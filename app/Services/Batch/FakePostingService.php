<?php

namespace App\Services\Batch;

use App\Models\Batch;
use App\Models\BatchItem;
use App\Enums\BatchStatusItem;
use App\Enums\BatchStatus;


class FakePostingService
{

    public function postBatch(BatchItem $batchItem): bool
    {
        $random = mt_rand(1, 100) / 100; // generates 0.01 to 1.00
        $timeoutRate = config('services.posting.timeout_rate', 0.10);
        $failureRate = config('services.posting.failure_rate', 0.20);

        // 10% timeout simulation
        if ($random <= 0.10) {
            sleep(5); // sleep 5 seconds to simulate slow bank API
        }

        // 20% failure simulation
        if ($random <= 0.20) {
            throw new \Exception("Bank API timeout: could not reach provider");
        }

        // 70% success
        return true;
    }
}
