<?php

namespace App\Livewire\Admin;

use Livewire\Component;

class TtsTest extends Component
{
    public $message = 'TTS integration is working!';

    public function render()
    {
        return view('livewire.admin.tts-test')
            ->extends('adminlte::page')
            ->section('content');
    }
}
