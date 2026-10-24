<!doctype html><html lang="vi"><meta charset="utf-8"><body>
<h1>{{ $heading }}</h1>
<p>Yêu cầu này dành cho tài khoản của bạn tại Work Management. Liên kết có hiệu lực trong {{ $minutes }} phút.</p>
<p><a href="{{ $url }}">{{ $heading }}</a></p>
@if($kind === 'reset')<p>Sau khi đổi mật khẩu, các phiên đăng nhập cũ sẽ kết thúc. Nếu không yêu cầu đổi mật khẩu, bạn có thể bỏ qua thư này.</p>
@else<p>Mở liên kết khi đang đăng nhập đúng tài khoản. Nếu không tạo tài khoản này, bạn có thể bỏ qua thư này.</p>@endif
<p>Không chuyển tiếp liên kết này cho người khác.</p></body></html>
