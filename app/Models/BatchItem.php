<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BatchItem extends Model
{
    protected $fillable = [
        'batch_id',
        'data',
        'account_number',
        "beneficiary_name",
        "amount",
        "narration",
        "external_reference",
        'bank_code',
        'tenant_id',
        'status',
        'error_message',
        'failue_reason',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new TenantScope());
    }


    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($filters['beneficiary_name'] ?? null, fn($q, $v) => $q->where('beneficiary_name', 'like', "%{$v}%"));
    }
}
