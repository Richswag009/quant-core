<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;

class Batch extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        "batch_id",
        'status',
        'source',
        'created_by',
        'approved_by',
        "total_items",
        "total_amount",
    ];

    protected static function booted()
    {
        static::addGlobalScope(new TenantScope());
    }

    public function getRouteKeyName()
    {
        return 'batch_id'; // or 'uuid'
    }

    public function items()
    {
        return $this->hasMany(BatchItem::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function audits()
    {
        return $this->hasMany(AuditTrail::class);
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($filters['source'] ?? null, fn($q, $v) => $q->where('source', $v))
            ->when($filters['from']   ?? null, fn($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to']     ?? null, fn($q, $v) => $q->whereDate('created_at', '<=', $v));
    }


    public function getSummaryAttribute(): array
    {
        $items = $this->relationLoaded('items')
            ? $this->items
            : $this->items()->get();

        return [
            'posted'  => $items->where('status', 'POSTED')->count(),
            'failed'  => $items->where('status', 'FAILED')->count(),
            'pending' => $items->where('status', 'PENDING')->count(),
            'invalid' => $items->where('status', 'INVALID')->count(),
        ];
    }
}
