<div>
@php
    $isCurrentAudioVariant = $isCurrentAudioVariant ?? true;
@endphp
{{-- ─────────────────────────────────────────────────────────────────
     Top toolbar: book meta + bulk actions
     ───────────────────────────────────────────────────────────────── --}}
<div class="card card-primary card-outline mb-3">
    <div class="card-body py-2">
        <div class="row align-items-center">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-book"></i></span>
                    </div>
                    <input type="text" wire:model.blur="bookTitle"
                           class="form-control" placeholder="Book title">
                </div>
            </div>
            <div class="col-md-2">
                <div class="input-group input-group-sm">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                    </div>
                    <input type="text" wire:model.blur="bookAuthor"
                           class="form-control" placeholder="Author">
                </div>
            </div>
            <div class="col-md-2">
                <div class="input-group input-group-sm">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-tags"></i></span>
                    </div>
                    <input type="text" wire:model.blur="variantName"
                           class="form-control" placeholder="Variant name">
                </div>
            </div>
            <div class="col-md-2">
                {{-- Progress bar --}}
                @if ($totalCount > 0)
                <div>
                    <div class="progress progress-sm mb-0" style="height:18px;">
                        <div class="progress-bar bg-success" role="progressbar"
                             style="width: {{ round(($doneCount / $totalCount) * 100) }}%">
                            {{ $doneCount }}/{{ $totalCount }}
                        </div>
                    </div>
                    <small class="text-muted">{{ $doneCount }} generated · {{ $pendingCount }} pending</small>
                </div>
                @endif
            </div>
            <div class="col-md-3 text-right">
                <button class="btn btn-sm btn-info mr-1" wire:click="importFromFiles"
                        wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="importFromFiles">
                        <i class="fas fa-file-import"></i> Import
                    </span>
                    <span wire:loading wire:target="importFromFiles">
                        <i class="fas fa-spinner fa-spin"></i> Importing…
                    </span>
                </button>
                <button class="btn btn-sm btn-warning mr-1" wire:click="saveBook"
                        wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="saveBook">
                        <i class="fas fa-save"></i> Save
                    </span>
                    <span wire:loading wire:target="saveBook">
                        <i class="fas fa-spinner fa-spin"></i> Saving…
                    </span>
                </button>
                <button class="btn btn-sm btn-success" wire:click="generateAll"
                        wire:loading.attr="disabled" wire:confirm="Generate audio for all PENDING/errored chapters? Already-done chapters will be skipped.">
                    <span wire:loading.remove wire:target="generateAll">
                        <i class="fas fa-play-circle"></i> Generate Pending
                    </span>
                    <span wire:loading wire:target="generateAll">
                        <i class="fas fa-spinner fa-spin"></i> Generating…
                    </span>
                </button>
                <button class="btn btn-sm btn-warning ml-1" wire:click="generateAllForce"
                        wire:loading.attr="disabled" wire:confirm="Regenerate ALL chapters (including already-done)? This OVERWRITES existing audio. Continue?">
                    <span wire:loading.remove wire:target="generateAllForce">
                        <i class="fas fa-redo"></i> Regenerate All
                    </span>
                    <span wire:loading wire:target="generateAllForce">
                        <i class="fas fa-spinner fa-spin"></i> Regenerating…
                    </span>
                </button>
            </div>
        </div>

        {{-- Load saved book --}}
        @if (!empty($savedBooks))
        <div class="row mt-2">
            <div class="col-md-6">
                <div class="input-group input-group-sm">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-folder-open"></i> Load</span>
                    </div>
                    <select class="form-control" wire:change="loadBook($event.target.value)">
                        <option value="">— Select a saved book —</option>
                        @foreach ($savedBooks as $group)
                            <optgroup label="{{ $group['book_title'] ?? 'Untitled' }}">
                                @foreach (($group['variants'] ?? []) as $sb)
                                    <option value="{{ $sb['id'] }}"
                                        {{ ($savedBookId ?? 0) === ($sb['id'] ?? 0) ? 'selected' : '' }}>
                                        {{ $sb['label'] ?? ($sb['summary'] ?? 'Default variant') }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <input type="text" wire:model.blur="publicSpeakerName"
                       class="form-control form-control-sm"
                       placeholder="Public speaker name, e.g. Davis">
                <small class="text-muted">Public-facing alias for the selected internal voice.</small>
            </div>
            @if ($savedBookId)
                <div class="col-md-3">
                    <small class="text-success"><i class="fas fa-check-circle"></i> Saved</small>
                </div>
            @endif
        </div>
        @endif

        <div class="row mt-2 align-items-end">
            <div class="col-md-3">
                <div class="custom-control custom-switch">
                    <input type="checkbox"
                           wire:model="hasBackgroundMusic"
                           class="custom-control-input"
                           id="audiobook-bg-enabled">
                    <label class="custom-control-label" for="audiobook-bg-enabled">
                        Background music
                    </label>
                </div>
            </div>
            <div class="col-md-3">
                <label class="mb-1 small font-weight-bold">Music Track</label>
                <select wire:model="backgroundMusicTrack" class="form-control form-control-sm">
                    @forelse ($bgMusicFiles as $track)
                        <option value="{{ $track }}">{{ Str::headline(str_replace(['-', '_'], ' ', $track)) }}</option>
                    @empty
                        <option value="">No tracks found</option>
                    @endforelse
                </select>
            </div>
            <div class="col-md-2">
                <label class="mb-1 small font-weight-bold">Music Volume</label>
                <input type="number"
                       wire:model.blur="backgroundMusicVolume"
                       min="0"
                       max="1"
                       step="0.01"
                       class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="mb-1 small font-weight-bold">TTS Volume</label>
                <input type="number"
                       wire:model.blur="ttsAudioVolume"
                       min="0"
                       max="1"
                       step="0.01"
                       class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <button type="button"
                        wire:click="refreshBgMusicFiles"
                        class="btn btn-sm btn-outline-secondary btn-block">
                    <i class="fas fa-sync"></i> Refresh Tracks
                </button>
            </div>
        </div>

        {{-- Import status feedback --}}
        @if (!empty($importStatus))
            @php [$type, $msg] = explode(':', $importStatus, 2); @endphp
            <div class="alert alert-{{ $type === 'success' ? 'success' : 'danger' }} alert-dismissible py-1 mt-2 mb-0"
                 role="alert">
                <i class="fas fa-{{ $type === 'success' ? 'check-circle' : 'exclamation-triangle' }}"></i>
                {{ $msg }}
                <button type="button" class="close py-1" wire:click="$set('importStatus', '')">
                    <span>&times;</span>
                </button>
            </div>
        @endif
    </div>
</div>

{{-- ─────────────────────────────────────────────────────────────────
     Main 2-column layout
     ───────────────────────────────────────────────────────────────── --}}
<div class="row">

    {{-- ── Left: Chapter list ──────────────────────────────────────── --}}
    <div class="col-md-3">
        <div class="card card-secondary">
            <div class="card-header py-2">
                <h3 class="card-title"><i class="fas fa-list-ol"></i> Chapters</h3>
                <div class="card-tools">
                    <button class="btn btn-xs btn-primary" wire:click="addChapter">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>
            </div>
            <div class="card-body p-0" style="max-height:620px; overflow-y:auto;">
                <ul class="list-group list-group-flush">
                    @foreach ($chapters as $i => $ch)
                        @php
                            $isActive = $ch['id'] === $activeChapterId;
                            $statusColor = match($ch['status']) {
                                'done'      => 'success',
                                'error'     => 'danger',
                                'generating'=> 'warning',
                                default     => 'secondary',
                            };
                            $statusIcon = match($ch['status']) {
                                'done'      => 'check-circle',
                                'error'     => 'times-circle',
                                'generating'=> 'spinner fa-spin',
                                default     => 'circle',
                            };
                        @endphp
                        <li class="list-group-item list-group-item-action p-2
                                   {{ $isActive ? 'active' : '' }}"
                            wire:key="chapter-item-{{ $ch['id'] }}"
                            style="cursor:pointer;"
                            wire:click="setActiveChapter({{ $ch['id'] }})">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center" style="min-width:0;">
                                    <i class="fas fa-{{ $statusIcon }} text-{{ $isActive ? 'white' : $statusColor }} mr-2 flex-shrink-0"
                                       style="font-size:0.8rem;"></i>
                                    <span class="text-truncate" style="font-size:0.85rem;">
                                        {{ $ch['title'] ?: 'Untitled' }}
                                    </span>
                                </div>
                                <div class="d-flex flex-shrink-0 ml-1">
                                    @if ($i > 0)
                                        <button class="btn btn-xs {{ $isActive ? 'btn-light' : 'btn-link' }} p-0 mr-1"
                                                wire:click.stop="moveUp({{ $ch['id'] }})"
                                                title="Move up">
                                            <i class="fas fa-arrow-up" style="font-size:0.7rem;"></i>
                                        </button>
                                    @endif
                                    @if ($i < count($chapters) - 1)
                                        <button class="btn btn-xs {{ $isActive ? 'btn-light' : 'btn-link' }} p-0 mr-1"
                                                wire:click.stop="moveDown({{ $ch['id'] }})"
                                                title="Move down">
                                            <i class="fas fa-arrow-down" style="font-size:0.7rem;"></i>
                                        </button>
                                    @endif
                                    @if (count($chapters) > 1)
                                        <button class="btn btn-xs {{ $isActive ? 'btn-light' : 'btn-link text-danger' }} p-0"
                                                wire:click.stop="removeChapter({{ $ch['id'] }})"
                                                wire:confirm="Remove this chapter?"
                                                title="Delete">
                                            <i class="fas fa-trash" style="font-size:0.7rem;"></i>
                                        </button>
                                    @endif
                                </div>
                            </div>
                            @if ($isCurrentAudioVariant && $ch['status'] === 'done' && $ch['audio_url'])
                                <div class="mt-1">
                                    <small class="{{ $isActive ? 'text-white-50' : 'text-muted' }}">
                                        <i class="fas fa-volume-up"></i> audio ready
                                    </small>
                                </div>
                            @endif
                            @if ($ch['status'] === 'error' && $ch['error'])
                                <div class="mt-1">
                                    <small class="{{ $isActive ? 'text-white' : 'text-danger' }}"
                                           style="font-size:0.72rem;">
                                        {{ Str::limit($ch['error'], 40) }}
                                    </small>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="card-footer p-2">
                <button class="btn btn-xs btn-outline-secondary btn-block"
                        wire:click="resetAllGenerated"
                        wire:confirm="Reset all chapters to pending? Generated audio links will be cleared.">
                    <i class="fas fa-redo"></i> Reset All
                </button>
            </div>
        </div>
    </div>

    {{-- ── Right: Editor + voice settings + generate controls ─────── --}}
    <div class="col-md-9">
        @if ($activeIndex !== null && isset($chapters[$activeIndex]))
            @php $ch = $chapters[$activeIndex]; @endphp

            {{-- Chapter editor card --}}
            <div class="card card-primary card-outline">
                <div class="card-header py-2">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-edit mr-2 text-primary"></i>
                        <input type="text"
                               wire:model.blur="chapters.{{ $activeIndex }}.title"
                               class="form-control form-control-sm font-weight-bold mr-3"
                               style="max-width:320px;"
                               placeholder="Chapter title">
                        <div class="ml-auto d-flex align-items-center">
                            {{-- Status badge --}}
                            @php
                                $badge = match($ch['status']) {
                                    'done'       => ['success', 'check-circle', 'Done'],
                                    'error'      => ['danger',  'times-circle', 'Error'],
                                    'generating' => ['warning', 'spinner fa-spin', 'Generating…'],
                                    default      => ['secondary','circle', 'Pending'],
                                };
                            @endphp
                            <span class="badge badge-{{ $badge[0] }} mr-2">
                                <i class="fas fa-{{ $badge[1] }}"></i> {{ $badge[2] }}
                            </span>
                            @if ($ch['status'] !== 'pending')
                                <button class="btn btn-xs btn-outline-secondary mr-1"
                                        wire:click="resetChapter({{ $ch['id'] }})"
                                        title="Reset chapter">
                                    <i class="fas fa-undo"></i>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Content tabs --}}
                <div class="card-header p-0 border-bottom-0">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link {{ $activeTab === 'ssml' ? 'active' : '' }}"
                               wire:click.prevent="setActiveTab('ssml')" href="#">
                                <i class="fas fa-code"></i> SSML / Markup
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $activeTab === 'plain' ? 'active' : '' }}"
                               wire:click.prevent="setActiveTab('plain')" href="#">
                                <i class="fas fa-align-left"></i> Plain Text
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $activeTab === 'record' ? 'active' : '' }}"
                               wire:click.prevent="setActiveTab('record')" href="#">
                                <i class="fas fa-microphone text-danger"></i> Record Voice
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="card-body p-2">
                    @if ($activeTab === 'ssml')
                        <textarea
                            wire:model.blur="chapters.{{ $activeIndex }}.ssml_content"
                            wire:key="ssml-editor-{{ $ch['id'] }}"
                            class="form-control"
                            rows="14"
                            placeholder="Paste SSML / markup text here.

