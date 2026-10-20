<x-layout title="429 — Thao tác quá nhanh">
<div style="max-width: 500px; margin: 4rem auto; text-align: center;">
    <section class="card" data-page="error-429" style="padding: 2.5rem 2rem;">
        <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(245, 158, 11, 0.1); color: #d97706; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 0.5rem; color: var(--color-text);">429 — Thao tác quá nhanh</h1>
        <p style="color: var(--color-text-muted); font-size: 0.9375rem; margin-bottom: 1.5rem; line-height: 1.5;">
            Hệ thống nhận thấy số lượng yêu cầu vượt quá giới hạn cho phép (Rate Limit). Vui lòng tạm dừng trong giây lát trước khi thử lại.
        </p>
        <a class="button" href="{{ route('home') }}" style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.625rem 1.25rem;">
            Về trang chủ
        </a>
    </section>
</div>
</x-layout>
