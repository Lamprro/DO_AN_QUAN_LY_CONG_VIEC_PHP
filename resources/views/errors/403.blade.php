<x-layout title="403 — Không có quyền truy cập">
<div style="max-width: 500px; margin: 4rem auto; text-align: center;">
    <section class="card" data-page="error-403" style="padding: 2.5rem 2rem;">
        <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(239, 68, 68, 0.1); color: #dc2626; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </div>
        <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 0.5rem; color: var(--color-text);">403 — Quyền truy cập bị từ chối</h1>
        <p style="color: var(--color-text-muted); font-size: 0.9375rem; margin-bottom: 1.5rem; line-height: 1.5;">
            Bạn không có đủ quyền hạn để thực hiện thao tác hoặc truy cập vào tài nguyên này.
        </p>
        <a class="button" href="{{ route('home') }}" style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.625rem 1.25rem;">
            Về trang chủ
        </a>
    </section>
</div>
</x-layout>
