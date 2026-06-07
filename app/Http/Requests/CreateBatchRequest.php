<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\String\TruncateMode;

class CreateBatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'source' => 'required|in:csv,json',
        ];

        if ($this->input('source') === 'json') {
            $rules = array_merge($rules, [
                'items'                          => 'required|array|min:1',
                'items.*.beneficiary_name'       => 'required|string',
                'items.*.account_number'         => 'required|string|size:10',
                'items.*.bank_code'              => 'required|string|size:3',
                'items.*.amount'                 => 'required|numeric|min:0.01',
                'items.*.narration'              => 'required|string|max:100',
                'items.*.external_reference'     => 'required|string|max:255',
            ]);
        }

        if ($this->input('source') === 'csv') {
            $rules = array_merge($rules, [
                'file' => 'required|file|mimes:csv,txt|max:2048',
            ]);
        }

        return $rules;
    }
}
