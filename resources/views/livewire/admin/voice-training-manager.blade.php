<div class="container-fluid px-4 py-3">
    <div class="d-flex align-items-center mb-3">
        <h4 class="mb-0 mr-3"><i class="fas fa-microphone-alt text-danger mr-2"></i>XTTS Voice Training</h4>
        <span class="badge badge-{{ $serviceOnline ? 'success' : 'danger' }} ml-2">
            <i class="fas fa-circle" style="font-size:0.6em"></i>
            vast.ai {{ $serviceOnline ? 'Online' : 'Offline' }}
        </span>
        <button wire:click="refreshSpeakers" class="btn btn-xs btn-outline-secondary ml-2" title="Refresh">
            <i class="fas fa-sync-alt" wire:loading.class="fa-spin" wire:target="refreshSpeakers"></i>
        </button>
    </div>

    @if (!$serviceOnline)
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            XTTS API is offline or not configured.
            Set <code>VASTAI_XTTS_URL</code> in your <code>.env</code> to the vast.ai instance URL
            (e.g. <code>http://123.21.129.10:18082</code>), then restart the service with
            <code>bash /workspace/personal_assistant/start_services.sh</code> on the vast.ai instance.
        </div>
    @endif

    {{-- Tabs --}}
    <ul class="nav nav-tabs mb-0">
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'speakers' ? 'active' : '' }}"
               wire:click.prevent="setActiveTab('speakers')" href="#">
                <i class="fas fa-user-circle"></i> Speakers
                @if (count($speakers) > 0)
                    <span class="badge badge-secondary ml-1">{{ count($speakers) }}</span>
                @endif
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'train' ? 'active' : '' }}"
               wire:click.prevent="setActiveTab('train')" href="#">
                <i class="fas fa-brain"></i> Fine-tuning
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'jobs' ? 'active' : '' }}"
               wire:click.prevent="setActiveTab('jobs')" href="#">
                <i class="fas fa-tasks"></i> Training Jobs
                @php $running = collect($jobs)->where('status','running')->count(); @endphp
                @if ($running > 0)
                    <span class="badge badge-warning ml-1">{{ $running }} running</span>
                @endif
            </a>
        </li>
    </ul>

    {{-- ══ SPEAKERS TAB ══════════════════════════════════════════════════════ --}}
    @if ($activeTab === 'speakers')
    <div class="card card-outline card-primary border-top-0 rounded-0 rounded-bottom">
        <div class="card-body">

            {{-- Upload new speaker --}}
            <div class="row">
                <div class="col-md-6">
                    <div class="card card-secondary card-outline">
                        <div class="card-header py-2">
                            <h6 class="mb-0"><i class="fas fa-upload mr-2"></i>Upload Speaker Reference</h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">Upload 10–60 seconds of clean speech audio. XTTS will clone this voice instantly (no training required).</p>

                            <div class="form-group">
                                <label class="small font-weight-bold">Speaker Name</label>
                                <input type="text" wire:model="newSpeakerName"
                                       class="form-control form-control-sm"
                                       placeholder="e.g. sarah_calm (alphanumeric + underscores)">
                            </div>
                            <div class="form-group">
                                <label class="small font-weight-bold">Audio File (WAV/MP3/M4A/OGG)</label>
                                <input type="file" wire:model="speakerFile"
                                       class="form-control-file"
                                       accept=".wav,.mp3,.m4a,.ogg,.webm">
                                <div wire:loading wire:target="speakerFile" class="text-muted small mt-1">
                                    <i class="fas fa-spinner fa-spin"></i> Uploading…
                                </div>
                            </div>

                            @error('newSpeakerName') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                            @error('speakerFile') <div class="text-danger small mb-2">{{ $message }}</div> @enderror

                            <button wire:click="uploadSpeaker"
                                    wire:loading.attr="disabled"
                                    wire:target="uploadSpeaker"
                                    class="btn btn-sm btn-primary">
                                <span wire:loading.remove wire:target="uploadSpeaker">
                                    <i class="fas fa-upload"></i> Upload Speaker
                                </span>
                                <span wire:loading wire:target="uploadSpeaker">
                                    <i class="fas fa-spinner fa-spin"></i> Uploading…
                                </span>
                            </button>

                            @if ($speakerStatus)
                                <div class="mt-2 small {{ str_starts_with($speakerStatus,'success:') ? 'text-success' : 'text-danger' }}">
                                    <i class="fas {{ str_starts_with($speakerStatus,'success:') ? 'fa-check-circle' : 'fa-exclamation-circle' }}"></i>
                                    {{ Str::after($speakerStatus, ':') }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card card-secondary card-outline">
                        <div class="card-header py-2">
                            <h6 class="mb-0"><i class="fas fa-play-circle mr-2"></i>Test Voice Synthesis</h6>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="small font-weight-bold">Speaker</label>
                                <select wire:model="testSpeaker" class="form-control form-control-sm">
                                    <option value="">— Default (no cloning) —</option>
                                    @foreach ($speakers as $sp)
                                        <option value="{{ $sp['name'] }}">{{ $sp['name'] }} ({{ $sp['size_kb'] }} KB)</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="small font-weight-bold">Language</label>
                                <select wire:model="testLanguage" class="form-control form-control-sm">
                                    <option value="en">English</option>
                                    <option value="hi">Hindi</option>
                                    <option value="es">Spanish</option>
                                    <option value="fr">French</option>
                                    <option value="de">German</option>
                                    <option value="pt">Portuguese</option>
                                    <option value="ar">Arabic</option>
                                    <option value="zh-cn">Chinese</option>
                                    <option value="ja">Japanese</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="small font-weight-bold">Text</label>
                                <textarea wire:model="testText" class="form-control form-control-sm" rows="3"></textarea>
                            </div>

                            <button wire:click="testSynthesize"
                                    wire:loading.attr="disabled"
                                    wire:target="testSynthesize"
                                    class="btn btn-sm btn-success">
                                <span wire:loading.remove wire:target="testSynthesize">
                                    <i class="fas fa-volume-up"></i> Synthesize
                                </span>
                                <span wire:loading wire:target="testSynthesize">
                                    <i class="fas fa-spinner fa-spin"></i> Generating…
                                </span>
                            </button>

                            @if ($testStatus)
                                <div class="mt-2 small {{ str_starts_with($testStatus,'success:') ? 'text-success' : 'text-danger' }}">
                                    <i class="fas {{ str_starts_with($testStatus,'success:') ? 'fa-check-circle' : 'fa-exclamation-circle' }}"></i>
                                    {{ Str::after($testStatus, ':') }}
                                </div>
                            @endif
                            @if ($testAudioB64)
                                <div class="mt-2">
                                    <audio controls class="w-100" style="max-width:100%">
                                        <source src="data:audio/wav;base64,{{ $testAudioB64 }}" type="audio/wav">
                                    </audio>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Speaker list --}}
            @if (count($speakers) > 0)
            <div class="mt-3">
                <h6 class="text-muted small font-weight-bold text-uppercase">Uploaded Speakers</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Name</th>
                                <th>Size</th>
                                <th width="100">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($speakers as $sp)
                            <tr>
                                <td>
                                    <i class="fas fa-user-circle text-primary mr-1"></i>
                                    <strong>{{ $sp['name'] }}</strong>
                                </td>
                                <td class="text-muted small">{{ $sp['size_kb'] }} KB</td>
                                <td>
                                    <button class="btn btn-xs btn-outline-success mr-1"
                                            wire:click="$set('testSpeaker', '{{ $sp['name'] }}')"
                                            title="Select for test">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    <button class="btn btn-xs btn-outline-danger"
                                            wire:click="deleteSpeaker('{{ $sp['name'] }}')"
                                            wire:confirm="Delete speaker '{{ $sp['name'] }}'?"
                                            title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @else
                <div class="alert alert-info mt-3 mb-0">
                    <i class="fas fa-info-circle mr-2"></i>
                    No speakers uploaded yet. Upload a WAV reference file above to enable voice cloning.
                </div>
            @endif
        </div>
    </div>
    @endif

    {{-- ══ FINE-TUNING TAB ═══════════════════════════════════════════════════ --}}
    @if ($activeTab === 'train')
    <div class="card card-outline card-warning border-top-0 rounded-0 rounded-bottom">
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong>Fine-tuning vs Zero-shot Cloning:</strong>
                Zero-shot cloning (Speakers tab) works instantly with just a reference WAV.
                Fine-tuning requires <strong>at least 30–60 minutes of clean audio</strong> and several hours of GPU training,
                but produces higher-quality results for a specific voice.
            </div>

            <div class="row">
                <div class="col-md-5">
                    <div class="card card-secondary card-outline">
                        <div class="card-header py-2">
                            <h6 class="mb-0"><i class="fas fa-database mr-2"></i>Training Data</h6>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="small font-weight-bold">Voice Name</label>
                                <input type="text" wire:model.live="trainVoiceName"
                                       class="form-control form-control-sm"
                                       placeholder="e.g. my_custom_voice">
                            </div>

                            @if ($trainVoiceName)
                                <div class="form-group">
                                    <label class="small font-weight-bold">Audio Sample</label>
                                    <input type="file" wire:model="trainFile"
                                           class="form-control-file"
                                           accept=".wav,.mp3,.m4a,.ogg,.webm">
                                </div>
                                <div class="form-group">
                                    <label class="small font-weight-bold">Transcript (exact words spoken)</label>
                                    <textarea wire:model="trainTranscript"
                                              class="form-control form-control-sm" rows="3"
                                              placeholder="Type the exact words spoken in the audio file above…"></textarea>
                                </div>

                                @error('trainVoiceName') <div class="text-danger small mb-1">{{ $message }}</div> @enderror
                                @error('trainFile') <div class="text-danger small mb-1">{{ $message }}</div> @enderror
                                @error('trainTranscript') <div class="text-danger small mb-1">{{ $message }}</div> @enderror

                                <button wire:click="uploadTrainingSample"
                                        wire:loading.attr="disabled"
                                        wire:target="uploadTrainingSample"
                                        class="btn btn-sm btn-secondary">
                                    <span wire:loading.remove wire:target="uploadTrainingSample">
                                        <i class="fas fa-plus"></i> Add Sample
                                    </span>
                                    <span wire:loading wire:target="uploadTrainingSample">
                                        <i class="fas fa-spinner fa-spin"></i> Uploading…
                                    </span>
                                </button>

                                @if ($trainUploadStatus)
                                    <div class="mt-2 small {{ str_starts_with($trainUploadStatus,'success:') ? 'text-success' : 'text-danger' }}">
                                        <i class="fas {{ str_starts_with($trainUploadStatus,'success:') ? 'fa-check-circle' : 'fa-exclamation-circle' }}"></i>
                                        {{ Str::after($trainUploadStatus, ':') }}
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    {{-- Sample list --}}
                    @if ($trainVoiceName && count($trainSamples) > 0)
                    <div class="card card-secondary card-outline">
                        <div class="card-header py-2">
                            <h6 class="mb-0">
                                Samples
                                <span class="badge badge-secondary ml-1">{{ count($trainSamples) }}</span>
                                <span class="badge {{ count($trainSamples) >= 5 ? 'badge-success' : 'badge-warning' }} ml-1">
                                    {{ count($trainSamples) >= 5 ? '✓ Ready' : 'Need ' . (5 - count($trainSamples)) . ' more' }}
                                </span>
                            </h6>
                        </div>
                        <div class="card-body p-0" style="max-height:300px;overflow-y:auto;">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    @foreach ($trainSamples as $s)
                                    <tr>
                                        <td class="small">
                                            <span class="text-muted">{{ $s['size_kb'] }}KB</span>
                                            <div class="text-truncate" style="max-width:160px;" title="{{ $s['transcript'] }}">
                                                {{ $s['transcript'] }}
                                            </div>
                                        </td>
                                        <td width="30">
                                            <button class="btn btn-xs btn-outline-danger"
                                                    wire:click="deleteTrainingSample('{{ $s['id'] }}')">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @elseif ($trainVoiceName)
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            No samples uploaded yet for <strong>{{ $trainVoiceName }}</strong>.
                        </div>
                    @endif
                </div>

                <div class="col-md-3">
                    @if ($trainVoiceName)
                    <div class="card card-danger card-outline">
                        <div class="card-header py-2">
                            <h6 class="mb-0"><i class="fas fa-play mr-2"></i>Start Training</h6>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="small font-weight-bold">Epochs</label>
                                <input type="number" wire:model="trainEpochs"
                                       class="form-control form-control-sm"
                                       min="10" max="1000" step="10">
                                <small class="text-muted">100–300 recommended</small>
                            </div>
                            <div class="form-group">
                                <label class="small font-weight-bold">Batch Size</label>
                                <input type="number" wire:model="trainBatchSize"
                                       class="form-control form-control-sm"
                                       min="1" max="8">
                                <small class="text-muted">2–4 for 16GB VRAM</small>
                            </div>

                            <button wire:click="startTraining"
                                    wire:loading.attr="disabled"
                                    wire:target="startTraining"
                                    class="btn btn-sm btn-danger btn-block"
                                    {{ count($trainSamples) < 5 ? 'disabled' : '' }}>
                                <span wire:loading.remove wire:target="startTraining">
                                    <i class="fas fa-rocket"></i> Start Fine-tuning
                                </span>
                                <span wire:loading wire:target="startTraining">
                                    <i class="fas fa-spinner fa-spin"></i> Starting…
                                </span>
                            </button>

                            @if ($trainJobStatus)
                                <div class="mt-2 small {{ str_starts_with($trainJobStatus,'success:') ? 'text-success' : 'text-danger' }}">
                                    {{ Str::after($trainJobStatus, ':') }}
                                </div>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ══ JOBS TAB ═══════════════════════════════════════════════════════════ --}}
    @if ($activeTab === 'jobs')
    <div class="card card-outline card-info border-top-0 rounded-0 rounded-bottom">
        <div class="card-body">
            <div class="d-flex align-items-center mb-3">
                <h6 class="mb-0 mr-3">Training Jobs</h6>
                <button wire:click="refreshJobs" class="btn btn-xs btn-outline-secondary">
                    <i class="fas fa-sync-alt" wire:loading.class="fa-spin" wire:target="refreshJobs"></i> Refresh
                </button>
            </div>

            @if (count($jobs) === 0)
                <div class="alert alert-info mb-0">
                    No training jobs found. Start a fine-tuning job from the Fine-tuning tab.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>Job ID</th>
                                <th>Voice</th>
                                <th>Status</th>
                                <th>Progress</th>
                                <th>Log (last 5 lines)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($jobs as $job)
                            @php
                                $statusColor = match($job['status'] ?? '') {
                                    'done'      => 'success',
                                    'running'   => 'warning',
                                    'failed'    => 'danger',
                                    'cancelled' => 'secondary',
                                    default     => 'info',
                                };
                            @endphp
                            <tr>
                                <td class="small text-monospace">{{ $job['job_id'] ?? '' }}</td>
                                <td><strong>{{ $job['voice_name'] ?? '' }}</strong></td>
                                <td>
                                    <span class="badge badge-{{ $statusColor }}">
                                        {{ ucfirst($job['status'] ?? '') }}
                                    </span>
                                </td>
                                <td class="small text-muted" style="max-width:200px;">
                                    {{ $job['progress'] ?? '' }}
                                </td>
                                <td>
                                    <pre class="mb-0 small" style="font-size:0.7rem;max-height:60px;overflow-y:auto;white-space:pre-wrap;">{{ implode("\n", array_slice($job['log_tail'] ?? [], -5)) }}</pre>
                                </td>
                                <td>
                                    @if (in_array($job['status'] ?? '', ['running', 'queued']))
                                        <button class="btn btn-xs btn-outline-danger"
                                                wire:click="cancelJob('{{ $job['job_id'] }}')"
                                                wire:confirm="Cancel this training job?">
                                            <i class="fas fa-stop"></i> Cancel
                                        </button>
                                    @elseif (($job['status'] ?? '') === 'done' && ($job['checkpoint'] ?? ''))
                                        <span class="badge badge-success">
                                            <i class="fas fa-check"></i> Model saved
                                        </span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
    @endif
</div>
