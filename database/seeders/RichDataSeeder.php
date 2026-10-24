<?php

namespace Database\Seeders;

use App\Models\CalendarEvent;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskBlocker;
use App\Models\TaskDependency;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RichDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $defaultPassword = Hash::make('Demo@123456');

            // 1. Tạo thêm các thành viên chủ chốt với tên tiếng Việt chuyên nghiệp
            $teamUsers = [
                ['email' => 'dev6@example.test', 'name' => 'Vũ Hải Đăng', 'title' => 'Mobile Flutter Dev'],
                ['email' => 'dev7@example.test', 'name' => 'Bùi Lan Hương', 'title' => 'UI/UX Designer'],
                ['email' => 'dev8@example.test', 'name' => 'Hoàng Anh Đức', 'title' => 'DevOps Cloud Engineer'],
                ['email' => 'dev9@example.test', 'name' => 'Nguyễn Minh Châu', 'title' => 'Product Owner'],
                ['email' => 'dev10@example.test', 'name' => 'Đỗ Khắc Nam', 'title' => 'Fullstack Developer'],
            ];

            $extraUsers = [];
            foreach ($teamUsers as $u) {
                $user = User::firstOrCreate(['email' => $u['email']], [
                    'name' => $u['name'],
                    'password' => $defaultPassword,
                    'status' => 'active',
                    'timezone' => 'Asia/Ho_Chi_Minh',
                ]);
                UserSetting::firstOrCreate(
                    ['user_id' => $user->id],
                    ['settings' => config('work_management.settings_defaults')]
                );
                $extraUsers[] = $user;
            }

            // Lấy lại các user chính
            $owner = User::where('email', 'demo1@example.test')->first();
            $manager = User::where('email', 'demo2@example.test')->first();
            $devA = User::where('email', 'demo3@example.test')->first();
            $devB = User::where('email', 'demo4@example.test')->first();
            $qa = User::where('email', 'demo5@example.test')->first();

            $allUsers = array_merge([$owner, $manager, $devA, $devB, $qa], $extraUsers);

            // Cập nhật tên hiển thị đẹp cho các tài khoản demo gốc nếu chưa đổi
            $owner->update(['name' => 'Nguyễn Văn An (Tech Lead)']);
            $manager->update(['name' => 'Trần Thị Mai (Project Manager)']);
            $devA->update(['name' => 'Lê Hoàng Long (Senior Backend)']);
            $devB->update(['name' => 'Phạm Thu Trang (Frontend Lead)']);
            $qa->update(['name' => 'Đặng Minh Tuấn (QA/QC Lead)']);

            // 2. Tạo hoặc lấy Workspace chính: "Công ty Cổ phần Công nghệ AlphaTech"
            $workspace = Workspace::firstOrCreate(
                ['name' => 'AlphaTech Enterprise', 'owner_id' => $owner->id],
                ['kind' => 'team', 'timezone' => 'Asia/Ho_Chi_Minh']
            );

            // Tạo các vai trò
            $roles = [];
            foreach (config('permissions.defaults') as $roleName => $perms) {
                $roles[$roleName] = Role::firstOrCreate(
                    ['workspace_id' => $workspace->id, 'name' => $roleName],
                    ['permissions' => $perms, 'is_system' => true, 'created_by' => $owner->id]
                );
            }

            // Thêm vai trò tùy biến "Tech Lead" và "QA Lead"
            $roles['Tech Lead'] = Role::firstOrCreate(
                ['workspace_id' => $workspace->id, 'name' => 'Tech Lead'],
                ['permissions' => $roles['Manager']->permissions, 'is_system' => false, 'created_by' => $owner->id]
            );

            // Gán tất cả thành viên vào workspace
            $workspaceMembers = [];
            foreach ($allUsers as $index => $u) {
                $role = match ($index) {
                    0 => $roles['Owner'],
                    1 => $roles['Manager'],
                    2, 7 => $roles['Tech Lead'],
                    4 => $roles['Member'],
                    default => $roles['Member'],
                };

                $workspaceMembers[$u->id] = WorkspaceMember::firstOrCreate(
                    ['workspace_id' => $workspace->id, 'user_id' => $u->id],
                    ['role_id' => $role->id, 'status' => 'active', 'joined_at' => now()->subMonths(2)]
                );
            }

            // 3. Tạo các Dự án quy mô thực tế
            $projectsData = [
                [
                    'name' => 'Nền tảng Thương mại Điện tử E-Commerce 2026',
                    'description' => 'Xây dựng hệ thống sàn TMĐT đa kênh, tích hợp thanh toán tự động, quản lý kho vận và AI gợi ý sản phẩm.',
                    'start_date' => now()->subDays(20)->toDateString(),
                    'deadline' => now()->addDays(70)->toDateString(),
                    'status' => 'active',
                ],
                [
                    'name' => 'Trợ lý Trí tuệ Nhân tạo Gemini Work Assistant',
                    'description' => 'Tích hợp mô hình Gemini AI để tự động phân tích tiến độ, lập lịch biểu thông minh và gỡ rối task blocker.',
                    'start_date' => now()->subDays(10)->toDateString(),
                    'deadline' => now()->addDays(45)->toDateString(),
                    'status' => 'active',
                ],
                [
                    'name' => 'Tái cấu trúc Hạ tầng Cloud & DevOps CI/CD',
                    'description' => 'Triển khai Kubernetes cluster, hệ thống giám sát Prometheus/Grafana và đường ống CI/CD tự động hóa.',
                    'start_date' => now()->subDays(15)->toDateString(),
                    'deadline' => now()->addDays(30)->toDateString(),
                    'status' => 'active',
                ],
                [
                    'name' => 'Ứng dụng Di động AlphaTech Mobile App (iOS & Android)',
                    'description' => 'Phát triển ứng dụng mobile đa nền tảng bằng Flutter, đồng bộ thời gian thực qua WebSockets.',
                    'start_date' => now()->subDays(5)->toDateString(),
                    'deadline' => now()->addDays(60)->toDateString(),
                    'status' => 'active',
                ],
            ];

            $projects = [];
            foreach ($projectsData as $pData) {
                $proj = Project::firstOrCreate(
                    ['workspace_id' => $workspace->id, 'name' => $pData['name']],
                    [
                        'created_by' => $owner->id,
                        'description' => $pData['description'],
                        'status' => $pData['status'],
                        'start_date' => $pData['start_date'],
                        'deadline' => $pData['deadline'],
                    ]
                );

                // Gán tất cả thành viên vào dự án
                foreach ($workspaceMembers as $wm) {
                    ProjectMember::firstOrCreate(
                        ['project_id' => $proj->id, 'workspace_member_id' => $wm->id],
                        ['role_id' => $wm->role_id, 'status' => 'active', 'joined_at' => now()->subDays(15)]
                    );
                }

                $projects[] = $proj;
            }

            // 4. Tạo chi tiết danh sách Task phong phú cho Dự án 1 (E-Commerce)
            $pEcom = $projects[0];

            $groupBackend = Task::firstOrCreate(
                ['workspace_id' => $workspace->id, 'code' => 'ECOM-GRP-BE'],
                ['project_id' => $pEcom->id, 'title' => 'Phát triển Hệ thống Backend & API', 'task_type' => 'GROUP', 'created_by' => $owner->id]
            );

            $groupFrontend = Task::firstOrCreate(
                ['workspace_id' => $workspace->id, 'code' => 'ECOM-GRP-FE'],
                ['project_id' => $pEcom->id, 'title' => 'Giao diện Người dùng & Trải nghiệm (UI/UX)', 'task_type' => 'GROUP', 'created_by' => $owner->id]
            );

            $groupInfra = Task::firstOrCreate(
                ['workspace_id' => $workspace->id, 'code' => 'ECOM-GRP-OPS'],
                ['project_id' => $pEcom->id, 'title' => 'Cơ sở dữ liệu & Tích hợp Cổng thanh toán', 'task_type' => 'GROUP', 'created_by' => $owner->id]
            );

            $ecomTasks = [
                // Backend tasks
                ['code' => 'ECOM-101', 'title' => 'Thiết kế cơ sở dữ liệu MySQL chuẩn 3NF cho đơn hàng và giỏ hàng', 'group' => $groupBackend, 'assignee' => $devA, 'reviewer' => $owner, 'status' => 'DONE', 'priority' => 'high', 'est' => 240, 'spent' => 240, 'completed' => now()->subDays(10)],
                ['code' => 'ECOM-102', 'title' => 'Xây dựng API xác thực JWT và phân quyền RBAC đa cấp', 'group' => $groupBackend, 'assignee' => $devA, 'reviewer' => $manager, 'status' => 'DONE', 'priority' => 'high', 'est' => 300, 'spent' => 280, 'completed' => now()->subDays(8)],
                ['code' => 'ECOM-103', 'title' => 'Phát triển API quản lý danh mục sản phẩm và biến thể (SKU)', 'group' => $groupBackend, 'assignee' => $extraUsers[4], 'reviewer' => $devA, 'status' => 'DONE', 'priority' => 'medium', 'est' => 180, 'spent' => 180, 'completed' => now()->subDays(5)],
                ['code' => 'ECOM-104', 'title' => 'Tích hợp cổng thanh toán VNPay và Stripe Checkout webhook', 'group' => $groupBackend, 'assignee' => $devA, 'reviewer' => $owner, 'status' => 'IN_PROGRESS', 'priority' => 'high', 'est' => 360, 'spent' => 150, 'deadline' => now()->addDays(3)],
                ['code' => 'ECOM-105', 'title' => 'Tối ưu hóa truy vấn SQL tìm kiếm sản phẩm với Full-Text Search', 'group' => $groupBackend, 'assignee' => $extraUsers[4], 'reviewer' => $devA, 'status' => 'TODO', 'priority' => 'medium', 'est' => 120, 'deadline' => now()->addDays(7)],
                ['code' => 'ECOM-106', 'title' => 'Xây dựng hàng đợi Queue gửi email hóa đơn và thông báo đơn hàng', 'group' => $groupBackend, 'assignee' => $devA, 'reviewer' => $manager, 'status' => 'REVIEW', 'priority' => 'medium', 'est' => 150, 'deadline' => now()->addDays(2)],
                ['code' => 'ECOM-107', 'title' => 'Viết unit test cho logic tính phí vận chuyển và mã giảm giá coupon', 'group' => $groupBackend, 'assignee' => $qa, 'reviewer' => $devA, 'status' => 'IN_PROGRESS', 'priority' => 'high', 'est' => 180, 'deadline' => now()->addDays(4)],

                // Frontend tasks
                ['code' => 'ECOM-201', 'title' => 'Thiết kế bộ UI Design System và Figma Prototype cho trang chủ', 'group' => $groupFrontend, 'assignee' => $extraUsers[1], 'reviewer' => $manager, 'status' => 'DONE', 'priority' => 'high', 'est' => 300, 'spent' => 300, 'completed' => now()->subDays(12)],
                ['code' => 'ECOM-202', 'title' => 'Xây dựng layout Responsive chuẩn Mobile-First cho trang xem chi tiết sản phẩm', 'group' => $groupFrontend, 'assignee' => $devB, 'reviewer' => $extraUsers[1], 'status' => 'DONE', 'priority' => 'high', 'est' => 240, 'spent' => 220, 'completed' => now()->subDays(6)],
                ['code' => 'ECOM-203', 'title' => 'Tạo module Giỏ hàng động với hiệu ứng trượt Offcanvas mượt mà', 'group' => $groupFrontend, 'assignee' => $devB, 'reviewer' => $manager, 'status' => 'IN_PROGRESS', 'priority' => 'medium', 'est' => 180, 'spent' => 90, 'deadline' => now()->addDays(2)],
                ['code' => 'ECOM-204', 'title' => 'Tích hợp bộ lọc đa tiêu chí (giá, thương hiệu, màu sắc, đánh giá sao)', 'group' => $groupFrontend, 'assignee' => $devB, 'reviewer' => $owner, 'status' => 'REVIEW', 'priority' => 'medium', 'est' => 210, 'deadline' => now()->addDays(1)],
                ['code' => 'ECOM-205', 'title' => 'Tối ưu tốc độ tải trang Core Web Vitals (LCP dưới 1.5s, FID dưới 50ms)', 'group' => $groupFrontend, 'assignee' => $devB, 'reviewer' => $owner, 'status' => 'TODO', 'priority' => 'high', 'est' => 240, 'deadline' => now()->addDays(10)],

                // Infra & Integration tasks
                ['code' => 'ECOM-301', 'title' => 'Cài đặt cụm Redis Cache để lưu phiên đăng nhập và cache bảng giá', 'group' => $groupInfra, 'assignee' => $extraUsers[2], 'reviewer' => $owner, 'status' => 'DONE', 'priority' => 'high', 'est' => 120, 'spent' => 110, 'completed' => now()->subDays(4)],
                ['code' => 'ECOM-302', 'title' => 'Cấu hình hệ thống sao lưu tự động MySQL Backup hàng ngày lên Cloud', 'group' => $groupInfra, 'assignee' => $extraUsers[2], 'reviewer' => $owner, 'status' => 'IN_PROGRESS', 'priority' => 'high', 'est' => 90, 'spent' => 45, 'deadline' => now()->addDays(5)],
                ['code' => 'ECOM-303', 'title' => 'Kiểm thử tải đồng thời 5.000 người dùng với Apache JMeter', 'group' => $groupInfra, 'assignee' => $qa, 'reviewer' => $owner, 'status' => 'TODO', 'priority' => 'medium', 'est' => 300, 'deadline' => now()->addDays(12)],
            ];

            $createdTasks = [];
            foreach ($ecomTasks as $t) {
                $task = Task::firstOrCreate(
                    ['workspace_id' => $workspace->id, 'code' => $t['code']],
                    [
                        'project_id' => $pEcom->id,
                        'parent_task_id' => $t['group']->id,
                        'task_type' => 'WORK',
                        'title' => $t['title'],
                        'description' => 'Mô tả chi tiết cho công việc: ' . $t['title'] . '. Đảm bảo đáp ứng đầy đủ tiêu chí chất lượng và hoàn thành đúng hạn.',
                        'created_by' => $owner->id,
                        'assignee_id' => $t['assignee']->id,
                        'reviewer_id' => $t['reviewer']->id,
                        'status' => $t['status'],
                        'priority' => $t['priority'],
                        'estimate_minutes' => $t['est'],
                        'sort_order' => count($createdTasks) + 1,
                        'deadline_at' => $t['deadline'] ?? ($t['completed'] ?? now()->addDays(7)),
                        'completed_at' => $t['completed'] ?? null,
                        'started_at' => now()->subDays(5),
                    ]
                );
                $createdTasks[] = $task;
            }

            // Milestone
            Task::firstOrCreate(
                ['workspace_id' => $workspace->id, 'code' => 'ECOM-MLS-BETA'],
                [
                    'project_id' => $pEcom->id,
                    'parent_task_id' => $groupBackend->id,
                    'task_type' => 'MILESTONE',
                    'title' => 'Cột mốc: Đóng gói bản Beta v1.0 ra mắt nội bộ',
                    'created_by' => $owner->id,
                    'status' => 'IN_PROGRESS',
                    'deadline_at' => now()->addDays(15),
                ]
            );

            // 5. Thêm Task cho Dự án 2 (AI Gemini Assistant)
            $pAi = $projects[1];
            $groupAiCore = Task::firstOrCreate(
                ['workspace_id' => $workspace->id, 'code' => 'AI-GRP-CORE'],
                ['project_id' => $pAi->id, 'title' => 'Tích hợp Gemini API & Xử lý Prompt', 'task_type' => 'GROUP', 'created_by' => $owner->id]
            );

            $aiTasks = [
                ['code' => 'AI-101', 'title' => 'Cấu hình GeminiAiProvider kết nối Google Generative Language API', 'assignee' => $owner, 'reviewer' => $manager, 'status' => 'DONE', 'priority' => 'high', 'est' => 120, 'completed' => now()->subDays(1)],
                ['code' => 'AI-102', 'title' => 'Xây dựng Prompt System chuẩn cho tính năng AI Planning tự động tạo kế hoạch', 'assignee' => $devA, 'reviewer' => $owner, 'status' => 'DONE', 'priority' => 'high', 'est' => 180, 'completed' => now()],
                ['code' => 'AI-103', 'title' => 'Phát triển tính năng AI Report phân tích tiến độ dự án theo tuần', 'assignee' => $devB, 'reviewer' => $manager, 'status' => 'IN_PROGRESS', 'priority' => 'high', 'est' => 200, 'deadline' => now()->addDays(2)],
                ['code' => 'AI-104', 'title' => 'Xây dựng trợ lý phân tích blocker và đề xuất hướng xử lý kỹ thuật', 'assignee' => $extraUsers[4], 'reviewer' => $owner, 'status' => 'TODO', 'priority' => 'medium', 'est' => 180, 'deadline' => now()->addDays(6)],
                ['code' => 'AI-105', 'title' => 'Tối ưu hóa token tiêu thụ và cơ chế fallback model tự động', 'assignee' => $owner, 'reviewer' => $manager, 'status' => 'IN_PROGRESS', 'priority' => 'medium', 'est' => 150, 'deadline' => now()->addDays(4)],
            ];

            foreach ($aiTasks as $t) {
                $task = Task::firstOrCreate(
                    ['workspace_id' => $workspace->id, 'code' => $t['code']],
                    [
                        'project_id' => $pAi->id,
                        'parent_task_id' => $groupAiCore->id,
                        'task_type' => 'WORK',
                        'title' => $t['title'],
                        'description' => 'Triển khai tính năng AI: ' . $t['title'],
                        'created_by' => $owner->id,
                        'assignee_id' => $t['assignee']->id,
                        'reviewer_id' => $t['reviewer']->id,
                        'status' => $t['status'],
                        'priority' => $t['priority'],
                        'estimate_minutes' => $t['est'],
                        'deadline_at' => $t['deadline'] ?? now()->addDays(5),
                        'completed_at' => $t['completed'] ?? null,
                    ]
                );
                $createdTasks[] = $task;
            }

            // 6. Thêm Task Dependencies & Blockers
            if (count($createdTasks) >= 6) {
                // ECOM-104 phụ thuộc vào ECOM-101 và ECOM-102
                TaskDependency::firstOrCreate(
                    ['predecessor_task_id' => $createdTasks[0]->id, 'successor_task_id' => $createdTasks[3]->id],
                    ['dependency_type' => 'FS', 'lag_minutes' => 0]
                );

                // ECOM-104 có blocker
                TaskBlocker::firstOrCreate(
                    ['task_id' => $createdTasks[3]->id, 'reason' => 'Đang chờ cổng thanh toán duyệt hồ sơ tài khoản merchant môi trường sandbox.'],
                    ['reported_by' => $devA->id]
                );
            }

            // 7. Tạo chuỗi hoạt động TaskActivity (bình luận, trao đổi thật)
            $comments = [
                ['task' => $createdTasks[0], 'user' => $devA, 'content' => 'Đã chuẩn hóa các bảng orders, order_items và vouchers theo đúng chuẩn schema 3NF.'],
                ['task' => $createdTasks[0], 'user' => $owner, 'content' => 'Bản thiết kế rất tốt, đã approve để triển khai sang migration.'],
                ['task' => $createdTasks[3], 'user' => $devA, 'content' => 'Đã gửi thông tin đăng ký API sang bên VNPay, dự kiến nhận key test vào ngày mai.'],
                ['task' => $createdTasks[3], 'user' => $manager, 'content' => 'Mình đã nhắc bên đối tác ưu tiên duyệt sớm cho team.'],
                ['task' => $createdTasks[8], 'user' => $devB, 'content' => 'Giao diện chi tiết sản phẩm đã responsive mượt trên cả iPhone 15 và Samsung Galaxy.'],
                ['task' => $createdTasks[8], 'user' => $extraUsers[1], 'content' => 'Typography và khoảng cách padding rất chuẩn với Figma.'],
            ];

            foreach ($comments as $c) {
                TaskActivity::firstOrCreate(
                    ['task_id' => $c['task']->id, 'actor_id' => $c['user']->id, 'content' => $c['content']],
                    ['type' => 'COMMENT', 'source' => 'user']
                );
            }

            // 8. Tạo Calendar Events (Lịch họp & Lịch làm việc) phong phú
            $baseDate = CarbonImmutable::now('Asia/Ho_Chi_Minh')->startOfWeek();
            $events = [
                ['title' => 'Daily Standup - Báo cáo tiến độ đầu ngày', 'day' => 0, 'h_start' => 9, 'h_end' => 10, 'user' => $owner, 'proj' => $pEcom],
                ['title' => 'Họp Review Kiến trúc Cổng thanh toán', 'day' => 1, 'h_start' => 14, 'h_end' => 16, 'user' => $devA, 'proj' => $pEcom],
                ['title' => 'Sprint Planning - Kế hoạch Sprint tuần', 'day' => 2, 'h_start' => 10, 'h_end' => 12, 'user' => $manager, 'proj' => $pEcom],
                ['title' => 'UI/UX Demo & Thẩm định giao diện Mobile', 'day' => 3, 'h_start' => 15, 'h_end' => 17, 'user' => $devB, 'proj' => $pEcom],
                ['title' => 'Họp Thử nghiệm Tích hợp Gemini AI Assistant', 'day' => 4, 'h_start' => 9, 'h_end' => 11, 'user' => $owner, 'proj' => $pAi],
                ['title' => 'Sprint Retrospective & Đánh giá kết quả', 'day' => 4, 'h_start' => 16, 'h_end' => 17, 'user' => $manager, 'proj' => $pEcom],
            ];

            foreach ($events as $e) {
                $s = $baseDate->addDays($e['day'])->setHour($e['h_start'])->setMinute(0)->setSecond(0);
                $end = $baseDate->addDays($e['day'])->setHour($e['h_end'])->setMinute(0)->setSecond(0);

                CalendarEvent::firstOrCreate(
                    ['workspace_id' => $workspace->id, 'user_id' => $e['user']->id, 'title' => $e['title']],
                    [
                        'project_id' => $e['proj']->id,
                        'starts_at' => $s,
                        'ends_at' => $end,
                        'event_type' => 'NOTE',
                        'timezone' => 'Asia/Ho_Chi_Minh',
                        'visibility' => 'project',
                        'status' => 'planned',
                        'source' => 'manual',
                    ]
                );
            }

            // 9. Tạo Notifications sinh động cho người dùng chính (demo1)
            $notifs = [
                ['title' => 'Bạn được phân công làm reviewer cho task ECOM-104', 'body' => 'Lê Hoàng Long đã hoàn thành phần tích hợp và gửi yêu cầu review mã nguồn.'],
                ['title' => 'Công việc ECOM-302 sắp đến hạn xử lý', 'body' => 'Thời hạn hoàn thành cấu hình tự động sao lưu dữ liệu là trong 2 ngày tới.'],
                ['title' => 'Bình luận mới trong dự án E-Commerce', 'body' => 'Trần Thị Mai: "Mình đã nhắc bên đối tác ưu tiên duyệt sớm cho team."'],
                ['title' => 'Gemini AI đã hoàn thành tạo bản kế hoạch mới', 'body' => 'Bản kế hoạch đề xuất cho giai đoạn thử nghiệm AI đã sẵn sàng để kiểm duyệt.'],
            ];

            foreach ($notifs as $idx => $n) {
                Notification::firstOrCreate(
                    ['workspace_id' => $workspace->id, 'recipient_id' => $owner->id, 'dedupe_key' => 'rich_notif_' . $idx],
                    [
                        'project_id' => $pEcom->id,
                        'type' => 'task_assigned',
                        'title' => $n['title'],
                        'body' => $n['body'],
                        'read_at' => $idx % 2 === 0 ? null : now()->subHours(3),
                    ]
                );
            }

            // 10. Tạo Task Templates (Mẫu công việc chuẩn)
            TaskTemplate::firstOrCreate(
                ['workspace_id' => $workspace->id, 'name' => 'Quy trình Phát triển Tính năng mới (Feature Workflow)'],
                [
                    'project_id' => $pEcom->id,
                    'created_by' => $owner->id,
                    'timezone' => 'Asia/Ho_Chi_Minh',
                    'status' => 'active',
                    'definition' => [
                        'tasks' => [
                            ['title' => 'Khảo sát yêu cầu & Đặc tả tính năng', 'estimate' => 120, 'priority' => 'high'],
                            ['title' => 'Thiết kế giao diện UI Mockup', 'estimate' => 180, 'priority' => 'medium'],
                            ['title' => 'Lập trình tính năng (Code & Unit Test)', 'estimate' => 360, 'priority' => 'high'],
                            ['title' => 'Kiểm thử chấp nhận người dùng (UAT)', 'estimate' => 120, 'priority' => 'medium'],
                        ],
                    ],
                ]
            );
        });
    }
}
