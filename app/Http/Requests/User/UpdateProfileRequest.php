<?php

namespace App\Http\Requests\User;

use App\Http\Requests\BaseApiRequest;

class UpdateProfileRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'name' => 'nullable|string|min:2|max:30|regex:/^[\p{L}\p{N}_\-\s]+$/u',
            'minecraftUsername' => 'nullable|string|regex:/^[a-zA-Z0-9_]{3,16}$/',
            'bio' => 'nullable|string|max:255',
            'avatar' => 'nullable|string|max:1000',
            'username' => 'nullable|string|min:3|max:30',
        ];
    }

    public function messages(): array
    {
        return [
            'name.min' => 'Tên hiển thị phải có ít nhất 2 ký tự.',
            'name.max' => 'Tên hiển thị không được vượt quá 30 ký tự.',
            'name.regex' => 'Tên hiển thị chỉ được chứa chữ cái, số, khoảng trắng và dấu gạch ngang/dưới.',
            'minecraftUsername.regex' => 'Tên Minecraft IGN phải từ 3 đến 16 ký tự và chỉ chứa chữ cái, số hoặc dấu gạch dưới (_).',
            'bio.max' => 'Tiểu sử / Giới thiệu không được vượt quá 255 ký tự.',
        ];
    }
}
