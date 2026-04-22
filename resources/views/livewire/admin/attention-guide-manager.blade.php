<div>
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Attention Guide Manager</h1>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            @if (session()->has('success'))
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    {{ session('success') }}
                </div>
            @endif

            {{-- ── Form Card ─────────────────────────────────────────────── --}}
            <div class="card card-primary card-outline mb-4">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-bell mr-1"></i>
                        {{ $editingId ? 'Edit Attention Guide' : 'Add New Attention Guide' }}
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        {{-- Language --}}
                        <div class="col-md-2">
                            <div class="form-group">
                                <label class="small font-weight-bold">Language</label>
                                <select wire:model.live="language" class="form-control form-control-sm @error('language') is-invalid @enderror">
                                    @foreach($languages as $lang)
                                        <option value="{{ $lang }}">{{ $lang }}</option>
                                    @endforeach
                                </select>
                                @error('language') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Speaker --}}
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="small font-weight-bold">Speaker</label>
                                <select wire:model="speaker" class="form-control form-control-sm @error('speaker') is-invalid @enderror">
                                    @foreach($speakers as $spk)
                                        <option value="{{ $spk }}">{{ $spk }}</option>
                                    @endforeach
                                </select>
                                @error('speaker') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Engine --}}
                        <div class="col-md-1">
                            <div class="form-group">
                                <label class="small font-weight-bold">Engine</label>
                                <select wire:model="engine" class="form-control form-control-sm @error('engine') is-invalid @enderror">
                                    <option value="azure">Azure</option>
                                    <option value="openai">OpenAI</option>
                                </select>
                            </div>
                        </div>

                        {{-- Speech Speed --}}
                        <div class="col-md-2">
                            <div class="form-group">
                                <label class="small font-weight-bold">Speech Speed</label>
                                <select wire:model="speed" class="form-control form-control-sm @error('speed') is-invalid @enderror">
                                    <option value="x-slow">X-Slow</option>
                                    <option value="slow">Slow</option>
                                    <option value="medium">Medium</option>
                                    <option value="fast">Fast</option>
                                    <option value="x-fast">X-Fast</option>
                                </select>
                                @error('speed') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Interval --}}
                        <div class="col-md-2">
                            <div class="form-group">
                                <label class="small font-weight-bold">Interval (seconds)</label>
                                <input type="number" wire:model="intervalSec" min="5" max="86400"
                                       class="form-control form-control-sm @error('intervalSec') is-invalid @enderror"
                                       placeholder="60">
                                @error('intervalSec') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <small class="text-muted">How often this guide plays (e.g. 60 = every minute)</small>
                            </div>
                        </div>

                        {{-- Active toggle --}}
                        <div class="col-md-2 d-flex align-items-center">
                            <div class="form-group mt-3">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" wire:model="isActive"
                                           class="custom-control-input" id="isActiveSwitch">
                                    <label class="custom-control-label" for="isActiveSwitch">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Message Text --}}
                    <div class="form-group">
                        <label class="small font-weight-bold">Message Text</label>
                        <textarea wire:model="text" rows="3"
                                  class="form-control @error('text') is-invalid @enderror"
                                  placeholder="Enter the attention guide message..."></textarea>
                        @error('text') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="card-footer py-2">
                    <button wire:click="save" wire:loading.attr="disabled"
                            class="btn btn-primary btn-sm mr-2">
                        <span wire:loading.remove wire:target="save">
                            <i class="fas fa-save"></i> {{ $editingId ? 'Update' : 'Save' }}
                        </span>
                        <span wire:loading wire:target="save">
                            <i class="fas fa-spinner fa-spin"></i> Saving…
                        </span>
                    </button>
                    @if ($editingId)
                        <button wire:click="cancelEdit" class="btn btn-secondary btn-sm">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    @endif
                </div>
            </div>

            {{-- ── Guides list ───────────────────────────────────────────── --}}
            <div class="card card-outline mb-4">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-list mr-1"></i> Attention Guides
                        <span class="badge badge-secondary ml-2">{{ count($guides) }}</span>
                    </h3>
                    <div class="card-tools">
                        <small class="text-muted">Active guides are synced to the Flutter app via API.</small>
                    </div>
                </div>
                <div class="card-body p-0">
                    @if(empty($guides))
                        <div class="p-3 text-muted">No attention guides yet. Add one above.</div>
                    @else
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Text</th>
                                <th>Language</th>
                                <th>Speaker</th>
                                <th>Speed</th>
                                <th>Interval</th>
                                <th>Active</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($guides as $guide)
                            <tr class="{{ $editingId === $guide['id'] ? 'table-warning' : '' }}">
                                <td>{{ $guide['id'] }}</td>
                                <td style="max-width:280px;word-break:break-word;">{{ $guide['text'] }}</td>
                                <td><span class="badge badge-info">{{ $guide['language'] }}</span></td>
                                <td><small>{{ $guide['speaker'] }}</small></td>
                                <td>{{ $guide['speed'] }}</td>
                                <td>{{ intdiv($guide['interval_ms'], 1000) }}s</td>
                                <td>
                                    <button wire:click="toggleActive({{ $guide['id'] }})"
                                            class="btn btn-xs {{ $guide['is_active'] ? 'btn-success' : 'btn-secondary' }}">
                                        {{ $guide['is_active'] ? 'Yes' : 'No' }}
                                    </button>
                                </td>
                                <td class="text-nowrap">
                                    <button wire:click="edit({{ $guide['id'] }})"
                                            class="btn btn-xs btn-warning mr-1">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button wire:click="delete({{ $guide['id'] }})"
                                            wire:confirm="Delete this attention guide?"
                                            class="btn btn-xs btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                </div>
            </div>

            {{-- ── API info ──────────────────────────────────────────────── --}}
            <div class="card card-info card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i> Flutter Sync Info</h3>
                </div>
                <div class="card-body">
                    <p class="mb-1">The Flutter app fetches active guides from:</p>
                    <code>GET /api/attention-guides</code> (requires auth token)
                    <p class="mt-2 mb-0 text-muted small">
                        The app merges server guides with locally-added guides.
                        Server guides refresh on each app session start.
                    </p>
                </div>
            </div>

        </div>
    </section>
</div>
