<?php

namespace App\Services\Batch;

use App\Models\Batch;
use App\Models\BatchItem;
use App\Enums\BatchStatusItem;
use App\Enums\BatchStatus;
use App\Actions\Batch\CreateBatch;
use App\Exceptions\BatchException;
use App\Http\Traits\ResponseTrait;
use App\Jobs\PostBatchJob;
use App\Models\IdempotencyKey;
use App\Services\AuditTrails\AuditTrailService;
use Illuminate\Support\Facades\DB;

class BatchService
{


    use ResponseTrait;


    public function __construct(
        protected BatchParserService $batchParserService,
        protected AuditTrailService $auditTrail,
        protected CreateBatch $createBatch
    ) {}



    public function createBatch(array $data, $file = null)
    {

        $user = auth()->user();
        $createBatch = app(CreateBatch::class);

        $data['items'] = $data['source'] === 'csv'
            ? $this->batchParserService->parseCSV($file)
            : $data['items'];

        $batch = $createBatch($data, $user);

        $this->auditTrail->log($batch, 'created', [
            'total_items' => $batch->total_items,
            'total_amount' => $batch->total_amount,
            'source'      => $batch->source,
        ]);

        return $batch->load('items');
    }

    // submit for approval
    public function submitBatch(Batch $batch): Batch
    {
        if ($batch->status === BatchStatus::PENDING_APPROVAL) {
            throw new BatchException("Batch has been submitted already");
        }

        if ($batch->status !== BatchStatus::VALIDATED) {
            throw new BatchException("Batch must be VALIDATED before submission");
        }
        $batch->status = BatchStatus::PENDING_APPROVAL;
        $batch->submitted_at = now();
        $batch->save();

        $this->auditTrail->log($batch, 'submitted');
        return $batch;
    }

    public function getAllBatches(array $filters = [], int $perPage = 20)
    {
        $user = auth()->user();

        return Batch::visibleTo($user)
            ->filter($filters)
            ->with(['items', 'creator'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }


    // approve
    public function approveBatch(Batch $batch): Batch
    {
        $this->ensureApproverRole();
        if ($batch->status !== BatchStatus::PENDING_APPROVAL) {
            throw new \Exception("Batch must be PENDING_APPROVAL to approve");
        }
        $batch->status = BatchStatus::APPROVED;
        $batch->approved_by = auth()->id();
        $batch->approved_at = now();
        $batch->save();

        $this->auditTrail->log($batch, 'approved');
        return $batch;
    }

    // reject
    public function rejectBatch(Batch $batch, string $reason): Batch
    {
        $this->ensureApproverRole();
        if ($batch->status !== BatchStatus::PENDING_APPROVAL) {
            throw new BatchException("Batch must be PENDING_APPROVAL to reject");
        }
        $batch->status = BatchStatus::REJECTED;
        $batch->rejection_reason = $reason;
        $batch->rejected_by = auth()->id();
        $batch->rejected_at = now();
        $batch->save();

        $this->auditTrail->log($batch, 'rejected', [
            'reason' => $reason,
        ]);

        return $batch;
    }

    public function postBatch(Batch $batch)
    {
        $this->ensureAdminRole();
        $user = auth()->user();

        // idempotency check
        $key = "post_batch_{$batch->batch_id}";
        $exists = IdempotencyKey::where('tenant_id', $user->tenant_id)
            ->where('key', $key)
            ->exists();

        if ($exists) {
            throw new BatchException("Batch has already been submitted for posting");
        }

        if ($batch->status !== BatchStatus::APPROVED) {
            throw new BatchException("Only approved batches can be posted");
        }

        // store key before dispatching
        DB::transaction(function () use ($batch, $user, $key) {

            IdempotencyKey::create([
                'tenant_id' => $user->tenant_id,
                'key'       => $key,
                'action'    => 'post_batch',
            ]);

            $batch->update([
                'status' => BatchStatus::POSTING
            ]);
        });

        PostBatchJob::dispatch($batch, $user->tenant_id, $user->id);
    }

    public function retryBatch(Batch $batch): void
    {

        $user = auth()->user();
        $this->ensureAdminRole();

        // batch must be posted or partially_posted
        if (!in_array($batch->status, [BatchStatus::POSTED, BatchStatus::PARTIALLY_POSTED, BatchStatus::POSTING])) {
            throw new BatchException("Only posted batches can be retried");
        }

        // batch must have failed items
        $hasFailed = $batch->items()
            ->whereIn('status', [BatchStatusItem::FAILED, BatchStatusItem::VALID])
            ->exists();

        if (!$hasFailed) {
            throw new BatchException("No failed items to retry");
        }

        // dispatch same job
        PostBatchJob::dispatch($batch, $batch->tenant_id, $user->id);

        $failedCount = $batch->items()->where('status', BatchStatusItem::FAILED)->count();
        $this->auditTrail->log($batch, 'retried', ["message" => "Retrying {$failedCount} failed items"]);
    }



    private function ensureApproverRole(): void
    {
        if (!in_array(auth()->user()->role, ['approver', 'admin'])) {
            abort(403, 'Only approvers/admins can perform this action');
        }
    }

    private function ensureAdminRole(): void
    {
        if (!in_array(auth()->user()->role, ['admin'])) {
            abort(403, 'Only admins can perform this action');
        }
    }
}
