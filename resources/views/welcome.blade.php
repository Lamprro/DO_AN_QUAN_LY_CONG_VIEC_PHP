<!DOCTYPE html>
<html lang="vi" data-theme="system">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Work Management · Quản lý Công việc & Tiến độ Dự án Thông minh</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app-shell.css') }}">
    <link rel="stylesheet" href="{{ asset('css/p0-ui.css') }}">
    <link rel="stylesheet" href="{{ asset('css/p1-observation.css') }}">
    <link rel="stylesheet" href="{{ asset('css/modern-theme.css') }}">
    <style>
        .hero-section {
            padding: 4.5rem 1.5rem 3.5rem;
            text-align: center;
            max-width: 1000px;
            margin: 0 auto;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.85rem;
            background: var(--primary-light);
            color: var(--primary);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: var(--radius-full);
            font-size: 0.825rem;
            font-weight: 750;
            margin-bottom: 1.5rem;
        }
        .hero-title {
            font-size: 3rem;
            font-weight: 850;
            line-height: 1.15;
            letter-spacing: -0.035em;
            margin-bottom: 1.25rem;
            background: linear-gradient(135deg, var(--text) 30%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-sub {
            font-size: 1.15rem;
            color: var(--text-muted);
            max-width: 720px;
            margin: 0 auto 2rem;
            line-height: 1.6;
        }
        .feature-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.75rem;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }
        .feature-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            border-color: var(--border-focus);
        }
        .feature-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: var(--radius-md);
            background: var(--primary-light);
            color: var(--primary);
            font-size: 1.35rem;
            margin-bottom: 1rem;
        }
        @media (max-width: 768px) {
            .hero-title { font-size: 2.15rem; }
            .hero-section { padding: 2.5rem 1rem 2rem; }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <a href="{{ url('/') }}" class="brand">Work Management</a>
        <nav aria-label="Tài khoản" class="account-nav">
            @auth
                <a href="{{ route('workspaces.index') }}" class="button" style="min-height: 36px; padding: 0.4rem 0.85rem;">
                    Vào Workspace ›
                </a>
            @else
                <a href="{{ route('login') }}" style="color: #fff !important; font-weight: 600;">Đăng nhập</a>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="button" style="min-height: 34px; padding: 0.35rem 0.85rem; font-size: 0.85rem;">
                        Đăng ký ngay
                    </a>
                @endif
            @endauth
        </nav>
    </header>

    <main id="main-content">
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="hero-badge">
                <span>✦</span> Hệ Thống Quản Lý Công Việc & Tiến Độ Dự Án Laravel MVC V3
            </div>
            <h1 class="hero-title">
                Lập Kế Hoạch Thông Minh & Tối Ưu Hóa Năng Suất Dự Án
            </h1>
            <p class="hero-sub">
                Giải pháp toàn diện từ cấu trúc công việc 3 tầng (GROUP - WORK - MILESTONE), timeline trực quan, ghi nhận giờ làm thực tế đến phân tích KPI điều hành thời gian thực.
            </p>

            <div class="actions" style="justify-content: center; margin-bottom: 2rem;">
                @auth
                    <a href="{{ route('workspaces.index') }}" class="button" style="font-size: 1rem; padding: 0.75rem 1.75rem;">
                        Truy cập Dashboard của bạn ›
                    </a>
                @else
                    <a href="{{ route('login') }}" class="button" style="font-size: 1rem; padding: 0.75rem 1.75rem;">
                        ⚡ Bắt đầu đăng nhập ngay
                    </a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="button secondary" style="font-size: 1rem; padding: 0.75rem 1.75rem;">
                            Tạo tài khoản mới
                        </a>
                    @endif
                @endauth
            </div>

            @if(app()->environment('local'))
            <!-- Demo quick access box -->
            <div class="demo-helper-box" style="max-width: 540px; margin: 0 auto; text-align: left;">
                <p>⚡ Tài khoản trải nghiệm nhanh (Dành cho Giảng viên & Ban Đánh Giá):</p>
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                    <div>
                        Email: <code>demo1@example.test</code> · Mật khẩu: <code>Demo@123456</code>
                    </div>
                    <a href="{{ route('login') }}" class="button" style="font-size: 0.8rem; padding: 0.3rem 0.75rem; min-height: 32px;">
                        Đăng nhập ngay
                    </a>
                </div>
            </div>
            @endif
        </section>

        <!-- Feature Grid Section -->
        <section style="max-width: 1200px; margin: 0 auto 4rem; padding: 0 1.5rem;">
            <div style="text-align: center; margin-bottom: 2.5rem;">
                <p class="eyebrow">TÍNH NĂNG VƯỢT TRỘI</p>
                <h2 style="font-size: 2rem;">Thiết kế cho hiệu suất & độ chính xác cao</h2>
                <p class="muted">Tích hợp đầy đủ các tiêu chuẩn nghiệp vụ từ cơ sở dữ liệu đến giao diện trực quan</p>
            </div>

            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr)) !important;">
                <article class="feature-card">
                    <div class="feature-icon">🎯</div>
                    <h3>Cấu trúc công việc 3 tầng</h3>
                    <p class="muted">Phân rã linh hoạt giữa Nhóm việc (GROUP), Công việc thực thi (WORK) và Mốc quan trọng (MILESTONE). Tự động tổng hợp tiến độ từ các task WORK lá.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon">⏱️</div>
                    <h3>Ghi nhận giờ thực tế (Timer)</h3>
                    <p class="muted">Bộ đếm thời gian live chính xác từng giây với cơ chế bảo vệ phân quyền, audit lịch sử điều chỉnh giờ và đối chiếu trực tiếp giữa dự kiến và thực tế.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon">📅</div>
                    <h3>Lịch & Timeline đa chiều</h3>
                    <p class="muted">Theo dõi lịch trình theo Ngày, Tuần, Tháng, Danh sách và Timeline. Tự động kiểm tra xung đột khung giờ bận và bảo vệ quyền riêng tư cá nhân.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon">🔄</div>
                    <h3>Lập kế hoạch & So sánh phiên bản</h3>
                    <p class="muted">Trình soạn kế hoạch trực quan, lưu trữ version lineage, xem trước (preview) tác động và so sánh diff chi tiết trước khi áp dụng vào dữ liệu thực tế.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon">🛡️</div>
                    <h3>Quy trình duyệt & Xử lý Blocker</h3>
                    <p class="muted">Cơ chế State Machine nghiêm ngặt (TODO → IN_PROGRESS → REVIEW → DONE) kèm phân quyền Reviewer, quản lý phụ thuộc FS/lag và báo cáo điểm nghẽn.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>Dashboard & Báo cáo điều hành</h3>
                    <p class="muted">KPI thời gian thực, bảng phân bổ trạng thái, đo lường khối lượng công việc từng thành viên và hỗ trợ lưu snapshot cùng xuất dữ liệu CSV an toàn.</p>
                </article>
            </div>
        </section>

        <!-- System Architecture Highlights -->
        <section class="card" style="max-width: 1200px; margin: 0 auto 3rem; background: var(--surface) !important; border-top: 4px solid var(--primary); padding: 2rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr)); gap: 1.5rem; text-align: center;">
                <div>
                    <strong style="font-size: 2rem; color: var(--primary); font-family: var(--font-mono);">Laravel 12</strong>
                    <p class="muted" style="margin-top: 0.25rem;">Kiến trúc MVC chuẩn mực, Blade Components</p>
                </div>
                <div>
                    <strong style="font-size: 2rem; color: var(--success); font-family: var(--font-mono);">61 FK / UUID</strong>
                    <p class="muted" style="margin-top: 0.25rem;">20 Bảng nghiệp vụ theo chuẩn Schema V3</p>
                </div>
                <div>
                    <strong style="font-size: 2rem; color: #8b5cf6; font-family: var(--font-mono);">Zero Data Loss</strong>
                    <p class="muted" style="margin-top: 0.25rem;">Kiểm soát xung đột phiên bản và chống ghi đè</p>
                </div>
                <div>
                    <strong style="font-size: 2rem; color: #0284c7; font-family: var(--font-mono);">100% Verified</strong>
                    <p class="muted" style="margin-top: 0.25rem;">Xác thực bằng dữ liệu và luồng chạy thực tế</p>
                </div>
            </div>
        </section>

        <footer>
            <p style="margin: 0;">Đồ án Quản lý Công việc & Tiến độ Dự án · Xây dựng trên nền tảng Laravel MVC V3</p>
            <p class="muted" style="font-size: 0.75rem; margin-top: 0.25rem;">Hỗ trợ giao diện Responsive, Dark Mode và khả năng truy cập tiêu chuẩn cao.</p>
        </footer>
    </main>
</body>
</html>
