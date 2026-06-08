<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuditLogResource;
use App\Models\Batch;
use Illuminate\Http\Request;

class AuditTrailController extends Controller
{
    //
    public function index(Batch $batch)
    {
        $audits = $batch->audits()
            ->with('user')
            ->orderBy('created_at')
            ->paginate(50);

        return $this->okResponse("fetched batch audits successfully", AuditLogResource::collection($audits));
    }
}
