<?php


namespace App\Actions\Batch;

use App\Enums\BatchStatus;
use App\Enums\BatchStatusItem;
use App\Models\User;
use Illuminate\Support\Str;
use App\Models\BatchItem;
use Illuminate\Support\Facades\DB;

class CreateBatch
{

    public function __invoke(array $data, User $user)
    {
        $totalAmount = 0;

        $items = [];
        foreach ($data['items'] as $row) {
            $totalAmount += $row['amount'];
            $items[] = [
                'account_number'     => $row['account_number'],
                'beneficiary_name'   => $row['beneficiary_name'],
                'amount'             => $row['amount'],
                'narration'          => $row['narration'],
                'bank_code'          => $row['bank_code'],
                'external_reference' => $row['external_reference'],
                'status'             => BatchStatusItem::PENDING,
                'tenant_id'          => $user->tenant_id,
            ];
        }





        return DB::transaction(function () use ($data, $user, $items, $totalAmount) {

            $batch = $user->tenant->batches()->create([
                'status'       => BatchStatus::DRAFT,
                'source'       => $data['source'] ?? 'manual',
                'created_by'      => $user->id,
                'total_items'  => count($items),
                'total_amount' => $totalAmount,
                'batch_id' => Str::uuid()
            ]);

            $now = now();
            $items = array_map(fn($item) => array_merge($item, [
                'batch_id'   => $batch->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]), $items);

            BatchItem::insert($items);

            return $batch;
        });
    }
}
