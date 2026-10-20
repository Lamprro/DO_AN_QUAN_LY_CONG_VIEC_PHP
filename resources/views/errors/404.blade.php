<x-layout title="404 — Không tìm thấy">
<div style="max-width: 500px; margin: 4rem auto; text-align: center;">
    <section class="card" data-page="error-404" style="padding: 2.5rem 2rem;">
        <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(99, 102, 241, 0.1); color: var(--color-primary); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M16 16s-1.5-2-4-2-4 2-4 2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
        </div>
        <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 0.5rem; color: var(--color-text);">404 — Trang không tồn tại</h1>
        <p style="color: var(--color-text-muted); font-size: 0.9375rem; margin-bottom: 1.5rem; line-height: 1.5;">
            Dữ liệu hoặc đường dẫn bạn đang tìm kiếm không tồn tại hoặc đã bị di chuyển.
        </p>
        <a class="button" href="{{ route('home') }}" style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.625rem 1.25rem;">
            Về trang chủ
        </a>
    </section>
</div>
</x-layout>
