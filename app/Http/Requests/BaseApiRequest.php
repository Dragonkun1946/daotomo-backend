<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class BaseApiRequest extends FormRequest
{
    /**
     * HTTP status code returned upon validation failure.
     */
    protected int $statusCode = 422;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Handle a failed validation attempt with standardized API JSON response.
     *
     * @param Validator $validator
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        $firstError = collect($validator->errors())->flatten()->first() ?: 'Dữ liệu không hợp lệ.';

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $firstError,
            'errors' => $validator->errors(),
        ], $this->statusCode));
    }
}
