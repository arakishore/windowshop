{{-- Purpose: Merchant web login screen using the shared authentication layout. --}}
@extends('layouts.auth')

@section('title', 'Merchant Login | WindowShop')

@section('content')
    <form class="login-form" method="POST" action="{{ route('merchant.authenticate') }}">
        @csrf
        <div class="card mb-0">
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="d-inline-flex align-items-center justify-content-center mb-4 mt-2">
                        <img src="{{ asset('assets/admin/images/logov2.png') }}" class="h-48px" alt="WindowShop">
                    </div>
                    <h5 class="mb-0">Merchant login</h5>
                    <span class="d-block text-muted">Access your WindowShop merchant panel</span>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        {{ $errors->first() }}
                    </div>
                @endif

                @if (session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="mb-3">
                    <label for="login" class="form-label">Email or mobile</label>
                    <div class="form-control-feedback form-control-feedback-start">
                        <input id="login" type="text" name="login" value="{{ old('login') }}" class="form-control @error('login') is-invalid @enderror" placeholder="merchant@example.com or mobile number" autocomplete="username" required autofocus>
                        <div class="form-control-feedback-icon">
                            <i class="ph-user-circle text-muted"></i>
                        </div>
                    </div>
                    @error('login')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="form-control-feedback form-control-feedback-start">
                        <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="Password" autocomplete="current-password" required>
                        <div class="form-control-feedback-icon">
                            <i class="ph-lock text-muted"></i>
                        </div>
                    </div>
                    @error('password')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex align-items-center justify-content-between mb-3">
                    <label class="form-check mb-0">
                        <input type="checkbox" name="remember" value="1" class="form-check-input" @checked(old('remember'))>
                        <span class="form-check-label">Remember me</span>
                    </label>
                </div>

                <div class="mb-3">
                    <button type="submit" class="btn btn-primary w-100">Sign in</button>
                </div>
            </div>
        </div>
    </form>
@endsection
