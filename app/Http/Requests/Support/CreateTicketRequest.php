<?php

namespace App\Http\Requests\Support;

use App\Http\Requests\BaseApiRequest;

class CreateTicketRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'category' => 'nullable|string|in:payment,bug,report,account,other',
            'subject' => 'required|string|min:4|max:150',
            'message' => 'required|string|min:10|max:2000',
            'minecraftUsername' => 'nullable|string|max:40',
            'minecraft_username' => 'nullable|string|max:40',
            'discordUsername' => 'nullable|string|max:64',
            'discord_username' => 'nullable|string|max:64',
            'contact' => 'nullable|string|max:120',
        ];
    }

    public function messages(): array
    {
        return [
            'category.in' => 'Phân loại ticket không hợp lệ.',
            'subject.required' => 'Vui lòng nhập tiêu đề hỗ trợ.',
            'subject.min' => 'Tiêu đề hỗ trợ phải có ít nhất 4 ký tự.',
            'subject.max' => 'Tiêu đề hỗ trợ tối đa 150 ký tự.',
            'message.required' => 'Vui lòng mô tả chi tiết vấn đề cần hỗ trợ.',
            'message.min' => 'Nội dung mô tả phải có ít nhất 10 ký tự.',
            'message.max' => 'Nội dung mô tả tối đa 2000 ký tự.',
        ];
    }
}
