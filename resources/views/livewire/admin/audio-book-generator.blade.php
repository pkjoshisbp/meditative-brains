<div>
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
            <div class="col-md-4">
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
                        wire:loading.attr="disabled" wire:confirm="Generate audio for all pending chapters? This may take several minutes.">
                    <span wire:loading.remove wire:target="generateAll">
                        <i class="fas fa-play-circle"></i> Generate All
                    </span>
                    <span wire:loading wire:target="generateAll">
                        <i class="fas fa-spinner fa-spin"></i> Generating…
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
                        @foreach ($savedBooks as $sb)
                            <option value="{{ $sb['id'] }}"
                                {{ ($savedBookId ?? 0) === ($sb['id'] ?? 0) ? 'selected' : '' }}>
                                {{ $sb['book_title'] ?? 'Untitled' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            @if ($savedBookId)
                <div class="col-md-6">
                    <small class="text-success"><i class="fas fa-check-circle"></i> Saved</small>
                </div>
            @endif
        </div>
        @endif

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
                            @if ($ch['status'] === 'done' && $ch['audio_url'])
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
                            style="font-family: 'Courier New', monospace; font-size: 0.82rem; resize: vertical;"></textarea>
                        <small class="text-muted">
                            Custom markup: <code>[pause:800]</code> · <code>[silence:500]</code> ·
                            <code>[personality:Warm]…[/personality]</code> · <code>**emphasis**</code>
                        </small>
                    @else
                        <textarea
                            wire:model.blur="chapters.{{ $activeIndex }}.plain_content"
                            wire:key="plain-editor-{{ $ch['id'] }}"
                            class="form-control"
                            rows="14"
                            placeholder="Plain text version (no markup). Leave blank if SSML is provided — the backend will use the SSML content."
                            style="font-size: 0.85rem; resize: vertical;"></textarea>
                        <small class="text-muted">
                            Plain text is used as a reference. Audio is generated from SSML/Markup when available.
                        </small>
                    @endif
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
                                <select wire:model="prosodyRate" class="form-control form-control-sm">
                                    <option value="x-slow">X-Slow</option>
                                    <option value="slow">Slow</option>
                                    <option value="medium">Medium</option>
                                    <option value="fast">Fast</option>
                                    <option value="x-fast">X-Fast</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mb-2">
                                <label class="mb-1 small font-weight-bold">Pitch</label>
                                <select wire:model="prosodyPitch" class="form-control form-control-sm">
                                    <option value="x-low">X-Low</option>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="x-high">X-High</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mb-2">
                                <label class="mb-1 small font-weight-bold">Volume</label>
                                <select wire:model="prosodyVolume" class="form-control form-control-sm">
                                    <option value="x-soft">X-Soft</option>
                                    <option value="soft">Soft</option>
                                    <option value="medium">Medium</option>
                                    <option value="loud">Loud</option>
                                    <option value="x-loud">X-Loud</option>
                                </select>
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
                    <button wire:click="generateChapter({{ $ch['id'] }})"
                            wire:loading.attr="disabled"
                            wire:target="generateChapter({{ $ch['id'] }})"
                            class="btn btn-primary mr-2">
                        <span wire:loading.remove wire:target="generateChapter({{ $ch['id'] }})">
                            <i class="fas fa-play"></i> Generate This Chapter
                        </span>
                        <span wire:loading wire:target="generateChapter({{ $ch['id'] }})">
                            <i class="fas fa-spinner fa-spin"></i> Generating…
                        </span>
                    </button>
                    <small class="text-muted">
                        Settings apply to all chapters. Each chapter generates its own audio file.
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
        @php $doneChapters = collect($chapters)->where('status', 'done')->values(); @endphp
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
</div>
