@extends('account.layout')
@section('title', 'Profile & Password')

@section('account-content')
<div>
    <h2 class="fw-bold mb-1">Profile & Password</h2>
    <p class="text-muted mb-4">Manage your personal details and account security.</p>

    {{-- Flash messages --}}
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="row g-4">

        {{-- Profile info --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">
                    <i class="fas fa-user me-2 text-primary"></i>Personal Information
                </div>
                <div class="card-body">
                    @if($errors->has('name') || $errors->has('email'))
                    <div class="alert alert-danger">
                        <ul class="mb-0 ps-3">
                            @foreach($errors->only(['name','email']) as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                    <form method="POST" action="{{ route('account.profile.update') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Full Name</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $user->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                value="{{ old('email', $user->email) }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Change password --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">
                    <i class="fas fa-lock me-2 text-warning"></i>Change Password
                </div>
                <div class="card-body">
                    @if($errors->has('current_password') || $errors->has('password'))
                    <div class="alert alert-danger">
                        <ul class="mb-0 ps-3">
                            @foreach($errors->only(['current_password','password']) as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                    <form method="POST" action="{{ route('account.password.update') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Current Password</label>
                            <input type="password" name="current_password"
                                class="form-control @error('current_password') is-invalid @enderror"
                                required placeholder="Enter your current password">
                            @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">New Password</label>
                            <input type="password" name="password"
                                class="form-control @error('password') is-invalid @enderror"
                                required placeholder="Min. 8 characters">
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Confirm New Password</label>
                            <input type="password" name="password_confirmation"
                                class="form-control" required placeholder="Repeat new password">
                        </div>
                        <button type="submit" class="btn btn-warning fw-semibold">
                            <i class="fas fa-key me-1"></i>Update Password
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
