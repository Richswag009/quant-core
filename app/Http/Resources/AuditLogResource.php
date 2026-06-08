<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'tenant_id' => $this->tenant_id,
            'batch_id'  => $this->batch->batch_id,
            'batch_ids'  => $this->batch,
            'actor' => [
                'slug' => $this->user?->slug,
                'name' => $this->user?->name
            ],
            'action'    => $this->action,
            'metadata'  => $this->metadata ?? (object) [],
            'summary' => $this->buildSummary(),

            'notes'     => $this->notes,
            'created_at' => $this->created_at?->toISOString(),

        ];
    }

    private function buildSummary(): string
    {
        return match ($this->action) {
            'created' => 'Batch was created',
            'approved' => 'Batch was approved',
            'submitted' => 'Batch was submitted',
            'validated' => 'Batch was validated',
            'rejected' => 'Batch was rejected',
            'posted' => 'Batch was posted',
            'retried' => 'Batch retry triggered',
            default => 'Action performed on batch',
        };
    }
}
