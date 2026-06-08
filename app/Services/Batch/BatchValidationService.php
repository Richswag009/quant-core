<?php

namespace App\Services\Batch;

use App\Http\Traits\ResponseTrait;
use App\Models\Batch;
use App\Enums\BatchStatusItem;
use App\Enums\BatchStatus;
use App\Exceptions\BatchException;
// use App\Exceptions\BatchException;
use App\Services\AuditTrails\AuditTrailService;

class BatchValidationService
{

    // use ResponseTrait;

    public function __construct(
        protected AuditTrailService $auditTrail
    ) {}

    public function validateBatch(Batch $batch)
    {

        if ($batch->status === BatchStatus::VALIDATED) {
            throw new BatchException("This batch has already been validated and cannot be validated again.");
        }

        if ($batch->status !== BatchStatus::DRAFT) {
            throw new BatchException("Only batches in DRAFT status can be validated.");
        }


        $validCount = 0;
        $invalidCount = 0;

        $references = $batch->items->pluck('external_reference')->toArray();
        $duplicates = array_diff_assoc($references, array_unique($references));

        // 2. Validate each item
        foreach ($batch->items->whereNotIn('status', ['VALID']) as $item) {

            $errors = $this->validateItem($item, $duplicates);

            if (empty($errors)) {
                $item->status = BatchStatusItem::VALID->value;
                $item->validation_error = null;
                $validCount++;
            } else {
                $item->status = BatchStatusItem::INVALID->value;
                $item->validation_error = implode(', ', $errors);
                $invalidCount++;
            }

            $item->save();
        }

        // 3. Decide batch status
        if ($invalidCount === 0) {
            $batch->status = BatchStatus::VALIDATED;
            $action = 'batch_validated';
        } else {
            $batch->status = BatchStatus::DRAFT;
            $action = 'batch_validation_failed';
        }

        $batch->save();

        // count all valid items including previously validated ones
        $validCount = $batch->items()->where('status', BatchStatusItem::VALID)->count();
        $invalidCount = $batch->items()->where('status', BatchStatusItem::INVALID)->count();


        $this->auditTrail->log($batch, $action, [
            'valid' => $validCount,
            'invalid' => $invalidCount,
        ]);

        // 5. Return summary
        return [
            'batch_id' => $batch->id,
            'status' => $batch->status,
            'valid_items' => $validCount,
            'invalid_items' => $invalidCount,
        ];
    }

    /**
     * Item validation rules
     */
    private function validateItem($item, $duplicates = []): array
    {
        $errors = [];

        if (in_array($item->external_reference, $duplicates)) {
            $errors[] = 'Duplicate external reference within batch';
        }

        if (empty($item->beneficiary_name)) {
            $errors[] = 'Beneficiary name is required';
        }

        if (!is_numeric($item->amount) || $item->amount <= 0) {
            $errors[] = 'Amount must be greater than 0';
        }

        if (empty($item->external_reference)) {
            $errors[] = 'External reference is required';
        }

        // account_number — exactly 10 digits
        if (!preg_match('/^\d{10}$/', $item->account_number)) {
            $errors[] = 'Account number must be exactly 10 digits';
        }

        // bank_code — exactly 3 digits
        if (!preg_match('/^\d{3}$/', $item->bank_code)) {
            $errors[] = 'Bank code must be exactly 3 digits';
        }

        // narration — max 100 chars
        if (strlen($item->narration) > 100) {
            $errors[] = 'Narration must not exceed 100 characters';
        }

        return $errors;
    }
}
