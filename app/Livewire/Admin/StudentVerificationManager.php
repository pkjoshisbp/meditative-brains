<?php

namespace App\Livewire\Admin;

use App\Livewire\AdminComponent;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;

class StudentVerificationManager extends AdminComponent
{
    use WithPagination;

    protected string $pageTitle = 'Student Verification';
    protected string $pageHeader = 'Student Verification';

    public string $search = '';
    public string $status = 'pending';
    public bool $showReviewModal = false;
    public ?int $selectedUserId = null;
    public string $reviewNotes = '';

    protected $queryString = ['search', 'status'];
    protected $paginationTheme = 'bootstrap';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function openReview(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->selectedUserId = $user->id;
        $this->reviewNotes = (string) ($user->student_review_notes ?? '');
        $this->showReviewModal = true;
        $this->resetErrorBag();
    }

    public function closeReview(): void
    {
        $this->showReviewModal = false;
        $this->selectedUserId = null;
        $this->reviewNotes = '';
    }

    public function approve(): void
    {
        $this->updateStudentStatus('approved');
    }

    public function reject(): void
    {
        $this->validate([
            'reviewNotes' => 'required|string|min:5|max:1000',
        ]);

        $this->updateStudentStatus('rejected');
    }

    public function expireSubmission(): void
    {
        $this->updateStudentStatus('expired');
    }

    protected function updateStudentStatus(string $status): void
    {
        $user = User::findOrFail($this->selectedUserId);

        $user->update([
            'student_status' => $status,
            'student_verified_at' => $status === 'approved' ? now() : null,
            'student_reviewed_at' => now(),
            'student_reviewed_by' => Auth::id(),
            'student_review_notes' => $this->reviewNotes !== '' ? $this->reviewNotes : null,
            'student_expires_at' => $status === 'approved' ? null : $user->student_expires_at,
        ]);

        $this->closeReview();
        session()->flash('success', "Student verification marked as {$status} for {$user->name}.");
    }

    protected function getViewData(): array
    {
        $query = User::query()
            ->where(function ($builder) {
                $builder->whereNotNull('student_institution')
                    ->orWhereNotNull('student_document_path')
                    ->orWhereIn('student_status', ['pending', 'approved', 'rejected', 'expired']);
            })
            ->when($this->status !== 'all', fn ($builder) => $builder->where('student_status', $this->status))
            ->when($this->search !== '', function ($builder) {
                $builder->where(function ($inner) {
                    $inner->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%')
                        ->orWhere('student_institution', 'like', '%' . $this->search . '%')
                        ->orWhere('student_id_number', 'like', '%' . $this->search . '%');
                });
            })
            ->latest('updated_at');

        return [
            'students' => $query->paginate(15),
            'selectedUser' => $this->selectedUserId ? User::find($this->selectedUserId) : null,
            'stats' => [
                'pending' => User::where('student_status', 'pending')->count(),
                'approved' => User::where('student_status', 'approved')->count(),
                'rejected' => User::where('student_status', 'rejected')->count(),
                'expired' => User::where('student_status', 'expired')->count(),
            ],
        ];
    }
}