@extends('account.layout')
@section('title', 'Profile & Password')

@section('account-content')
<div>
    <h2 class="fw-bold mb-1">Profile & Password</h2>
    <p class="text-muted mb-4">Manage your personal details, account security, and student verification.</p>

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

    <div class="row g-4 mt-1">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-id-card me-2 text-success"></i>Student Verification</span>
                    @php
                        $statusClasses = [
                            'approved' => 'success',
                            'pending' => 'warning text-dark',
                            'rejected' => 'danger',
                            'expired' => 'secondary',
                            'none' => 'light text-dark',
                        ];
                        $statusClass = $statusClasses[$user->student_status] ?? 'light text-dark';
                    @endphp
                    <span class="badge bg-{{ $statusClass }}">{{ $user->studentStatusLabel() }}</span>
                </div>
                <div class="card-body">
                    @if($errors->has('student_institution') || $errors->has('student_id_number') || $errors->has('student_document'))
                    <div class="alert alert-danger">
                        <ul class="mb-0 ps-3">
                            @foreach($errors->only(['student_institution','student_id_number','student_document']) as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    @if($user->student_status === 'pending' && $user->student_expires_at)
                    <div class="alert alert-warning">
                        <strong>Pending review.</strong> Student pricing stays active until {{ $user->student_expires_at->format('d M Y, H:i') }} while your documents are reviewed.
                    </div>
                    @elseif($user->student_status === 'approved')
                    <div class="alert alert-success">
                        <strong>Approved.</strong> Student pricing is active on eligible products and subscription plans.
                    </div>
                    @elseif($user->student_status === 'rejected')
                    <div class="alert alert-danger">
                        <strong>Review declined.</strong>
                        @if($user->student_review_notes)
                            {{ $user->student_review_notes }}
                        @else
                            Please update your details and resubmit.
                        @endif
                    </div>
                    @elseif($user->student_status === 'expired')
                    <div class="alert alert-secondary">
                        <strong>Submission expired.</strong> Re-submit your student details to restore student pricing.
                    </div>
                    @endif

                    <form method="POST" action="{{ route('account.profile.student-verification') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Institution</label>
                                <input type="text" name="student_institution" class="form-control @error('student_institution') is-invalid @enderror"
                                    value="{{ old('student_institution', $user->student_institution) }}" required>
                                @error('student_institution')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Student ID / Registration Number</label>
                                <input type="text" name="student_id_number" class="form-control @error('student_id_number') is-invalid @enderror"
                                    value="{{ old('student_id_number', $user->student_id_number) }}" required>
                                @error('student_id_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Student Document</label>
                                <input type="file" name="student_document" class="form-control @error('student_document') is-invalid @enderror" accept=".jpg,.jpeg,.png,.pdf">
                                <div class="form-text">You can submit this now or upload it later from here. Accepted: JPG, PNG, PDF. Max 10 MB.</div>
                                @error('student_document')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            @if($user->studentDocumentUrl())
                            <div class="col-12">
                                <div class="border rounded p-3 bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <div class="fw-semibold">Current document on file</div>
                                        <div class="small text-muted">
                                            Uploaded {{ optional($user->student_document_uploaded_at)->format('d M Y, H:i') ?: 'recently' }}
                                        </div>
                                    </div>
                                    <a href="{{ $user->studentDocumentUrl() }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-file-alt me-1"></i>Open Document
                                    </a>
                                </div>
                            </div>
                            @endif
                        </div>

                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4">
                            <div class="small text-muted">
                                Submitting this form activates student pricing immediately while your verification is pending review.
                            </div>
                            <button type="submit" class="btn btn-success fw-semibold">
                                <i class="fas fa-paper-plane me-1"></i>Submit Student Verification
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
