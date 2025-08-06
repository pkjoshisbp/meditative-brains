<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Services\AudioSecurityService;
use Illuminate\Support\Facades\Storage;

class AudioFileBrowser extends Component
{
    use WithFileUploads;

    public $currentDirectory = '';
    public $uploadFiles = [];
    public $newFolderName = '';
    public $showUploadModal = false;
    public $showNewFolderModal = false;
    public $selectedFile = null;
    
    protected $listeners = ['refreshFiles' => '$refresh'];

    protected $rules = [
        'uploadFiles.*' => 'file|mimes:mp3,wav,m4a,flac,aac,ogg|max:102400', // 100MB max
        'newFolderName' => 'required|string|max:50|regex:/^[a-zA-Z0-9\-_\s]+$/'
    ];

    public function mount($directory = '')
    {
        $this->currentDirectory = $directory;
    }

    public function selectFile($filePath)
    {
        $this->selectedFile = $filePath;
        $this->dispatch('file-selected', $filePath);
    }

    public function navigateToDirectory($directory)
    {
        $this->currentDirectory = $directory;
    }

    public function goUp()
    {
        if ($this->currentDirectory) {
            $parts = explode('/', trim($this->currentDirectory, '/'));
            array_pop($parts);
            $this->currentDirectory = implode('/', $parts);
        }
    }

    public function uploadFiles()
    {
        $this->validate();

        $audioService = app(AudioSecurityService::class);
        $uploadedCount = 0;

        foreach ($this->uploadFiles as $file) {
            try {
                $audioService->uploadOriginalFile($file, $this->currentDirectory);
                $uploadedCount++;
            } catch (\Exception $e) {
                session()->flash('error', 'Failed to upload ' . $file->getClientOriginalName() . ': ' . $e->getMessage());
            }
        }

        if ($uploadedCount > 0) {
            session()->flash('message', "Successfully uploaded {$uploadedCount} file(s)");
        }

        $this->reset(['uploadFiles', 'showUploadModal']);
    }

    public function createFolder()
    {
        $this->validate(['newFolderName' => $this->rules['newFolderName']]);

        $targetPath = 'audio/original';
        if ($this->currentDirectory) {
            $targetPath .= '/' . trim($this->currentDirectory, '/');
        }
        $targetPath .= '/' . $this->newFolderName;

        if (Storage::disk('local')->exists($targetPath)) {
            session()->flash('error', 'Folder already exists');
            return;
        }

        Storage::disk('local')->makeDirectory($targetPath);
        session()->flash('message', 'Folder created successfully');
        
        $this->reset(['newFolderName', 'showNewFolderModal']);
    }

    public function formatFileSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    public function render()
    {
        $audioService = app(AudioSecurityService::class);
        $fileData = $audioService->getAvailableAudioFiles($this->currentDirectory);

        return view('livewire.admin.audio-file-browser', [
            'files' => $fileData['files'],
            'directories' => $fileData['directories'],
            'currentPath' => $fileData['current_path']
        ]);
    }
}
