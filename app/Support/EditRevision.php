<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditRevision
{
    public static function check(Model $model, array $data): void
    {
        if (! isset($data['expected_revision']) || (int) $data['expected_revision'] !== (int) $model->revision) {
            throw ValidationException::withMessages(['expected_revision' => 'Dữ liệu đã được cập nhật ở nơi khác. Bản nhập của bạn được giữ lại; mở dữ liệu mới để đối chiếu trước khi lưu.']);
        }
    }
}
