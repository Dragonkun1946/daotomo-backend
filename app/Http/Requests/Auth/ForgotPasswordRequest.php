<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseApiRequest;

class ForgotPasswordRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|email',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Vui lòng cung cấp địa chỉ email.',
            'email.email' => 'Vui lòng cung cấp địa chỉ email hợp lệ.',
        ];
    }
}
