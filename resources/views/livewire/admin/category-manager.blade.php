<div>
    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('message') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <div class="row">
                <div class="col-md-6">
                    <h3 class="card-title">Categories</h3>
                </div>
                <div class="col-md-6 text-right">
                    <button wire:click="create" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Category
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <!-- Search -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <input wire:model.live="search" type="text" class="form-control" placeholder="Search categories...">
                </div>
            </div>

            <!-- Categories Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Parent</th>
                            <th>Status</th>
                            <th>Sort Order</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $category)
                            <tr>
                                <td>{{ $category->id }}</td>
                                <td>{{ $category->name }}</td>
                                <td>{{ $category->parent ? $category->parent->name : '-' }}</td>
                                <td>
                                    <span class="badge badge-{{ $category->is_active ? 'success' : 'secondary' }}">
                                        {{ $category->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>{{ $category->sort_order }}</td>
                                <td>
                                    <button wire:click="edit({{ $category->id }})" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button wire:click="delete({{ $category->id }})" 
                                            onclick="return confirm('Are you sure?')" 
                                            class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">No categories found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            {{ $categories->links() }}
        </div>
    </div>

    <!-- Create/Edit Form Modal -->
    @if($showForm)
    <div class="modal fade show" style="display: block;" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        {{ $editingCategory ? 'Edit Category' : 'Create Category' }}
                    </h5>
                    <button type="button" class="close" wire:click="cancel">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="save">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">Name *</label>
                                    <input wire:model="name" type="text" class="form-control @error('name') is-invalid @enderror" id="name">
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="parent_id">Parent Category</label>
                                    <select wire:model="parent_id" class="form-control @error('parent_id') is-invalid @enderror" id="parent_id">
                                        <option value="">-- Select Parent --</option>
                                        @foreach($parentCategories as $parent)
                                            <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('parent_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea wire:model="description" class="form-control @error('description') is-invalid @enderror" id="description" rows="3"></textarea>
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="sort_order">Sort Order</label>
                                    <input wire:model="sort_order" type="number" class="form-control @error('sort_order') is-invalid @enderror" id="sort_order">
                                    @error('sort_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <div class="form-check">
                                        <input wire:model="is_active" type="checkbox" class="form-check-input" id="is_active">
                                        <label class="form-check-label" for="is_active">Active</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SEO Fields -->
                        <h5>SEO Settings</h5>
                        <div class="form-group">
                            <label for="meta_title">Meta Title</label>
                            <input wire:model="meta_title" type="text" class="form-control @error('meta_title') is-invalid @enderror" id="meta_title">
                            @error('meta_title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-group">
                            <label for="meta_description">Meta Description</label>
                            <textarea wire:model="meta_description" class="form-control @error('meta_description') is-invalid @enderror" id="meta_description" rows="2"></textarea>
                            @error('meta_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-group">
                            <label for="meta_keywords">Meta Keywords</label>
                            <input wire:model="meta_keywords" type="text" class="form-control @error('meta_keywords') is-invalid @enderror" id="meta_keywords" placeholder="keyword1, keyword2, keyword3">
                            @error('meta_keywords') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="cancel">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="save">
                        {{ $editingCategory ? 'Update' : 'Create' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    @endif

<style>
    .modal {
        background-color: rgba(0,0,0,0.5);
    }
</style>
</div>
