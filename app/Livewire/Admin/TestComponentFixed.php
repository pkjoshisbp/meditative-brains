<?php

namespace App\Livewire\Admin;

use App\Livewire\AdminComponent;

class TestComponentFixed extends AdminComponent
{
    public $message = 'Hello from Admin Livewire!';
    
    protected string $pageTitle = 'Test Component';
    protected string $pageHeader = 'Test Admin Component';
    
    protected function getViewData(): array
    {
        return [
            'message' => $this->message,
        ];
    }
}
