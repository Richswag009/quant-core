<?php

namespace App\Jobs;

use App\Enums\BatchStatus;
use App\Enums\BatchStatusItem;
use App\Services\Batch\FakePostingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Testing\Fakes\Fake;
use App\Models\Batch;
use App\Models\BatchItem;
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
            ->whereIn('status', [
                BatchStatusItem::VALID,
                BatchStatusItem::FAILED
            ])
            ->get();


        $postedIds = [];
        $failedItems = [];

        foreach ($items as $item) {

            try {
                $fakePostingService->postBatch($item);
                $postedIds[] = $item->id;
            } catch (\Throwable $th) {
                $failedItems[$item->id] = $th->getMessage();
            }
        }

        //  posted items
        if (!empty($postedIds)) {
            BatchItem::whereIn('id', $postedIds)->update([
                'status'        => BatchStatusItem::POSTED,
                'posted_at'     => now(),
                'posting_error' => null,
            ]);
        }

        // failed items
        if (!empty($failedItems)) {
            foreach ($failedItems as $id => $error) {
                BatchItem::where('id', $id)->update([
                    'status'        => BatchStatusItem::FAILED->value,
                    'posting_error' => $error,
                ]);
            }
        }

        $hasFailures = count($failedItems) > 0;

        $this->batch->update([
            'status'    => $hasFailures ? BatchStatus::PARTIALLY_POSTED : BatchStatus::POSTED,
            'posted_at' => now(),
        ]);
        $postedCount = count($postedIds);
        $failedCount = count($failedItems);

        // Post audit losgs
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
        // log to audit trail
        $auditTrail = app(AuditTrailService::class);

        // update batch status to reflect complete failure
        $this->batch->update([
            'status' => BatchStatus::FAILED,
        ]);


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
