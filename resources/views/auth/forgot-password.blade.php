<x-layout title="Quên mật khẩu">
<section class="card narrow" data-page="forgot-password"><h1>Quên mật khẩu</h1>
<p>Nhập email đã đăng ký. Chúng tôi sẽ gửi liên kết để bạn đặt mật khẩu mới.</p>
<form method="post" action="{{ route('password.email') }}" data-form="forgot-password">@csrf
<x-input name="email" type="email" label="Email đã đăng ký" required autocomplete="username" maxlength="255" />
<div class="actions"><button>Gửi liên kết đặt lại</button><a href="{{ route('login') }}">Quay lại đăng nhập</a></div>
</form></section></x-layout>
