<?php

namespace App\Jobs;

use App\Enums\BatchStatus;
use App\Enums\BatchStatusItem;
use App\Services\Batch\FakePostingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Testing\Fakes\Fake;
use App\Models\Batch;
use App\Services\AuditTrails\AuditTrailService;

class PostBatchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;        // retry job 3 times if it fails
    public int $timeout = 60;     // kill job after 60 seconds
    public array $backoff = [10, 30, 60];     // wait 10 seconds between retries



    /**
     * Create a new job instance.
     */
    public function __construct(
        public Batch $batch,
        public string $tenantId,
        public int $userId
    ) {}
    /**
     * Execute the job.
     */
    public function handle(FakePostingService $fakePostingService, AuditTrailService $auditTrail): void
    {

        $items = $this->batch->items()
            ->where('tenant_id', $this->tenantId)
            ->whereIn('status', [BatchStatusItem::VALID, BatchStatusItem::FAILED])
            ->get();

        foreach ($items as $item) {

            try {
                $fakePostingService->postBatch($item);
                $item->status = BatchStatusItem::POSTED;
                $item->posted_at = now();
                $item->save();
            } catch (\Throwable $th) {
                $item->status = BatchStatusItem::FAILED;
                $item->posting_error = $th->getMessage();
                $item->save();
            }
        }

        // update batch status after all items are processed
        $hasFailures = $this->batch->items()
            ->where('status', BatchStatusItem::FAILED)
            ->exists();

        $this->batch->update([
            'status'    => $hasFailures ? BatchStatus::PARTIALLY_POSTED : BatchStatus::POSTED,
            'posted_at' => now(),
        ]);

        $postedCount = $this->batch->items()->where('status', BatchStatusItem::POSTED)->count();
        $failedCount = $this->batch->items()->where('status', BatchStatusItem::FAILED)->count();

        // PostBatchJob
        $auditTrail->log(
            $this->batch,
            'posted',
            [
                'posted' => $postedCount,
                'failed' => $failedCount,
            ],
            $this->userId
        );
    }

    public function failed(\Throwable $exception): void
    {
        // update batch status to reflect complete failure
        $this->batch->update([
            'status' => BatchStatus::FAILED,
        ]);

        // log to audit trail
        $auditTrail = app(AuditTrailService::class);
        $auditTrail->log(
            $this->batch,
            'job_failed',
            [
                "message" => "Posting job failed after {$this->tries} attempts: " . $exception->getMessage()
            ],
            $this->userId
        );
    }
}
