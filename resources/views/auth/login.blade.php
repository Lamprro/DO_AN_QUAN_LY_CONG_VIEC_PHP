<x-layout title="Đăng nhập"><section class="card narrow" data-page="login"><h1>Đăng nhập</h1>
<form action="{{ route('login.store') }}" method="POST" data-form="login">@csrf
<x-input name="email" label="Email" type="email" required autocomplete="username" />
<x-input name="password" label="Mật khẩu" type="password" required autocomplete="current-password" />
<p><label><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Ghi nhớ đăng nhập</label></p>
<div class="actions"><button type="submit">Đăng nhập</button><a href="{{ route('register') }}">Tạo tài khoản</a></div></form></section></x-layout>
