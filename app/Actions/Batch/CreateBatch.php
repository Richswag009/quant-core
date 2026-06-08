<?php


namespace App\Actions\Batch;

use App\Enums\BatchStatus;
use App\Enums\BatchStatusItem;
use App\Http\Resources\UserResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Http\Traits\ResponseTrait;
use Illuminate\Support\Facades\DB;

class CreateBatch
{
    //
    use ResponseTrait;

    public function __invoke(array $data, User $user)
    {
        return DB::transaction(function () use ($data, $user) {

            $batch = $user->tenant->batches()->create([
                'status'       => BatchStatus::DRAFT,
                'source'       => $data['source'] ?? 'manual',
                'created_by'      => $user->id,
                'total_items'  => count($data['items']),
                'total_amount' => array_sum(array_column($data['items'], 'amount')),
                'batch_id' => Str::uuid()
            ]);

            foreach ($data['items'] as $row) {
                $batch->items()->create([
                    'account_number'     => $row['account_number'],
                    'beneficiary_name'   => $row['beneficiary_name'],
                    'amount'             => $row['amount'],
                    'narration'          => $row['narration'],
                    'bank_code'          => $row['bank_code'],
                    'external_reference' => $row['external_reference'],
                    'status'             => BatchStatusItem::PENDING,
                    'tenant_id'          => $user->tenant_id,
                ]);
            }

            return $batch;
        });
    }
}
