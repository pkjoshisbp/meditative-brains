<div>
@section('title', 'TTS Test')

@section('content_header')
    <h1>TTS Integration Test</h1>
@stop

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-check-circle text-success"></i> TTS Components Status
                    </h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-success">
                        <h4><i class="icon fas fa-check"></i> Success!</h4>
                        {{ $message }}
                    </div>
                    
                    <h5>Available TTS Components:</h5>
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-comments text-primary"></i> TTS Messages Management</span>
                            <a href="{{ route('admin.tts.messages') }}" class="btn btn-primary btn-sm">Access</a>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-tags text-info"></i> TTS Categories Management</span>
                            <a href="{{ route('admin.tts.categories') }}" class="btn btn-info btn-sm">Access</a>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-volume-up text-success"></i> Audio Generator</span>
                            <a href="{{ route('admin.tts.generator') }}" class="btn btn-success btn-sm">Access</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@stop
</div>
