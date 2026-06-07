<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'name',
    ];

    //
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }

    public function key()
    {
        return $this->hasMany(IdempotencyKey::class);
    }
}
