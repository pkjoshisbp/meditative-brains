@extends('adminlte::page')

@section('title', 'Background Music Manager')

@section('content_header')
    <h1><i class="fas fa-music mr-2"></i>Background Music Manager</h1>
@stop

@section('content')

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            @foreach($errors->all() as $error)
                <div><i class="fas fa-exclamation-circle mr-1"></i>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    {{-- Upload Card --}}
    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-upload mr-1"></i>Upload New Track</h3>
        </div>
        <div class="card-body">
            <p class="text-muted">
                Uploaded tracks are saved to <code>storage/app/bg-music/original/</code>,
                <code>public/bg-music/</code> (for home-screen playback), and automatically
                AES-256 encrypted to <code>storage/app/bg-music/encrypted/</code> for
                secure Flutter streaming.
            </p>
            <form action="{{ route('admin.bg-music.upload') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="col-md-5">
                        <div class="form-group">
                            <label for="track_name">Track Name <span class="text-danger">*</span></label>
                            <input type="text" id="track_name" name="track_name"
                                   class="form-control @error('track_name') is-invalid @enderror"
                                   placeholder="e.g. Positive Attitude"
                                   value="{{ old('track_name') }}" required>
                            <small class="form-text text-muted">
                                Will be converted to slug. E.g. "Positive Attitude" → <code>positive-attitude.mp3</code>
                            </small>
                            @error('track_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-group">
                            <label for="audio_file">Audio File <span class="text-danger">*</span></label>
                            <div class="custom-file">
                                <input type="file" id="audio_file" name="audio_file"
                                       class="custom-file-input @error('audio_file') is-invalid @enderror"
                                       accept=".mp3,.aac,.m4a,.wav,.ogg" required>
                                <label class="custom-file-label" for="audio_file">Choose file…</label>
                            </div>
                            <small class="form-text text-muted">MP3, AAC, M4A, WAV or OGG — max 30 MB</small>
                            @error('audio_file')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="form-group w-100">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-upload mr-1"></i>Upload
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Existing Tracks --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list mr-1"></i>Existing Tracks
                <span class="badge badge-secondary ml-2">{{ count($tracks) }}</span>
            </h3>
        </div>
        <div class="card-body p-0">
            @if(empty($tracks))
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-music fa-2x mb-2 d-block"></i>
                    No background music tracks found. Upload your first track above.
                </div>
            @else
                <table class="table table-hover table-sm mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Filename</th>
                            <th>Slug</th>
                            <th class="text-right">Size</th>
                            <th class="text-center">Original</th>
                            <th class="text-center">Public</th>
                            <th class="text-center">Encrypted</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tracks as $track)
                        <tr>
                            <td>
                                <i class="fas fa-file-audio text-primary mr-1"></i>
                                <code>{{ $track['filename'] }}</code>
                            </td>
                            <td><span class="badge badge-light">{{ $track['slug'] }}</span></td>
                            <td class="text-right text-muted">{{ $track['size_kb'] }} KB</td>
                            <td class="text-center">
                                <i class="fas fa-check-circle text-success" title="Stored in storage/app/bg-music/original/"></i>
                            </td>
                            <td class="text-center">
                                @if($track['has_public'])
                                    <i class="fas fa-check-circle text-success" title="Accessible at /bg-music/{{ $track['filename'] }}"></i>
                                @else
                                    <i class="fas fa-times-circle text-danger" title="Missing from public/bg-music/"></i>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($track['has_encrypted'])
                                    <i class="fas fa-lock text-info" title="AES-256 encrypted"></i>
                                @else
                                    <span class="badge badge-warning" title="Will be encrypted on next Flutter app request">Pending</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="/bg-music/{{ $track['filename'] }}" target="_blank"
                                   class="btn btn-xs btn-outline-info mr-1" title="Play (public URL)">
                                    <i class="fas fa-play"></i>
                                </a>
                                <form action="{{ route('admin.bg-music.delete') }}" method="POST"
                                      class="d-inline"
                                      onsubmit="return confirm('Delete {{ $track['filename'] }}? This removes original, public, and encrypted copies.')">
                                    @csrf
                                    <input type="hidden" name="filename" value="{{ $track['filename'] }}">
                                    <button type="submit" class="btn btn-xs btn-outline-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- Storage Paths Info --}}
    <div class="card card-secondary collapsed-card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i>Storage Paths Reference</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-sm table-bordered">
                <thead><tr><th>Location</th><th>Path</th><th>Purpose</th></tr></thead>
                <tbody>
                    <tr>
                        <td>Original</td>
                        <td><code>storage/app/bg-music/original/&lt;slug&gt;.ext</code></td>
                        <td>Source for encryption. Used by the WS server for re-encryption.</td>
                    </tr>
                    <tr>
                        <td>Public</td>
                        <td><code>public/bg-music/&lt;slug&gt;.ext</code></td>
                        <td>Direct public URL (<code>/bg-music/slug.ext</code>). Used by home screen.</td>
                    </tr>
                    <tr>
                        <td>Encrypted</td>
                        <td><code>storage/app/bg-music/encrypted/&lt;slug&gt;.enc</code></td>
                        <td>AES-256-CBC encrypted. Served via signed stream URL to Flutter.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

@stop

@section('js')
<script>
    // Update custom file input label with selected filename
    document.getElementById('audio_file').addEventListener('change', function() {
        var label = this.nextElementSibling;
        label.textContent = this.files.length ? this.files[0].name : 'Choose file…';
    });
</script>
@stop
