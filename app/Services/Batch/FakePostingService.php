<?php

namespace App\Services\Batch;

use App\Models\Batch;
use App\Models\BatchItem;
use App\Enums\BatchStatusItem;
use App\Enums\BatchStatus;
use Exception;

class FakePostingService
{

    public function postBatch(BatchItem $batchItem): bool
    {
        $random = mt_rand(1, 100) / 100; // generates 0.01 to 1.00
        $timeoutRate = config('services.posting.timeout_rate', 0.10);
        $failureRate = config('services.posting.failure_rate', 0.20);

        $random = mt_rand(1, 100) / 100;

        if ($random <= $timeoutRate) {
            sleep(5);
            throw new \Exception("Timeout");
        }

        if ($random <= ($timeoutRate + $failureRate)) {
            throw new \Exception("Failure");
        }

        return true;
    }
}
