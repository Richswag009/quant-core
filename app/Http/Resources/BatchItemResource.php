<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatchItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'beneficiary_name'   => $this->beneficiary_name,
            'account_number'     => $this->account_number,
            'bank_code'          => $this->bank_code,
            'amount'             => (float) $this->amount,
            'narration'          => $this->narration,
            'external_reference' => $this->external_reference,
            'status'             => $this->status,
            'validation_error'   => $this->validation_error,
            'posting_error'      => $this->posting_error,
            'posted_at'          => optional($this->posted_at)?->toDateTimeString(),
            'created_at'         => $this->created_at?->toDateTimeString(),
        ];
    }
}
