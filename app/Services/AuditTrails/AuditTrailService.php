<?php

namespace App\Services\AuditTrails;

use App\Models\AuditTrail;
use App\Models\Batch;

class AuditTrailService
{
    public function log(
        Batch $batch,
        string $action,
        array $metadata = [],
        ?string $performedBy = null
    ): void {
        AuditTrail::create([
            'batch_id' => $batch->id,
            'tenant_id' => $batch->tenant_id,
            'action' => $action,
            'user_id' => $performedBy ?? auth()->id(),
            'metadata' => $metadata,
        ]);
    }
}
