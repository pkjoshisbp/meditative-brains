<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin Layout Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for the admin panel layout and
    | default settings for admin components.
    |
    */

    'layout' => [
        // Default layout for admin components
        'default' => 'components.layouts.admin',
        
        // Default page title
        'default_title' => 'Admin Panel - Meditative Brains',
        
        // Default page header
        'default_header' => 'Admin Panel',
        
        // Whether to automatically use admin layout for components in Admin namespace
        'auto_layout' => true,
    ],

    'sidebar' => [
        // Admin sidebar menu items
        'menu' => [
            [
                'text' => 'Dashboard',
                'route' => 'admin.dashboard',
                'icon' => 'fas fa-tachometer-alt',
            ],
            [
                'text' => 'Products',
                'route' => 'admin.products',
                'icon' => 'fas fa-box',
            ],
            [
                'text' => 'Categories',
                'route' => 'admin.categories',
                'icon' => 'fas fa-folder',
            ],
            [
                'text' => 'File Browser',
                'route' => 'admin.files',
                'icon' => 'fas fa-file-audio',
            ],
        ],
    ],
];