Supported: [pause:800]  [silence:500]  [personality:Warm]…[/personality]  **bold**  *italic*"
                            style="font-family: 'Inter', 'Segoe UI', sans-serif; font-size: 0.95rem; line-height: 1.6; resize: vertical;"></textarea>
                        <small class="text-muted">
                            Custom markup: <code>[pause:800]</code> · <code>[silence:500]</code> ·
                            <code>[personality:Warm]…[/personality]</code> · <code>**strong emphasis**</code> · <code>*moderate emphasis*</code>
                        </small>
                    @elseif ($activeTab === 'plain')
                        <textarea
                            wire:model.blur="chapters.{{ $activeIndex }}.plain_content"
                            wire:key="plain-editor-{{ $ch['id'] }}"
                            class="form-control"
                            rows="14"
                            placeholder="Plain text version (no markup). Leave blank if SSML is provided — the backend will use the SSML content."
                            style="font-family: 'Inter', 'Segoe UI', sans-serif; font-size: 0.95rem; line-height: 1.6; resize: vertical;"></textarea>
                        <small class="text-muted">
                            Plain text is used as a reference. Audio is generated from SSML/Markup when available.
                        </small>
                    @else
                        {{-- ── Record Voice tab ────────────────────────────────────── --}}
                        {{-- Read-only SSML reference so user can read text while recording --}}
                        @if (!empty($ch['ssml_content']) || !empty($ch['plain_content']))
                        <div class="bg-light border rounded p-2 mb-3" style="max-height:160px;overflow-y:auto;">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="text-muted font-weight-bold text-uppercase">
                                    <i class="fas fa-book-open mr-1"></i> Text Reference (read-only)
                                </small>
                            </div>
                            <pre class="mb-0" style="font-size:0.82rem;white-space:pre-wrap;word-break:break-word;font-family:inherit;">{{ !empty($ch['ssml_content']) ? $ch['ssml_content'] : $ch['plain_content'] }}</pre>
                        </div>
                        @endif
                        <div class="py-2 px-1">
                            <div class="d-flex align-items-center flex-wrap mb-3" style="gap:.5rem;">
                                <button id="rec-start-{{ $ch['id'] }}"
                                        class="btn btn-danger"
                                        onclick="recStart({{ $ch['id'] }})">
                                    <i class="fas fa-microphone"></i> Start Recording
                                </button>
                                <button id="rec-stop-{{ $ch['id'] }}"
                                        class="btn btn-secondary"
                                        style="display:none;"
                                        onclick="recStop({{ $ch['id'] }})">
                                    <i class="fas fa-stop-circle"></i> Stop
                                </button>
                                <span id="rec-dot-{{ $ch['id'] }}"
                                      class="text-danger"
                                      style="display:none; font-size:1.2rem;">
                                    <i class="fas fa-circle fa-pulse"></i>
                                </span>
                                <span id="rec-timer-{{ $ch['id'] }}"
                                      class="font-weight-bold text-muted"
                                      style="font-size:1.1rem; font-variant-numeric:tabular-nums;">
                                    00:00
                                </span>
                            </div>

                            <div id="rec-preview-wrap-{{ $ch['id'] }}"
                                 class="d-flex align-items-center mb-3"
                                 style="display:none;">
                                <i class="fas fa-headphones text-primary mr-2"></i>
                                <audio id="rec-preview-{{ $ch['id'] }}"
                                       controls
                                       class="flex-grow-1"
                                       style="height:32px;"></audio>
                            </div>

                            <button id="rec-upload-{{ $ch['id'] }}"
                                    class="btn btn-success"
                                    style="display:none;"
                                    onclick="recUpload({{ $ch['id'] }})">
                                <i class="fas fa-upload"></i> Save Recording as Chapter Audio
                            </button>

                            <p class="text-muted small mt-3 mb-0">
                                <i class="fas fa-info-circle"></i>
                                Your browser will request microphone access when you click
                                <em>Start Recording</em>. The audio is converted to AAC on the server
                                and stored as encrypted audio — same pipeline as TTS-generated chapters.
                            </p>
                        </div>
                    @endif
                </div>

                {{-- Save content toolbar --}}
                <div class="card-footer py-2 border-top">
                    <div class="d-flex align-items-center">
                        <button wire:click="saveChapterContent({{ $ch['id'] }})"
                                wire:loading.attr="disabled"
                                wire:loading.class="btn-success"
                                wire:target="saveChapterContent"
                                class="btn btn-sm btn-outline-success mr-3">
                            <span wire:loading.remove wire:target="saveChapterContent">
                                <i class="fas fa-save"></i> Save Content
                            </span>
                            <span wire:loading wire:target="saveChapterContent">
                                <i class="fas fa-spinner fa-spin"></i> Saving…
                            </span>
                        </button>

                        @if ($savedBookId)
                            <a href="{{ route('admin.tts.audiobook.sections', ['bookId' => $savedBookId, 'chapterNumber' => $activeIndex + 1]) }}"
                               target="_blank"
                               rel="noopener"
                               class="btn btn-sm btn-outline-primary mr-3">
                                <i class="fas fa-stream"></i> Edit Sections
                            </a>
                        @endif

                        @if ($chapterSaveStatus === 'success:' . $ch['id'])
                            <span class="text-success small">
                                <i class="fas fa-check-circle"></i> Content saved to database
                            </span>
                        @elseif (!empty($chapterSaveStatus) && str_starts_with($chapterSaveStatus, 'error:'))
                            <span class="text-danger small">
                                <i class="fas fa-exclamation-circle"></i>
                                {{ Str::after($chapterSaveStatus, 'error:') }}
                            </span>
                        @else
                            <small class="text-muted">
                                Save the edited content to the database without generating audio.
                            </small>
                        @endif
                    </div>
                </div>

                {{-- Audio player (when done) --}}
                @if ($ch['status'] === 'done' && $ch['audio_url'])
                    <div class="card-footer bg-light py-2">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-headphones text-success mr-2"></i>
                            <strong class="mr-2 text-success">Generated audio:</strong>
                            <audio controls class="flex-grow-1" style="height:32px;"
                                   src="{{ $ch['audio_url'] }}">
                                Your browser does not support audio.
                            </audio>
                            <a href="{{ $ch['audio_url'] }}" download
                               class="btn btn-xs btn-outline-primary ml-2">
                                <i class="fas fa-download"></i>
                            </a>
                        </div>
                    </div>
                @endif

                <div class="card-footer bg-white border-top">
                    <div class="row align-items-end">
                        <div class="col-md-4">
                            <label class="mb-1 small font-weight-bold">Preview Chapter</label>
                            <select wire:model.live="previewChapterNumber" class="form-control form-control-sm">
                                <option value="">Automatic first generated chapter</option>
                                @foreach ($chapters as $previewIndex => $previewChapter)
                                    <option value="{{ $previewIndex + 1 }}">
                                        Chapter {{ $previewIndex + 1 }}: {{ $previewChapter['title'] ?: 'Untitled' }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Choose which chapter becomes the audiobook preview when no custom clip is uploaded.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="mb-1 small font-weight-bold">Custom Preview Audio</label>
                            <input type="file" wire:model="previewAudioUpload" class="form-control-file" accept="audio/*">
                            <small class="text-muted">If you want a hand-picked excerpt, upload it here. This overrides the chapter selection.</small>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex flex-wrap" style="gap:.5rem;">
                                <button type="button" wire:click="savePreviewSelection" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-save"></i> Save Preview Choice
                                </button>
                                <button type="button" wire:click="saveCustomPreviewAudio" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-upload"></i> Upload Custom Preview
                                </button>
                                @if ($customPreviewAudioPath || $customPreviewAudioUrl)
                                    <button type="button" wire:click="clearCustomPreviewAudio" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-times"></i> Clear Custom Preview
                                    </button>
                                @endif
                            </div>
                            @if (!empty($previewStatus))
                                @php [$previewType, $previewMessage] = explode(':', $previewStatus, 2); @endphp
                                <div class="small mt-2 text-{{ $previewType === 'success' ? 'success' : 'danger' }}">
                                    <i class="fas fa-{{ $previewType === 'success' ? 'check-circle' : 'exclamation-circle' }}"></i>
                                    {{ $previewMessage }}
                                </div>
                            @endif
                        </div>
                    </div>
                    @if ($resolvedPreviewUrl)
                        <div class="mt-3 border rounded p-2 bg-light">
                            <div class="small text-muted mb-1">{{ $resolvedPreviewLabel ?: 'Audiobook preview' }}</div>
                            <audio controls class="w-100" style="height:32px;" src="{{ $resolvedPreviewUrl }}"></audio>
                        </div>
                    @elseif ($resolvedPreviewLabel)
                        <div class="small text-muted mt-2">{{ $resolvedPreviewLabel }}</div>
                    @endif
                </div>

                {{-- Error display --}}
                @if ($ch['status'] === 'error' && $ch['error'])
                    <div class="card-footer bg-light py-2">
                        <div class="alert alert-danger mb-0 py-1">
                            <i class="fas fa-exclamation-circle"></i>
                            <strong>Error:</strong> {{ $ch['error'] }}
                        </div>
                    </div>
                @endif
            </div>

            {{-- ── Voice settings + Generate button ─────────────────── --}}
            <div class="card card-secondary card-outline">
                <div class="card-header py-2">
                    <h3 class="card-title"><i class="fas fa-microphone-alt"></i> Voice Settings</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool"
                                data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group mb-2">
                                <label class="mb-1 small font-weight-bold">Engine</label>
                                <select wire:model.live="engine" class="form-control form-control-sm">
                                    <option value="azure">Azure TTS</option>
                                    <option value="vits">VITS Local</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mb-2">
                                <label class="mb-1 small font-weight-bold">Language</label>
                                <select wire:model.live="language" class="form-control form-control-sm">
                                    @foreach ($languages as $lang)
                                        <option value="{{ $lang }}">{{ $lang }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-2">
                                <label class="mb-1 small font-weight-bold">Speaker Voice</label>
                                <select wire:model.live="speaker" class="form-control form-control-sm">
                                    @foreach ($speakers as $spk)
                                        <option value="{{ $spk }}">{{ $spk }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group mb-2">
                                <label class="mb-1 small font-weight-bold">Rate</label>
                                <select wire:model.live="prosodyRate" class="form-control form-control-sm">
                                    <option value="default">Default</option>
                                    <option value="x-slow">X-Slow</option>
                                    <option value="slow">Slow</option>
                                    <option value="medium">Medium</option>
                                    <option value="fast">Fast</option>
                                    <option value="x-fast">X-Fast</option>
                                    <option value="85%">85% Speed</option>
                                    <option value="90%">90% Speed</option>
                                    <option value="110%">110% Speed</option>
                                    <option value="120%">120% Speed</option>
                                    <option value="130%">130% Speed</option>
                                    <option value="custom">Custom…</option>
                                </select>
                                @if($prosodyRate === 'custom')
                                    <input type="text" wire:model="customRate"
                                           class="form-control form-control-sm mt-1"
                                           placeholder="e.g. 95% or 1.1">
                                @endif
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mb-2">
                                <label class="mb-1 small font-weight-bold">Pitch</label>
                                <select wire:model.live="prosodyPitch" class="form-control form-control-sm">
                                    <option value="x-low">X-Low</option>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="x-high">X-High</option>
                                    <option value="+5Hz">+5Hz</option>
                                    <option value="+10Hz">+10Hz</option>
                                    <option value="-5Hz">-5Hz</option>
                                    <option value="-10Hz">-10Hz</option>
                                    <option value="custom">Custom…</option>
                                </select>
                                @if($prosodyPitch === 'custom')
                                    <input type="text" wire:model="customPitch"
                                           class="form-control form-control-sm mt-1"
                                           placeholder="e.g. +5Hz or high">
                                @endif
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mb-2">
                                <label class="mb-1 small font-weight-bold">Volume</label>
                                <select wire:model.live="prosodyVolume" class="form-control form-control-sm">
                                    <option value="default">Default</option>
                                    <option value="silent">Silent</option>
                                    <option value="x-soft">X-Soft</option>
                                    <option value="soft">Soft</option>
                                    <option value="medium">Medium</option>
                                    <option value="x-loud">X-Loud</option>
                                    <option value="80%">80% Volume</option>
                                    <option value="90%">90% Volume</option>
                                    <option value="110%">110% Volume</option>
                                    <option value="120%">120% Volume</option>
                                    <option value="130%">130% Volume</option>
                                    <option value="custom">Custom…</option>
                                </select>
                                @if($prosodyVolume === 'custom')
                                    <input type="text" wire:model="customVolume"
                                           class="form-control form-control-sm mt-1"
                                           placeholder="e.g. +20% or x-loud">
                                @endif
                            </div>
                        </div>
                        @if (!empty($availableStyles))
                            <div class="col-md-3">
                                <div class="form-group mb-2">
                                    <label class="mb-1 small font-weight-bold">Speaking Style</label>
                                    <select wire:model="speakerStyle" class="form-control form-control-sm">
                                        <option value="">— default —</option>
                                        @foreach ($availableStyles as $style)
                                            <option value="{{ $style }}">{{ ucfirst($style) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif
                        @if (!empty($availablePersonalities))
                            <div class="col-md-3">
                                <div class="form-group mb-2">
                                    <label class="mb-1 small font-weight-bold">Personality</label>
                                    <select wire:model="speakerPersonality" class="form-control form-control-sm">
                                        <option value="">— default —</option>
                                        @foreach ($availablePersonalities as $p)
                                            <option value="{{ $p }}">{{ ucfirst($p) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif
                        @if (!empty($availableExpressionStyles))
                            <div class="col-md-3">
                                <div class="form-group mb-2">
                                    <label class="mb-1 small font-weight-bold">Expression Style</label>
                                    <select wire:model="expressionStyle" class="form-control form-control-sm">
                                        <option value="">— default —</option>
                                        @foreach ($availableExpressionStyles as $es)
                                            <option value="{{ $es }}">{{ ucfirst($es) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="card-footer py-2">
                    @if ($isCurrentAudioVariant && $ch['status'] === 'done')
                        <button wire:click="generateChapterForce({{ $ch['id'] }})"
                                wire:loading.attr="disabled"
                                wire:target="generateChapterForce"
                                wire:confirm="This chapter already has audio. Regenerate and OVERWRITE?"
                                class="btn btn-warning mr-2">
                            <span wire:loading.remove wire:target="generateChapterForce">
                                <i class="fas fa-redo"></i> Regenerate (Overwrite)
                            </span>
                            <span wire:loading wire:target="generateChapterForce">
                                <i class="fas fa-spinner fa-spin"></i> Generating…
                            </span>
                        </button>
                    @else
                        <button wire:click="generateChapter({{ $ch['id'] }})"
                                wire:loading.attr="disabled"
                                wire:target="generateChapter"
                                class="btn btn-primary mr-2">
                            <span wire:loading.remove wire:target="generateChapter">
                                <i class="fas fa-play"></i> Generate This Chapter
                            </span>
                            <span wire:loading wire:target="generateChapter">
                                <i class="fas fa-spinner fa-spin"></i> Generating…
                            </span>
                        </button>
                    @endif
                    <small class="text-muted">
                        Settings apply to all chapters. Unchanged sections are reused, and only edited sections need new Azure audio.
                    </small>
                </div>
            </div>

        @else
            <div class="callout callout-info">
                <h5><i class="fas fa-info-circle"></i> No chapter selected</h5>
                <p>Select a chapter from the list, or click <strong>Import Chapters</strong> to load from
                <code>practicing-happiness/tts/</code>.</p>
            </div>
        @endif

        {{-- ── All generated files overview ────────────────────────── --}}
        @php $doneChapters = $isCurrentAudioVariant ? collect($chapters)->where('status', 'done')->values() : collect(); @endphp
        @if ($doneChapters->count() > 0)
            <div class="card card-success card-outline">
                <div class="card-header py-2">
                    <h3 class="card-title">
                        <i class="fas fa-check-circle text-success"></i>
                        Generated Audio Files ({{ $doneChapters->count() }})
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Chapter</th>
                                <th>Audio</th>
                                <th style="width:80px;">Download</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($doneChapters as $done)
                                <tr>
                                    <td class="text-muted" style="font-size:0.8rem;">
                                        {{ collect($chapters)->search(fn($c) => $c['id'] === $done['id']) + 1 }}
                                    </td>
                                    <td>{{ $done['title'] }}</td>
                                    <td>
                                        @if ($done['audio_url'])
                                            <audio controls style="height:28px; max-width:300px;"
                                                   src="{{ $done['audio_url'] }}"></audio>
                                        @else
                                            <span class="text-muted small">No URL</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($done['audio_url'])
                                            <a href="{{ $done['audio_url'] }}" download
                                               class="btn btn-xs btn-outline-success">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>

{{-- ─────────────────────────────────────────────────────────────────────────
     Voice Recorder JavaScript
     Uses the MediaRecorder API; uploads the blob via Livewire file upload then
     calls saveRecordedAudio() to convert + encrypt + store the audio.
     ───────────────────────────────────────────────────────────────────────── --}}
<script>
(function () {
    window._recBlobs = window._recBlobs || {};
    var _recorder = null, _chunks = [], _timer = null, _secs = 0;

    window.recStart = function (id) {
        if (!window.MediaRecorder || !navigator.mediaDevices) {
            alert('Your browser does not support microphone recording.');
            return;
        }
        navigator.mediaDevices.getUserMedia({ audio: true, video: false })
            .then(function (stream) {
                _chunks = [];
                _secs   = 0;

                var mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
                    ? 'audio/webm;codecs=opus'
                    : (MediaRecorder.isTypeSupported('audio/ogg;codecs=opus')
                        ? 'audio/ogg;codecs=opus'
                        : 'audio/webm');

                _recorder = new MediaRecorder(stream, { mimeType: mimeType });

                _recorder.ondataavailable = function (e) {
                    if (e.data && e.data.size > 0) _chunks.push(e.data);
                };

                _recorder.onstop = function () {
                    stream.getTracks().forEach(function (t) { t.stop(); });
                    var blob = new Blob(_chunks, { type: mimeType });
                    window._recBlobs[id] = { blob: blob, type: mimeType };

                    var previewEl   = document.getElementById('rec-preview-'      + id);
                    var previewWrap = document.getElementById('rec-preview-wrap-' + id);
                    if (previewEl)   previewEl.src = URL.createObjectURL(blob);
                    if (previewWrap) previewWrap.style.display = 'flex';

                    var uploadBtn = document.getElementById('rec-upload-' + id);
                    if (uploadBtn) uploadBtn.style.display = 'inline-block';
                };

                _recorder.start(500);

                clearInterval(_timer);
                _timer = setInterval(function () {
                    _secs++;
                    var m = String(Math.floor(_secs / 60)).padStart(2, '0');
                    var s = String(_secs % 60).padStart(2, '0');
                    var el = document.getElementById('rec-timer-' + id);
                    if (el) el.textContent = m + ':' + s;
                }, 1000);

                document.getElementById('rec-start-' + id).style.display = 'none';
                document.getElementById('rec-stop-'  + id).style.display = 'inline-block';
                document.getElementById('rec-dot-'   + id).style.display = 'inline-block';
            })
            .catch(function (err) {
                alert('Microphone access denied or unavailable: ' + err.message);
            });
    };

    window.recStop = function (id) {
        if (_recorder && _recorder.state !== 'inactive') _recorder.stop();
        clearInterval(_timer);
        var startBtn = document.getElementById('rec-start-' + id);
        var stopBtn  = document.getElementById('rec-stop-'  + id);
        var dotEl    = document.getElementById('rec-dot-'   + id);
        if (startBtn) startBtn.style.display = 'inline-block';
        if (stopBtn)  stopBtn.style.display  = 'none';
        if (dotEl)    dotEl.style.display    = 'none';
    };

    window.recUpload = function (id) {
        var entry = window._recBlobs[id];
        if (!entry) { alert('No recording found. Please record first.'); return; }

        var ext  = entry.type.includes('webm') ? 'webm' : 'ogg';
        var file = new File([entry.blob], 'voice-recording.' + ext, { type: entry.type });

        var btn = document.getElementById('rec-upload-' + id);
        btn.disabled  = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing\u2026';

        @this.upload(
            'recordedAudio',
            file,
            function ()    { @this.call('saveRecordedAudio', id); },
            function (err) {
                btn.disabled  = false;
                btn.innerHTML = '<i class="fas fa-upload"></i> Save Recording as Chapter Audio';
                alert('Upload failed: ' + JSON.stringify(err));
            },
            function (pct) {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + Math.round(pct) + '%\u2026';
            }
        );
    };
}());
</script>
</div>
