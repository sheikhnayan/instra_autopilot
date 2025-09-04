@extends('layouts.app')

@section('title', 'Edit Container - SocialScheduler')
@section('header', 'Edit Container: ' . $container->name)

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow-sm border">
        <div class="p-6">
            <form action="{{ route('containers.update', $container) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                
                <!-- Container Details -->
                <div class="mb-8">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Container Details</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Container Name</label>
                            <input type="text" id="name" name="name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="e.g., Summer Campaign 2024"
                                   value="{{ old('name', $container->name) }}">
                            @error('name')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description (Optional)</label>
                            <input type="text" id="description" name="description"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Brief description of this container"
                                   value="{{ old('description', $container->description) }}">
                            @error('description')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Current Posts -->
                @if($container->posts->count() > 0)
                <div class="mb-8">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Current Posts ({{ $container->posts->count() }})</h3>
                    <div class="space-y-4">
                        @foreach($container->posts as $post)
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center overflow-hidden">
                                        @if(is_array($post->images) && count($post->images) > 0 && $post->images[0] !== '/images/placeholder.jpg')
                                            <img src="{{ asset($post->images[0]) }}" alt="Post image" class="w-full h-full object-cover">
                                        @else
                                            <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                                            </svg>
                                        @endif
                                    </div>
                                    @if(is_array($post->images) && count($post->images) > 1)
                                    <p class="text-xs text-gray-500 mt-1 text-center">+{{ count($post->images) - 1 }}</p>
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-medium text-gray-900 mb-2">Post {{ $post->order }}</h4>
                                    <p class="text-sm text-gray-600">{{ Str::limit($post->caption, 100) }}</p>
                                    @if(is_array($post->hashtags) && count($post->hashtags) > 0)
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach(array_slice($post->hashtags, 0, 3) as $hashtag)
                                        <span class="text-xs bg-blue-50 text-blue-600 px-2 py-1 rounded">{{ $hashtag }}</span>
                                        @endforeach
                                        @if(count($post->hashtags) > 3)
                                        <span class="text-xs text-gray-500">+{{ count($post->hashtags) - 3 }} more</span>
                                        @endif
                                    </div>
                                    @endif
                                </div>
                                <div class="flex space-x-2">
                                    <button type="button" class="text-sm text-blue-600 hover:text-blue-800" onclick="openEditModal({{ $post->id }}, '{{ addslashes($post->caption) }}', '{{ implode(' ', $post->hashtags ?? []) }}')">Edit</button>
                                    <form action="{{ route('posts.delete', $post) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this post?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm text-red-600 hover:text-red-800">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Add New Posts Section -->
                <div class="mb-8">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Add New Posts</h3>
                    <button type="button" id="add-new-post" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors mb-4">
                        + Add Post to Container
                    </button>
                    <div id="new-posts-container" class="space-y-4"></div>
                </div>

                                {{-- @endif --}}

                <!-- Add New Posts -->
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Add New Posts</h3>
                        <button type="button" id="add-post" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                            + Add Post
                        </button>
                    </div>

                    <div id="new-posts-container" class="space-y-6">
                        <!-- New posts will be added here dynamically -->
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex items-center justify-between">
                    <a href="{{ route('containers.show', $container) }}" class="text-gray-600 hover:text-gray-800">
                        Cancel
                    </a>
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        Update Container
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Post Modal -->
    <div id="editPostModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Post</h3>
                <form id="editPostForm" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="mb-4">
                        <label for="edit_caption" class="block text-sm font-medium text-gray-700 mb-2">Caption</label>
                        <textarea id="edit_caption" name="caption" rows="4" required
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Write your Instagram caption here..."></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label for="edit_hashtags" class="block text-sm font-medium text-gray-700 mb-2">Hashtags</label>
                        <input type="text" id="edit_hashtags" name="hashtags"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="#hashtag1 #hashtag2 #hashtag3">
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <button type="button" onclick="closeEditModal()" class="text-gray-600 hover:text-gray-800">
                            Cancel
                        </button>
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                            Update Post
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
let newPostIndex = 0;

function addNewPost() {
    const postHtml = `
        <div class="new-post-item border border-green-200 rounded-lg p-4 bg-green-50">
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-medium text-gray-900">New Post ${newPostIndex + 1}</h4>
                <button type="button" class="remove-new-post text-red-600 hover:text-red-800 text-sm">Remove</button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Caption</label>
                    <textarea name="new_posts[${newPostIndex}][caption]" rows="4" required
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Write your Instagram caption here..."></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Images</label>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center image-upload-area" data-post-index="${newPostIndex}">
                        <input type="file" multiple accept="image/*" class="hidden image-input" name="new_posts[${newPostIndex}][images][]">
                        <div class="upload-placeholder">
                            <svg class="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            <p class="text-sm text-gray-500">Click to upload images</p>
                            <p class="text-xs text-gray-400">PNG, JPG, GIF up to 10MB each</p>
                        </div>
                        <div class="image-preview-container hidden">
                            <div class="grid grid-cols-2 gap-2 image-preview"></div>
                            <button type="button" class="mt-2 text-sm text-blue-600 hover:text-blue-800 change-images">Change Images</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Hashtags (Optional)</label>
                <input type="text" name="new_posts[${newPostIndex}][hashtags]"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="#hashtag1 #hashtag2 #hashtag3">
            </div>
        </div>
    `;
    
    document.getElementById('new-posts-container').insertAdjacentHTML('beforeend', postHtml);
    newPostIndex++;
}

// Handle new post clicks
document.getElementById('add-new-post').addEventListener('click', addNewPost);

document.getElementById('new-posts-container').addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-new-post')) {
        e.target.closest('.new-post-item').remove();
    }
    
    // Handle upload area clicks
    if (e.target.closest('.upload-placeholder') || e.target.closest('.change-images')) {
        const uploadArea = e.target.closest('.image-upload-area');
        const fileInput = uploadArea.querySelector('input[type="file"]');
        fileInput.click();
    }
});

// Handle file selection
document.getElementById('new-posts-container').addEventListener('change', function(e) {
    if (e.target.type === 'file') {
        handleImageUpload(e.target);
    }
});

function handleImageUpload(input) {
    const uploadArea = input.closest('.image-upload-area');
    const placeholder = uploadArea.querySelector('.upload-placeholder');
    const previewContainer = uploadArea.querySelector('.image-preview-container');
    const previewGrid = previewContainer.querySelector('.image-preview');
    
    if (input.files && input.files.length > 0) {
        // Clear previous previews
        previewGrid.innerHTML = '';
        
        // Show preview container, hide placeholder
        placeholder.classList.add('hidden');
        previewContainer.classList.remove('hidden');
        
        // Create previews for each file
        Array.from(input.files).forEach((file, index) => {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewDiv = document.createElement('div');
                    previewDiv.className = 'relative group';
                    previewDiv.innerHTML = `
                        <img src="${e.target.result}" class="w-full h-20 object-cover rounded-lg">
                        <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center">
                            <span class="text-white text-xs">${file.name}</span>
                        </div>
                    `;
                    previewGrid.appendChild(previewDiv);
                };
                reader.readAsDataURL(file);
            }
        });
    }
}

// Modal functions for editing posts
function openEditModal(postId, caption, hashtags) {
    document.getElementById('edit_caption').value = caption;
    document.getElementById('edit_hashtags').value = hashtags;
    document.getElementById('editPostForm').action = `/posts/${postId}`;
    document.getElementById('editPostModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editPostModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('editPostModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});
</script>
@endpush
@endsection
