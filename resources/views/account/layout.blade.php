@extends('layouts.app-frontend')

@section('title', ($title ?? 'My Account') . ' — Mental Fitness Store')

@section('content')
<div class="container py-5" style="min-height:70vh;">
    <div class="row g-4">

        {{-- Sidebar --}}
        <div class="col-lg-3">
            <div class="card shadow-sm border-0 sticky-top" style="top:90px;">
                <div class="card-body p-0">
                    {{-- User info --}}
                    <div class="p-4 text-center border-bottom" style="background:linear-gradient(135deg,#0f172a,#1e3a5f);">
                        <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle"
                            style="width:64px;height:64px;background:linear-gradient(135deg,#059669,#0891b2);font-size:1.6rem;color:#fff;font-weight:700;">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>
                        <div class="fw-bold text-white">{{ Auth::user()->name }}</div>
                        <div class="small" style="color:#94a3b8;">{{ Auth::user()->email }}</div>
                    </div>
                    {{-- Nav links --}}
                    <nav class="nav flex-column p-2">
                        <a href="{{ route('account.dashboard') }}"
                            class="nav-link d-flex align-items-center gap-2 px-3 py-2 rounded {{ request()->routeIs('account.dashboard') ? 'bg-primary text-white' : 'text-dark' }}">
                            <i class="fas fa-home fa-fw"></i> Dashboard
                        </a>
                        <a href="{{ route('account.library') }}"
                            class="nav-link d-flex align-items-center gap-2 px-3 py-2 rounded {{ request()->routeIs('account.library') ? 'bg-primary text-white' : 'text-dark' }}">
                            <i class="fas fa-headphones fa-fw"></i> My Library
                        </a>
                        <a href="{{ route('account.orders') }}"
                            class="nav-link d-flex align-items-center gap-2 px-3 py-2 rounded {{ request()->routeIs('account.orders') ? 'bg-primary text-white' : 'text-dark' }}">
                            <i class="fas fa-receipt fa-fw"></i> My Orders
                        </a>
                        <a href="{{ route('account.profile') }}"
                            class="nav-link d-flex align-items-center gap-2 px-3 py-2 rounded {{ request()->routeIs('account.profile') ? 'bg-primary text-white' : 'text-dark' }}">
                            <i class="fas fa-user-edit fa-fw"></i> Profile & Password
                        </a>
                        <hr class="my-2">
                        <a href="{{ route('home') }}"
                            class="nav-link d-flex align-items-center gap-2 px-3 py-2 rounded text-dark">
                            <i class="fas fa-store fa-fw"></i> Browse Products
                        </a>
                        <a href="{{ route('logout') }}"
                            class="nav-link d-flex align-items-center gap-2 px-3 py-2 rounded text-danger"
                            onclick="event.preventDefault(); document.getElementById('account-logout-form').submit();">
                            <i class="fas fa-sign-out-alt fa-fw"></i> Logout
                        </a>
                        <form id="account-logout-form" action="{{ route('logout') }}" method="POST" class="d-none">@csrf</form>
                    </nav>
                </div>
            </div>
        </div>

        {{-- Main content --}}
        <div class="col-lg-9">
            @yield('account-content')
        </div>
    </div>
</div>
@endsection
