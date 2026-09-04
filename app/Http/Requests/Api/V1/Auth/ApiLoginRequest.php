<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ApiLoginRequest extends LoginRequest
{
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $validator->errors(),
            'code' => 'validation_error',
        ], 422));
    }
}
