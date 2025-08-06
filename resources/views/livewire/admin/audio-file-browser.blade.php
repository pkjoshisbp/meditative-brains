<div>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Audio File Browser</h5>
        <div>
            <button type="button" class="btn btn-sm btn-primary me-2" wire:click="$set('showUploadModal', true)">
                <i class="fas fa-upload"></i> Upload Files
            </button>
            <button type="button" class="btn btn-sm btn-secondary" wire:click="$set('showNewFolderModal', true)">
                <i class="fas fa-folder-plus"></i> New Folder
            </button>
        </div>
    </div>
    
    <div class="card-body">
        @if (session()->has('message'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('message') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session()->has('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Navigation -->
        <div class="mb-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="#" wire:click.prevent="navigateToDirectory('')">
                            <i class="fas fa-home"></i> Root
                        </a>
                    </li>
                    @if($currentPath)
                        @php
                            $pathParts = explode('/', trim($currentPath, '/'));
                            $buildPath = '';
                        @endphp
                        @foreach($pathParts as $part)
                            @php $buildPath .= ($buildPath ? '/' : '') . $part; @endphp
                            <li class="breadcrumb-item">
                                <a href="#" wire:click.prevent="navigateToDirectory('{{ $buildPath }}')">{{ $part }}</a>
                            </li>
                        @endforeach
                    @endif
                </ol>
            </nav>
        </div>

        <!-- File List -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Modified</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @if($currentPath)
                        <tr>
                            <td colspan="5">
                                <a href="#" wire:click.prevent="goUp()" class="text-decoration-none">
                                    <i class="fas fa-arrow-up"></i> .. (Go Up)
                                </a>
                            </td>
                        </tr>
                    @endif

                    @foreach($directories as $directory)
                        <tr>
                            <td>
                                <i class="fas fa-folder text-warning me-2"></i>
                                <a href="#" wire:click.prevent="navigateToDirectory('{{ $directory['path'] }}')" class="text-decoration-none">
                                    {{ $directory['name'] }}
                                </a>
                            </td>
                            <td>Folder</td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                        </tr>
                    @endforeach

                    @foreach($files as $file)
                        <tr class="{{ $selectedFile === $file['path'] ? 'table-primary' : '' }}">
                            <td>
                                <i class="fas fa-file-audio text-info me-2"></i>
                                {{ $file['name'] }}
                            </td>
                            <td>Audio File</td>
                            <td>{{ $this->formatFileSize($file['size']) }}</td>
                            <td>{{ $file['modified'] ? date('Y-m-d H:i', $file['modified']) : '-' }}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-success" wire:click="selectFile('{{ $file['path'] }}')">
                                    <i class="fas fa-check"></i> Select
                                </button>
                            </td>
                        </tr>
                    @endforeach

                    @if(empty($directories) && empty($files))
                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                <i class="fas fa-folder-open fa-2x mb-2"></i>
                                <br>No files or folders found
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade @if($showUploadModal) show @endif" style="display: @if($showUploadModal) block @else none @endif" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Audio Files</h5>
                <button type="button" class="btn-close" wire:click="$set('showUploadModal', false)"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Select Audio Files</label>
                    <input type="file" class="form-control" wire:model="uploadFiles" multiple accept=".mp3,.wav,.m4a,.flac,.aac,.ogg">
                    @error('uploadFiles.*') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                
                @if($uploadFiles)
                    <div class="alert alert-info">
                        <strong>Files to upload:</strong>
                        <ul class="mb-0">
                            @foreach($uploadFiles as $file)
                                <li>{{ $file->getClientOriginalName() }} ({{ $this->formatFileSize($file->getSize()) }})</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" wire:click="$set('showUploadModal', false)">Cancel</button>
                <button type="button" class="btn btn-primary" wire:click="uploadFiles" @if(!$uploadFiles) disabled @endif>
                    <i class="fas fa-upload"></i> Upload Files
                </button>
            </div>
        </div>
    </div>
</div>

<!-- New Folder Modal -->
<div class="modal fade @if($showNewFolderModal) show @endif" style="display: @if($showNewFolderModal) block @else none @endif" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Folder</h5>
                <button type="button" class="btn-close" wire:click="$set('showNewFolderModal', false)"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Folder Name</label>
                    <input type="text" class="form-control" wire:model="newFolderName" placeholder="Enter folder name">
                    @error('newFolderName') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" wire:click="$set('showNewFolderModal', false)">Cancel</button>
                <button type="button" class="btn btn-primary" wire:click="createFolder">
                    <i class="fas fa-folder-plus"></i> Create Folder
                </button>
            </div>
        </div>
    </div>
</div>

@if($showUploadModal || $showNewFolderModal)
    <div class="modal-backdrop fade show"></div>
@endif

@script
<script>
// Handle file selection events
document.addEventListener('livewire:init', () => {
    Livewire.on('file-selected', (filePath) => {
        // You can add custom handling here if needed
        console.log('File selected:', filePath);
    });
});
</script>
@endscript

</div>
