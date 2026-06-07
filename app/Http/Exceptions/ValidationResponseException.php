<?php

namespace App\Exceptions;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ValidationResponseException extends HttpResponseException
{
    public const VALIDATION_ERROR_STATUS = 422;

    /**
     * The underlying validator instance
     */
    public Validator $validator;

    /**
     * The validator request instance
     */
    protected Request $request;

    /**
     * Create a new HTTP response exception instance using specified validator.
     */
    public function __construct(Validator $validator, ?Request $request = null)
    {
        $this->validator = $validator;
        $this->request = $request ?? request();
        parent::__construct(
            response()->json($this->getResponseData(), self::VALIDATION_ERROR_STATUS)
        );
    }

    /**
     * Get the response data for the exception
     */
    protected function getResponseData(): array
    {
        return [
            'status' => false,
            'message' => 'Validation Error Occurred.',
            'errors' => $this->formatValidationErrors(),
        ];
    }

    /**
     * Format the validator error messages
     */
    protected function formatValidationErrors(): array
    {
        $validation_messages = $this->validator->errors()->getMessages();

        $normalized_messages = array_unique(Arr::dot($validation_messages));

        $result = collect([]);
        collect($normalized_messages)->each(function ($message, $key) use (&$result) {
            $field = substr($key, 0, strpos($key, '.'));
            if (! $result->has($field)) {
                $result = $result->put($field, [
                    'message' => $message,
                    'rejected_value' => $this->request->input($field),
                ]);
            }
        });

        return $result->all();
    }

    /**
     * Get the underlying validation instance
     */
    public function getValidator(): Validator
    {
        return $this->validator;
    }

    /**
     * Get the underlying request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }
}
