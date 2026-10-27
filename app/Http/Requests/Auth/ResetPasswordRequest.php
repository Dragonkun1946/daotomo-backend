<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseApiRequest;

class ResetPasswordRequest extends BaseApiRequest
{
    protected int $statusCode = 400;

    public function rules(): array
    {
        return [
            'password' => 'required|string|min:6|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'Vui lòng cung cấp mật khẩu mới.',
            'password.min' => 'Mật khẩu mới phải có ít nhất 6 ký tự.',
            'password.max' => 'Mật khẩu không được vượt quá 100 ký tự.',
        ];
    }
}
