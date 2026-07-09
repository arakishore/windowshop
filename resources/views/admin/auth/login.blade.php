{{-- Purpose: Login screen scaffold that uses the auth layout without admin navbar or sidebar. --}}
@extends('layouts.auth')

@section('title', 'Login | WindowShop')

@section('content')
    <!-- Login form -->
    <form class="login-form" method="POST" action="{{ route('admin.authenticate') }}">
        @csrf
        <div class="card mb-0">
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="d-inline-flex align-items-center justify-content-center mb-4 mt-2">
                        <img src="{{ asset('assets/admin/images/logov2.png') }}" class="h-48px" alt="WindowShop">
                    </div>
                    <h5 class="mb-0">Login to your account</h5>
                    <span class="d-block text-muted">Enter your credentials below</span>
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
                    <label for="email" class="form-label">Email</label>
                    <div class="form-control-feedback form-control-feedback-start">
                        <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" placeholder="admin@example.com" autocomplete="email" required autofocus>
                        <div class="form-control-feedback-icon">
                            <i class="ph-user-circle text-muted"></i>
                        </div>
                    </div>
                    @error('email')
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
                <div class="mb-3">
                    <button type="submit" class="btn btn-primary w-100">Sign in</button>
                </div>
            </div>
        </div>
    </form>
    <!-- /login form -->
@endsection
