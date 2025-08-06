<?php

namespace App\Livewire;

use Livewire\Component;

class SimpleTest extends Component
{
    public $message = 'Hello from Livewire!';

    public function render()
    {
        return view('livewire.simple-test');
    }
}
