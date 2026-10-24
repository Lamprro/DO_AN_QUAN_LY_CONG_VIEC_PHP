<?php

namespace App\Services;

use App\Contracts\AiProvider;
use App\Data\AiProviderResult;
use App\Exceptions\AiProviderUnavailable;
use App\Models\AiRun;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gemini AI Provider implementation for Google Generative AI API.
 */
class GeminiAiProvider implements AiProvider
{
    public function complete(AiRun $run): AiProviderResult
    {
        $key = config('ai.key');
        if (! $key) {
            throw new AiProviderUnavailable('Chưa cấu hình API Key cho Gemini (AI_API_KEY).');
        }

        // Support model from run context if provided, fallback to config
        $model = $run->context_snapshot['model'] ?? config('ai.model', 'gemini-2.5-flash');
        if (! $model || $model === 'unconfigured') {
            $model = 'gemini-2.5-flash';
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";

        $systemInstruction = match ($run->operation->value) {
            'report' => 'Bạn là chuyên gia phân tích dữ liệu và tiến độ dự án. Hãy đưa ra nhận xét, đánh giá tiến độ và lời khuyên hữu ích bằng tiếng Việt dựa trên dữ liệu báo cáo được cung cấp. Trả về văn bản thuần ngắn gọn, súc tích, chuyên nghiệp.',
            'assistance' => 'Bạn là chuyên gia trợ lý kỹ thuật và quản lý công việc. Hãy phân tích vấn đề đang gặp phải (blocker) và đưa ra các bước gợi ý xử lý cụ thể bằng tiếng Việt.',
            default => 'Return text containing ONLY valid JSON matching plan_schema. Do not execute actions. Use task_id only for existing context tasks. Never invent user UUIDs. Do not wrap in markdown codeblock.',
        };

        $userPrompt = $run->user_prompt ?? 'Hãy phân tích bối cảnh công việc và đưa ra phản hồi.';
        $contextJson = json_encode($run->context_snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $promptText = "BỐI CẢNH DỮ LIỆU:\n{$contextJson}\n\nYÊU CẦU NGƯỜI DÙNG:\n{$userPrompt}";

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout(config('ai.timeout_seconds', 45))
                ->post($url, [
                    'system_instruction' => [
                        'parts' => [
                            ['text' => $systemInstruction],
                        ],
                    ],
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $promptText],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.4,
                        'maxOutputTokens' => 8192,
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::error('Gemini API connection error: ' . $e->getMessage());
            throw new \RuntimeException('Không thể kết nối đến máy chủ Gemini API.');
        }

        if (in_array($response->status(), [400, 401, 403, 404, 422], true)) {
            $errorBody = $response->json();
            $msg = $errorBody['error']['message'] ?? 'Gemini API từ chối yêu cầu.';
            Log::warning('Gemini API error: ' . $msg);
            throw new AiProviderUnavailable('Gemini API từ chối yêu cầu: ' . $msg);
        }

        if (! $response->successful()) {
            throw new \RuntimeException('Gemini API trả về lỗi HTTP: ' . $response->status());
        }

        $data = $response->json();
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (! is_string($text) || trim($text) === '') {
            throw new AiProviderUnavailable('Gemini API không trả về nội dung văn bản.');
        }

        // Clean markdown code blocks if present
        if (in_array($run->operation->value, ['planning', 'replanning'], true)) {
            $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
            $text = preg_replace('/\s*```$/', '', $text);
        }

        $inputTokens = $data['usageMetadata']['promptTokenCount'] ?? null;
        $outputTokens = $data['usageMetadata']['candidatesTokenCount'] ?? null;

        return new AiProviderResult(trim($text), $inputTokens, $outputTokens);
    }
}
