@php($mailLocale = app()->getLocale() === 'en' ? 'en' : 'id')
@if ($mailLocale === 'en')
    <h1>Welcome to {{ config('app.name') }}</h1>
    <p>Hello {{ $user->name }}, your account is ready to use.</p>
@else
    <h1>Selamat datang di {{ config('app.name') }}</h1>
    <p>Halo {{ $user->name }}, akun Anda siap digunakan.</p>
@endif
