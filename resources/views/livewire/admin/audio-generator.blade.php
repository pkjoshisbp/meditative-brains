<div>
@section('title', 'Audio Generator')

@section('content_header')
    <h1>TTS Audio Generator</h1>
@stop

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Flash Messages -->
            @if (session()->has('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> {{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            @endif

            @if (session()->has('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-volume-up"></i> Generate TTS Audio
                    </h3>
                    <div class="card-tools">
                        <button class="btn btn-secondary btn-sm" wire:click="resetForm">
                            <i class="fas fa-redo"></i> Reset Form
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form wire:submit.prevent="generateAudio">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="category">Category</label>
                                    <input type="text" wire:model="category" class="form-control" 
                                           id="category" placeholder="e.g. affirmations, motivation">
                                    @error('category') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="engine">TTS Engine</label>
                                    <select wire:model.live="engine" class="form-control" id="engine">
                                        <option value="azure">Azure TTS</option>
                                        <option value="vits">VITS Local</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="language">Language</label>
                                    <select wire:model.live="language" class="form-control" id="language">
                                        @foreach ($languages as $lang)
                                            <option value="{{ $lang }}">{{ $lang }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="speaker">Speaker Voice</label>
                                    <select wire:model="speaker" class="form-control" id="speaker">
                                        @foreach ($speakersByLanguage[$language] ?? [] as $spk)
                                            <option value="{{ $spk }}">{{ $spk }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Prosody Controls -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-sliders-h"></i> Voice Settings
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="prosodyRate">Speech Rate</label>
                                            <select wire:model="prosodyRate" class="form-control" id="prosodyRate">
                                                <option value="x-slow">Extra Slow</option>
                                                <option value="slow">Slow</option>
                                                <option value="medium">Medium</option>
                                                <option value="fast">Fast</option>
                                                <option value="x-fast">Extra Fast</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="prosodyPitch">Pitch</label>
                                            <select wire:model="prosodyPitch" class="form-control" id="prosodyPitch">
                                                <option value="x-low">Extra Low</option>
                                                <option value="low">Low</option>
                                                <option value="medium">Medium</option>
                                                <option value="high">High</option>
                                                <option value="x-high">Extra High</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="prosodyVolume">Volume</label>
                                            <select wire:model="prosodyVolume" class="form-control" id="prosodyVolume">
                                                <option value="silent">Silent</option>
                                                <option value="x-soft">Extra Soft</option>
                                                <option value="soft">Soft</option>
                                                <option value="medium">Medium</option>
                                                <option value="loud">Loud</option>
                                                <option value="x-loud">Extra Loud</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Background Music -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-music"></i> Background Music
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" wire:model="backgroundMusic" id="backgroundMusic">
                                            <label class="form-check-label" for="backgroundMusic">
                                                Enable Background Music
                                            </label>
                                        </div>
                                    </div>
                                    @if($backgroundMusic)
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="musicVolume">Music Volume</label>
                                                <input type="range" wire:model="musicVolume" class="form-control-range" 
                                                       id="musicVolume" min="0" max="1" step="0.1">
                                                <small class="form-text text-muted">Current: {{ $musicVolume }}</small>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="messages">Text Content</label>
                            <textarea wire:model="messages" class="form-control" id="messages" rows="6" 
                                      placeholder="Enter the text you want to convert to speech..."></textarea>
                            @error('messages') <span class="text-danger">{{ $message }}</span> @enderror
                            <small class="form-text text-muted">
                                Enter the text that will be converted to speech. Line breaks will be respected for natural pauses.
                            </small>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-lg" 
                                    {{ $isGenerating ? 'disabled' : '' }}>
                                @if($isGenerating)
                                    <i class="fas fa-spinner fa-spin"></i> Generating Audio...
                                @else
                                    <i class="fas fa-microphone"></i> Generate Audio
                                @endif
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Generation Result -->
            @if($generationResult)
                <div class="card mt-4">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-check-circle text-success"></i> Audio Generated Successfully
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h5>Generation Details:</h5>
                                <ul class="list-unstyled">
                                    <li><strong>Category:</strong> {{ $category }}</li>
                                    <li><strong>Language:</strong> {{ $language }}</li>
                                    <li><strong>Speaker:</strong> {{ $speaker }}</li>
                                    <li><strong>Engine:</strong> {{ strtoupper($engine) }}</li>
                                    @if(isset($generationResult['duration']))
                                        <li><strong>Duration:</strong> {{ $generationResult['duration'] }}s</li>
                                    @endif
                                    @if(isset($generationResult['fileSize']))
                                        <li><strong>File Size:</strong> {{ round($generationResult['fileSize'] / 1024, 2) }} KB</li>
                                    @endif
                                </ul>
                            </div>
                            <div class="col-md-4 text-right">
                                @if(isset($generationResult['audioUrl']))
                                    <a href="https://meditative-brains.com:3001{{ $generationResult['audioUrl'] }}" 
                                       target="_blank" class="btn btn-success">
                                        <i class="fas fa-download"></i> Download Audio
                                    </a>
                                    <br><br>
                                    <audio controls class="w-100">
                                        <source src="https://meditative-brains.com:3001{{ $generationResult['audioUrl'] }}" type="audio/mpeg">
                                        Your browser does not support the audio element.
                                    </audio>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@stop

@section('js')
<script>
// Auto-hide alerts after 5 seconds
setTimeout(function() {
    $('.alert').fadeOut();
}, 5000);
</script>
@stop
</div>
