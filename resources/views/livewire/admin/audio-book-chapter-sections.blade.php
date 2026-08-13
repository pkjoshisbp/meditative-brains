<div>
    <div class="card card-primary card-outline mb-3">
        <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap: .75rem;">
                <div>
                    <div class="text-muted small text-uppercase">Audiobook Section Editor</div>
                    <h3 class="mb-1">{{ $bookTitle }}</h3>
                    <div class="text-muted">Chapter {{ $chapterNumber }}</div>
                </div>
                <div class="d-flex flex-wrap" style="gap: .5rem;">
                    <a href="{{ route('admin.tts.audiobook') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Generator
                    </a>
                    <button type="button" wire:click="saveSections" class="btn btn-sm btn-outline-success">
                        <i class="fas fa-save"></i> Save Sections
                    </button>
                    <button type="button" wire:click="regenerateChangedSections" class="btn btn-sm btn-primary">
                        <i class="fas fa-sync"></i> Regenerate Changed Sections
                    </button>
                </div>
            </div>

            @if (!empty($statusMessage))
                @php [$statusType, $statusText] = explode(':', $statusMessage, 2); @endphp
                <div class="alert alert-{{ $statusType === 'success' ? 'success' : 'danger' }} py-2 mt-3 mb-0">
                    <i class="fas fa-{{ $statusType === 'success' ? 'check-circle' : 'exclamation-triangle' }}"></i>
                    {{ $statusText }}
                </div>
            @endif
        </div>
    </div>

    <div class="card card-secondary card-outline mb-3">
        <div class="card-body py-3">
            <div class="row align-items-end">
                <div class="col-md-6">
                    <label class="small font-weight-bold mb-1">Chapter Title</label>
                    <input type="text" wire:model.blur="chapterTitle" class="form-control" placeholder="Chapter title">
                </div>
                <div class="col-md-6">
                    <div class="small text-muted mb-1">Current Source Format</div>
                    <span class="badge badge-{{ $usesSsml ? 'info' : 'secondary' }}">
                        {{ $usesSsml ? 'SSML / Markup' : 'Plain Text' }}
                    </span>
                    <div class="small text-muted mt-2">
                        Sections are split by blank lines when possible; plain-text chapters with a single block fall back to sentence splitting.
                    </div>
                </div>
            </div>

            @if ($chapterAudioUrl)
                <div class="mt-3 border rounded bg-light p-2">
                    <div class="small text-muted mb-1">Current rebuilt chapter audio</div>
                    <audio controls class="w-100" style="height: 32px;" src="{{ $chapterAudioUrl }}"></audio>
                </div>
            @endif
        </div>
    </div>

    @foreach ($sections as $index => $section)
        @php
            $sectionNumber = $index + 1;
            $isDirty = in_array($sectionNumber, $dirtyChunkNumbers, true);
        @endphp
        <div class="card mb-3 {{ $isDirty ? 'card-warning card-outline' : 'card-light' }}">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <div>
                    <strong>Section {{ $sectionNumber }}</strong>
                </div>
                <div>
                    <span class="badge badge-{{ $isDirty ? 'warning' : 'success' }}">
                        {{ $isDirty ? 'Needs audio refresh' : 'Audio reusable' }}
                    </span>
                </div>
            </div>
            <div class="card-body">
                <textarea wire:model.blur="sections.{{ $index }}.text"
                          class="form-control"
                          rows="5"
                          style="line-height: 1.6; resize: vertical;">{{ $section['text'] ?? '' }}</textarea>
            </div>
        </div>
    @endforeach
</div>