<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\Scopes\TenantScope;

class AuditTrail extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'action',
        'metadata',
        'batch_id',
        'changes',
    ];
    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new TenantScope());
    }


    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}
