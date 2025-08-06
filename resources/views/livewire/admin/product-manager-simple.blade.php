<div>
    <h2>Product Management (Test)</h2>
    
    @if (session()->has('message'))
        <div class="alert alert-success">{{ session('message') }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3>Products</h3>
            <button wire:click="create" class="btn btn-primary">Add Product</button>
        </div>
        <div class="card-body">
            @if($showForm)
                <h4>{{ $editingProduct ? 'Edit Product' : 'Create New Product' }}</h4>
                <form wire:submit.prevent="save">
                    <div class="mb-3">
                        <label>Product Name</label>
                        <input type="text" wire:model="name" class="form-control">
                        @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label>Category</label>
                        <select wire:model="category_id" class="form-control">
                            <option value="">Select Category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label>Price</label>
                        <input type="number" wire:model="price" class="form-control" step="0.01">
                        @error('price') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    
                    <button type="submit" class="btn btn-success">Save</button>
                    <button type="button" wire:click="cancel" class="btn btn-secondary">Cancel</button>
                </form>
            @else
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($products as $product)
                                <tr>
                                    <td>{{ $product->name }}</td>
                                    <td>{{ $product->category->name ?? 'N/A' }}</td>
                                    <td>${{ number_format($product->price, 2) }}</td>
                                    <td>
                                        <button wire:click="edit({{ $product->id }})" class="btn btn-sm btn-primary">Edit</button>
                                        <button wire:click="delete({{ $product->id }})" class="btn btn-sm btn-danger">Delete</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">No products found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $products->links() }}
            @endif
        </div>
    </div>
</div>
