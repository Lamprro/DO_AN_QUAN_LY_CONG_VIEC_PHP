<x-layout title="419 — Phiên làm việc đã hết hạn">
<div style="max-width: 500px; margin: 4rem auto; text-align: center;">
    <section class="card" data-page="error-419" style="padding: 2.5rem 2rem;">
        <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(99, 102, 241, 0.1); color: var(--color-primary); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 0.5rem; color: var(--color-text);">419 — Phiên đã hết hạn</h1>
        <p style="color: var(--color-text-muted); font-size: 0.9375rem; margin-bottom: 1.5rem; line-height: 1.5;">
            Mã xác thực bảo vệ CSRF đã hết hiệu lực do bạn không thao tác trong thời gian dài. Vui lòng tải lại trang để tiếp tục.
        </p>
        <div class="actions" style="display: flex; gap: 0.75rem; justify-content: center;">
            <button onclick="window.location.reload();" class="button" style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.625rem 1.25rem;">
                ↻ Tải lại trang
            </button>
            <a class="button secondary" href="{{ route('home') }}">
                Về trang chủ
            </a>
        </div>
    </section>
</div>
</x-layout>
