<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // 'name' => $this->name,
            'status' => $this->status,
            'batch_id' => $this->batch_id,
            'total_items' => $this->total_items,
            'total_amount' => $this->total_amount,
            'source' => $this->source,
            'created_by' => $this->whenLoaded('creator', fn() => [
                'slug'   => $this->creator->slug,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ]),
            "summary" => $this->summary,
            'items' => BatchItemResource::collection($this->whenLoaded('items'))
        ];
    }
}
