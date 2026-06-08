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

        $items = $batch->items;

        if ($batch->status === BatchStatus::VALIDATED) {
            throw new BatchException("This batch has already been validated and cannot be validated again.");
        }

        if ($batch->status !== BatchStatus::DRAFT) {
            throw new BatchException("Only batches in DRAFT status can be validated.");
        }

        $validCount = 0;
        $invalidCount = 0;

        $references = $items->pluck('external_reference')->toArray();
        $duplicates = array_diff_assoc($references, array_unique($references));



        // 2. Validate each item
        foreach ($items as $item) {

            $errors = $this->validateItem($item, $duplicates);

            if (empty($errors)) {
                $item->status = BatchStatusItem::VALID;
                $item->validation_error = null;
                $validCount++;
            } else {
                $item->status = BatchStatusItem::INVALID;
                $item->validation_error = implode(', ', $errors);
                $invalidCount++;
            }

            $item->save();
        }

        // 3. Decide batch status
        $batch->status = $invalidCount === 0
            ? BatchStatus::VALIDATED
            : BatchStatus::DRAFT;

        $action = $invalidCount === 0
            ? 'batch_validated'
            : 'batch_validation_failed';


        $batch->save();

        // count all valid items including previously validated ones
        $finalValid = $items->where('status', BatchStatusItem::VALID)->count();
        $finalInvalid = $items->where('status', BatchStatusItem::INVALID)->count();

        $this->auditTrail->log($batch, $action, [
            'valid' => $finalValid,
            'invalid' => $finalInvalid,
        ]);

        // 5. Return summary
        return [
            'batch_id' => $batch->batch_id,
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
