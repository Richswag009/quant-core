<?php

namespace App\Traits;

use App\Models\OutboundTransaction;

trait TagTrait
{
    protected function calculateTags($transaction): array
    {
        $tags = [];
        $statusCode = $transaction->status_code;

        // Server errors (5xx)
        if ($statusCode >= OutboundTransaction::SERVER_ERROR) {
            $tags[] = ['value' => 'Server Error', 'color' => '#7f1c1d'];
        }

        // Client errors
        if ($statusCode === OutboundTransaction::UNAUTHORIZED) {
            $tags[] = ['value' => 'Auth Failed', 'color' => '#991b1b'];
        }

        if ($statusCode === OutboundTransaction::TOO_MANY_REQUESTS) {
            $tags[] = ['value' => 'Rate Limited', 'color' => '#92400e'];
        }

        if ($statusCode === OutboundTransaction::TIMEOUT) {
            $tags[] = ['value' => 'Timeout', 'color' => '#713f11'];
        }

        // Performance issues
        if ($transaction->duration > OutboundTransaction::SLOW_RESPONSE) {
            $tags[] = ['value' => 'Slow Response', 'color' => '#7c2d12'];
        }

        if ($transaction->memory_usage && $transaction->memory_usage > OutboundTransaction::HIGH_MEMORY_USAGE) {
            $tags[] = ['value' => 'High Memory', 'color' => '#334155'];
        }

        return $tags;
    }
}
