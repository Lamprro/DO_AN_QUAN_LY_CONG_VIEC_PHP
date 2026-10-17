<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use ZipArchive;

class AttachmentFormat implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('Tệp tải lên không hợp lệ.');

            return;
        }
        $ext = strtolower($value->getClientOriginalExtension());
        $mime = $value->getMimeType();
        $map = ['txt' => ['text/plain'], 'csv' => ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'],
            'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'pdf' => ['application/pdf'],
            'docx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xlsx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']];
        if (! isset($map[$ext]) || ! in_array($mime, $map[$ext], true)) {
            $fail('Nội dung tệp không khớp phần mở rộng được phép.');

            return;
        }
        if (in_array($ext, ['docx', 'xlsx'], true)) {
            if (! class_exists(ZipArchive::class)) {
                $fail('Máy chủ cần bật PHP ZIP để nhận DOCX/XLSX.');

                return;
            }
            $zip = new ZipArchive;
            $opened = $zip->open($value->getRealPath(), ZipArchive::RDONLY);
            if ($opened !== true) {
                $fail('Tệp Office không có cấu trúc hợp lệ.');

                return;
            }
            $valid = $zip->numFiles <= 512 && $zip->locateName('[Content_Types].xml') !== false && $zip->locateName($ext === 'docx' ? 'word/document.xml' : 'xl/workbook.xml') !== false;
            $zip->close();
            if (! $valid) {
                $fail('Tệp Office không có cấu trúc hợp lệ.');
            }
        }
    }
}
