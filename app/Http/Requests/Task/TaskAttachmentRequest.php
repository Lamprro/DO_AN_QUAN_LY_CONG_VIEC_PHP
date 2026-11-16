<?php

namespace App\Http\Requests\Task;

use App\Rules\AttachmentFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TaskAttachmentRequest extends FormRequest
{
    public function rules(): array
    {
        $maxKb = (int) config('tasks.attachment_max_kb', 10240);

        return [
            'file' => ['nullable', 'file', 'max:'.$maxKb, new AttachmentFormat],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['bail', 'required', 'file', 'max:'.$maxKb, new AttachmentFormat],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $hasSingle = $this->hasFile('file');
            $hasMultiple = $this->hasFile('files');

            if (! $hasSingle && ! $hasMultiple) {
                $validator->errors()->add('files', 'Vui lòng chọn ít nhất một tệp hoặc hình ảnh để tải lên.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'files.max' => 'Bạn chỉ có thể tải lên tối đa 10 tệp cùng lúc.',
            'files.*.file' => 'Tệp tải lên không hợp lệ.',
            'files.*.max' => 'Mỗi tệp không được vượt quá dung lượng cho phép.',
        ];
    }
}
