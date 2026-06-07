<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use Illuminate\Http\Request;

class AuditTrailController extends Controller
{
    //
    public function index(Batch $batch)
    {
        $audits = $batch->audits()
            ->orderBy('created_at')
            ->get();

        return $this->okResponse("fetched batch audits successfully", $audits);
    }
}
