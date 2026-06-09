@extends('layouts.storefront')
@section('title', 'Social Login')
@section('content')
<div class="container" style="max-width:440px;">
    <h4 class="fw-bold mb-4 text-center">Masuk / Daftar</h4>
    <a href="{{ route('social.redirect', 'google') }}" class="btn btn-outline-danger w-100 mb-2 btn-lg"><i class="fab fa-google me-2"></i> Lanjutkan dengan Google</a>
    <a href="{{ route('social.redirect', 'facebook') }}" class="btn btn-outline-primary w-100 mb-3 btn-lg"><i class="fab fa-facebook me-2"></i> Lanjutkan dengan Facebook</a>
    <div class="text-center text-muted small mb-3">atau</div>
    <a href="{{ route('login') }}" class="btn btn-outline-secondary w-100">Login dengan Email</a>
</div>
@endsection
