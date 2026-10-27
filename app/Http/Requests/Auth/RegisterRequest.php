<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseApiRequest;

class RegisterRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'username' => 'required|string|min:3|max:30|unique:users,username',
            'email' => 'required|email|max:100|unique:users,email',
            'password' => 'required|string|min:6|max:100',
            'minecraftUsername' => 'nullable|string|max:30',
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Vui lòng nhập tên người dùng.',
            'username.unique' => 'Tên người dùng đã được sử dụng.',
            'username.min' => 'Tên người dùng phải có ít nhất 3 ký tự.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không đúng định dạng.',
            'email.unique' => 'Email đã được sử dụng.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
        ];
    }
}
