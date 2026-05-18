<div>
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row mb-4">
        <div class="col-6 col-lg-3">
            <div class="info-box bg-warning">
                <span class="info-box-icon"><i class="fas fa-hourglass-half"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Pending</span>
                    <span class="info-box-number">{{ $stats['pending'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="info-box bg-success">
                <span class="info-box-icon"><i class="fas fa-user-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Approved</span>
                    <span class="info-box-number">{{ $stats['approved'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="info-box bg-danger">
                <span class="info-box-icon"><i class="fas fa-user-times"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Rejected</span>
                    <span class="info-box-number">{{ $stats['rejected'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="info-box bg-secondary">
                <span class="info-box-icon"><i class="fas fa-clock"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Expired</span>
                    <span class="info-box-number">{{ $stats['expired'] }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-primary mb-3">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-8">
                    <input type="text" wire:model.live.debounce.400ms="search" class="form-control"
                        placeholder="Search by name, email, institution, or student ID...">
                </div>
                <div class="col-md-3">
                    <select wire:model.live="status" class="form-select">
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="expired">Expired</option>
                        <option value="all">All statuses</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button wire:click="$set('search', '')" class="btn btn-secondary w-100">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Institution</th>
                            <th>Status</th>
                            <th>Document</th>
                            <th>Deadline</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($students as $student)
                            <tr>
                                <td>{{ $student->id }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $student->name }}</div>
                                    <div class="small text-muted">{{ $student->email }}</div>
                                    @if($student->student_id_number)
                                        <div class="small text-muted">ID: {{ $student->student_id_number }}</div>
                                    @endif
                                </td>
                                <td>{{ $student->student_institution ?: '—' }}</td>
                                <td>
                                    @php
                                        $statusClass = match ($student->student_status) {
                                            'approved' => 'success',
                                            'pending' => 'warning text-dark',
                                            'rejected' => 'danger',
                                            'expired' => 'secondary',
                                            default => 'light text-dark',
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $statusClass }}">{{ $student->studentStatusLabel() }}</span>
                                </td>
                                <td>
                                    @if($student->studentDocumentUrl())
                                        <a href="{{ $student->studentDocumentUrl() }}" class="btn btn-xs btn-outline-secondary" target="_blank">
                                            <i class="fas fa-file-alt me-1"></i>Open
                                        </a>
                                    @else
                                        <span class="text-muted small">Not uploaded</span>
                                    @endif
                                </td>
                                <td>
                                    @if($student->student_expires_at)
                                        <div>{{ $student->student_expires_at->format('d M Y') }}</div>
                                        <div class="small text-muted">{{ $student->student_expires_at->format('H:i') }}</div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <button wire:click="openReview({{ $student->id }})" class="btn btn-xs btn-primary">
                                        <i class="fas fa-search me-1"></i>Review
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-user-graduate fa-2x mb-2 d-block"></i>
                                    No student verification records found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($students->hasPages())
            <div class="card-footer">
                {{ $students->links() }}
            </div>
        @endif
    </div>

    @if($showReviewModal && $selectedUser)
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-user-graduate me-2"></i>{{ $selectedUser->name }}</h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="closeReview"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-4 mb-3">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr><td class="fw-bold">Email</td><td>{{ $selectedUser->email }}</td></tr>
                                    <tr><td class="fw-bold">Institution</td><td>{{ $selectedUser->student_institution ?: '—' }}</td></tr>
                                    <tr><td class="fw-bold">Student ID</td><td>{{ $selectedUser->student_id_number ?: '—' }}</td></tr>
                                    <tr><td class="fw-bold">Status</td><td>{{ $selectedUser->studentStatusLabel() }}</td></tr>
                                    <tr><td class="fw-bold">Submitted</td><td>{{ optional($selectedUser->student_document_uploaded_at)->format('d M Y, H:i') ?: 'No document yet' }}</td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded p-3 bg-light h-100">
                                    <div class="fw-semibold mb-2">Student document</div>
                                    @if($selectedUser->studentDocumentUrl())
                                        <a href="{{ $selectedUser->studentDocumentUrl() }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                                            <i class="fas fa-file-alt me-1"></i>Open Uploaded Document
                                        </a>
                                    @else
                                        <div class="text-muted small">No document uploaded yet.</div>
                                    @endif
                                    @if($selectedUser->student_expires_at)
                                        <div class="small text-muted mt-3">Pending pricing deadline: {{ $selectedUser->student_expires_at->format('d M Y, H:i') }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="form-label fw-semibold">Review notes</label>
                            <textarea wire:model="reviewNotes" rows="4" class="form-control @error('reviewNotes') is-invalid @enderror"
                                placeholder="Internal notes or reason for rejection..."></textarea>
                            @error('reviewNotes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between flex-wrap gap-2">
                        <div class="d-flex gap-2">
                            <button class="btn btn-success" wire:click="approve">
                                <i class="fas fa-check me-1"></i>Approve
                            </button>
                            <button class="btn btn-danger" wire:click="reject">
                                <i class="fas fa-times me-1"></i>Reject
                            </button>
                            <button class="btn btn-secondary" wire:click="expireSubmission">
                                <i class="fas fa-clock me-1"></i>Mark Expired
                            </button>
                        </div>
                        <button class="btn btn-outline-secondary" wire:click="closeReview">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>