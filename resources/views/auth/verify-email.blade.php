<x-layout title="Xác minh email">
<section class="card narrow" data-page="verify-email"><h1>Xác minh email của bạn</h1>
<p>Để bảo vệ tài khoản và lời mời vào nhóm, hãy mở liên kết trong thư gửi đến <strong>{{ auth()->user()->email }}</strong>.</p>
<ol><li>Kiểm tra hộp thư đến và thư rác.</li><li>Mở liên kết trên trình duyệt đang đăng nhập tài khoản này.</li><li>Tiếp tục vào workspace để tạo dự án và công việc.</li></ol>
<form method="post" action="{{ route('verification.send') }}" data-form="verification-send">@csrf
<div class="actions"><button>Gửi lại thư xác minh</button><a href="{{ route('profile.edit') }}">Sửa email trong hồ sơ</a></div>
</form><p class="muted">Liên kết hết hạn sau 60 phút. Có thể gửi lại tối đa 3 lần mỗi phút.</p></section></x-layout>
