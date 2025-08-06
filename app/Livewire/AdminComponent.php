<?php

namespace App\Livewire;

use Livewire\Component;

abstract class AdminComponent extends Component
{
    /**
     * The page title for AdminLTE
     */
    protected string $pageTitle = 'Admin Panel';
    
    /**
     * The content header for AdminLTE
     */
    protected string $pageHeader = 'Admin Panel';
    
    /**
     * The layout to use for admin components
     */
    protected string $layout = 'components.layouts.admin';
    
    /**
     * Override this method to set custom title and header
     */
    protected function getLayoutData(): array
    {
        return [
            'title' => $this->pageTitle,
            'header' => $this->pageHeader,
        ];
    }
    
    /**
     * Render the component with admin layout
     */
    public function render()
    {
        $view = $this->getView();
        $data = $this->getViewData();
        $layoutData = $this->getLayoutData();
        
        return view($view, $data)->layout($this->layout, $layoutData);
    }
    
    /**
     * Get the view name - override this or implement renderView() method
     */
    protected function getView(): string
    {
        // Default convention: convert class name to view path
        $className = class_basename($this);
        $viewName = str($className)->snake()->replace('_', '-');
        
        return 'livewire.admin.' . $viewName;
    }
    
    /**
     * Get data to pass to the view - override this method
     */
    protected function getViewData(): array
    {
        return [];
    }
    
    /**
     * Allow child classes to override the entire render method if needed
     */
    protected function renderView()
    {
        return null;
    }
}
