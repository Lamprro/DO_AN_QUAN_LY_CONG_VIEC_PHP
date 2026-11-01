<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class SystemHealthCheck extends Command
{
    protected $signature = 'app:health-check {--ai : Kiểm tra gọi trực tiếp đến API của nhà cung cấp AI thật}';
    protected $description = 'Kiểm tra toàn diện sức khỏe hệ thống: Database MySQL, Storage, Queue, Cache và Gemini AI';

    public function handle(): int
    {
        $this->newLine();
        $this->info('================================================================');
        $this->info('   KIỂM TRA SỨC KHỎE HỆ THỐNG — DIAGNOSTIC PREFLIGHT SYSTEM     ');
        $this->info('================================================================');
        $this->newLine();

        $rows = [];
        $hasFailure = false;

        // 1. Database Check
        $dbStart = microtime(true);
        try {
            DB::connection()->getPdo();
            $driver = DB::connection()->getDriverName();
            $dbName = DB::connection()->getDatabaseName();
            $tablesCount = count(Schema::getTableListing());
            $usersCount = User::count();
            $tasksCount = Task::count();
            $workspacesCount = Workspace::count();
            $projectsCount = Project::count();
            $dbTime = round((microtime(true) - $dbStart) * 1000, 2);

            $rows[] = [
                'Hệ thống CSDL',
                '<fg=green>PASS</>',
                "Driver: {$driver} | DB: {$dbName} | {$tablesCount} bảng ({$usersCount} users, {$workspacesCount} workspaces, {$projectsCount} projects, {$tasksCount} tasks)",
                "{$dbTime} ms",
            ];
        } catch (\Throwable $e) {
            $hasFailure = true;
            $rows[] = ['Hệ thống CSDL', '<fg=red>FAIL</>', $e->getMessage(), '-'];
        }

        // 2. Storage Writable Check
        $storagePaths = [
            'Cache' => storage_path('framework/cache'),
            'Sessions' => storage_path('framework/sessions'),
            'Views' => storage_path('framework/views'),
            'Logs' => storage_path('logs'),
            'Private Files' => storage_path('app/private'),
        ];
        $storageIssues = [];
        foreach ($storagePaths as $name => $path) {
            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
            }
            if (!is_writable($path)) {
                $storageIssues[] = "{$name} không có quyền ghi";
            }
        }
        if (empty($storageIssues)) {
            $rows[] = ['Quyền ghi Storage', '<fg=green>PASS</>', 'Các thư mục cache, sessions, views, logs, private files đều sẵn sàng', 'OK'];
        } else {
            $hasFailure = true;
            $rows[] = ['Quyền ghi Storage', '<fg=red>FAIL</>', implode(', ', $storageIssues), '-'];
        }

        // 3. Cache Test
        $cacheStart = microtime(true);
        try {
            $testKey = 'health_check_' . uniqid();
            Cache::put($testKey, 'ok', 60);
            $val = Cache::get($testKey);
            Cache::forget($testKey);
            $cacheTime = round((microtime(true) - $cacheStart) * 1000, 2);

            if ($val === 'ok') {
                $rows[] = ['Bộ nhớ Cache', '<fg=green>PASS</>', 'Store: ' . config('cache.default') . ' hoạt động chính xác', "{$cacheTime} ms"];
            } else {
                $hasFailure = true;
                $rows[] = ['Bộ nhớ Cache', '<fg=red>FAIL</>', 'Không đọc lại được giá trị test', '-'];
            }
        } catch (\Throwable $e) {
            $hasFailure = true;
            $rows[] = ['Bộ nhớ Cache', '<fg=red>FAIL</>', $e->getMessage(), '-'];
        }

        // 4. Queue System Check
        try {
            $queueDriver = config('queue.default');
            $jobsCount = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
            $failedJobsCount = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;

            $status = $failedJobsCount > 0 ? '<fg=yellow>WARN</>' : '<fg=green>PASS</>';
            $rows[] = ['Hàng đợi Queue', $status, "Driver: {$queueDriver} | {$jobsCount} jobs đang chờ | {$failedJobsCount} jobs lỗi", 'OK'];
        } catch (\Throwable $e) {
            $rows[] = ['Hàng đợi Queue', '<fg=yellow>WARN</>', $e->getMessage(), '-'];
        }

        // 5. AI Configuration & Provider Check
        $aiProvider = config('tasks.ai_provider') ?? env('AI_PROVIDER', 'gemini');
        $geminiKey = env('GEMINI_API_KEY');
        $aiModel = env('AI_MODEL', 'gemini-2.5-flash');

        if ($aiProvider === 'gemini') {
            if (!empty($geminiKey) && $geminiKey !== 'your_gemini_api_key_here') {
                if ($this->option('ai')) {
                    $aiStart = microtime(true);
                    try {
                        $res = Http::timeout(8)->post("https://generativelanguage.googleapis.com/v1beta/models/{$aiModel}:generateContent?key={$geminiKey}", [
                            'contents' => [['parts' => [['text' => 'ping']]]],
                        ]);
                        $aiTime = round((microtime(true) - $aiStart) * 1000, 2);
                        if ($res->successful()) {
                            $rows[] = ['Google Gemini AI', '<fg=green>PASS</>', "Kết nối thành công tới model {$aiModel}", "{$aiTime} ms"];
                        } else {
                            $rows[] = ['Google Gemini AI', '<fg=yellow>WARN</>', "HTTP {$res->status()}: " . substr($res->body(), 0, 80), "{$aiTime} ms"];
                        }
                    } catch (\Throwable $e) {
                        $rows[] = ['Google Gemini AI', '<fg=yellow>WARN</>', 'Không thể kết nối internet tới Gemini API: ' . $e->getMessage(), '-'];
                    }
                } else {
                    $rows[] = ['Cấu hình Gemini AI', '<fg=green>PASS</>', "Provider: {$aiProvider} | Model: {$aiModel} | Key: Đã thiết lập (Dùng --ai để test ping)", 'OK'];
                }
            } else {
                $rows[] = ['Cấu hình Gemini AI', '<fg=yellow>WARN</>', 'GEMINI_API_KEY chưa cấu hình trong .env (Hệ thống chạy chế độ offline fixture)', 'OFFLINE'];
            }
        } else {
            $rows[] = ['Cấu hình AI', '<fg=green>PASS</>', "Provider: {$aiProvider}", 'OK'];
        }

        $this->table(['Thành phần', 'Trạng thái', 'Chi tiết kiểm tra', 'Độ trễ'], $rows);
        $this->newLine();

        if ($hasFailure) {
            $this->error('Phát hiện vấn đề cần xử lý trước khi triển khai production!');
            return Command::FAILURE;
        }

        $this->info('Hệ thống hoạt động ổn định và sẵn sàng cho môi trường làm việc!');
        return Command::SUCCESS;
    }
}
