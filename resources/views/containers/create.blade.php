@extends('layouts.app')

@section('title', 'Create Container - SocialScheduler')
@section('header', 'Create New Container')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow-sm border">
        <div class="p-6">
            <form action="{{ route('containers.store') }}" method="POST" id="container-form" enctype="multipart/form-data">
                @csrf
                
                <!-- Container Details -->
                <div class="mb-8">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Container Details</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Container Name</label>
                            <input type="text" id="name" name="name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="e.g., Summer Campaign 2024"
                                   value="{{ old('name') }}">
                            @error('name')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description (Optional)</label>
                            <input type="text" id="description" name="description"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Brief description of this container"
                                   value="{{ old('description') }}">
                            @error('description')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Posts Section -->
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Posts</h3>
                        <button type="button" id="add-post" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                            + Add Post
                        </button>
                    </div>

                    <div id="posts-container" class="space-y-6">
                        <!-- Posts will be added here dynamically -->
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex items-center justify-between">
                    <a href="{{ route('containers.index') }}" class="text-gray-600 hover:text-gray-800">
                        Cancel
                    </a>
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        Create Container
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
let postIndex = 0;

function addPost() {
    const postHtml = `
        <div class="post-item border border-gray-200 rounded-lg p-4" data-post-index="${postIndex}">
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-medium text-gray-900">Post ${postIndex + 1}</h4>
                <button type="button" class="remove-post text-red-600 hover:text-red-800 text-sm">Remove</button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Caption *</label>
                    <textarea name="posts[${postIndex}][caption]" rows="4" required
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Write your Instagram caption here..."></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Images (Optional)</label>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center image-upload-area">
                        <input type="file" multiple accept="image/*" class="hidden" name="posts[${postIndex}][images][]" id="images-${postIndex}">
                        <div class="upload-placeholder cursor-pointer" onclick="document.getElementById('images-${postIndex}').click()">
                            <svg class="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            <p class="text-sm text-gray-500">Click to upload images</p>
                            <p class="text-xs text-gray-400">PNG, JPG, GIF up to 10MB each</p>
                            <div class="mt-2 p-2 bg-blue-50 rounded-md">
                                <p class="text-xs text-blue-700 font-medium">📏 Instagram Requirements:</p>
                                <p class="text-xs text-blue-600">• Single image: 4:5 to 1.91:1 aspect ratio</p>
                                <p class="text-xs text-blue-600">• Multiple images: 4:5 to 1:1 (square) aspect ratio</p>
                                <p class="text-xs text-blue-600">• Minimum 320px wide, recommended 1080px</p>
                            </div>
                        </div>
                        <div class="image-preview-container hidden">
                            <div class="grid grid-cols-2 gap-2" id="preview-${postIndex}"></div>
                            <button type="button" class="mt-2 text-sm text-blue-600 hover:text-blue-800 change-images" onclick="document.getElementById('images-${postIndex}').click()">Change Images</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Hashtags (Optional)</label>
                <input type="text" name="posts[${postIndex}][hashtags]"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="#hashtag1 #hashtag2 #hashtag3">
                <p class="text-xs text-gray-500 mt-1">Separate hashtags with spaces</p>
            </div>
        </div>
    `;
    
    document.getElementById('posts-container').insertAdjacentHTML('beforeend', postHtml);
    
    // Add event listener for the file input
    const fileInput = document.getElementById(`images-${postIndex}`);
    fileInput.addEventListener('change', function(e) {
        handleImageUpload(e.target);
    });
    
    postIndex++;
    updatePostNumbers();
}

function updatePostNumbers() {
    const postItems = document.querySelectorAll('.post-item');
    postItems.forEach((item, index) => {
        const title = item.querySelector('h4');
        title.textContent = `Post ${index + 1}`;
    });
}

function handleImageUpload(input) {
    const uploadArea = input.closest('.image-upload-area');
    const placeholder = uploadArea.querySelector('.upload-placeholder');
    const previewContainer = uploadArea.querySelector('.image-preview-container');
    const previewGrid = previewContainer.querySelector('.grid');
    
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
    } else {
        // Show placeholder, hide preview
        placeholder.classList.remove('hidden');
        previewContainer.classList.add('hidden');
    }
}

// Event delegation for remove buttons
document.getElementById('posts-container').addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-post')) {
        e.preventDefault();
        const postItem = e.target.closest('.post-item');
        if (document.querySelectorAll('.post-item').length > 1) {
            postItem.remove();
            updatePostNumbers();
        } else {
            alert('You must have at least one post.');
        }
    }
});

// Add Post button event listener
document.getElementById('add-post').addEventListener('click', function(e) {
    e.preventDefault();
    addPost();
});

// Form validation before submit
document.getElementById('container-form').addEventListener('submit', function(e) {
    const posts = document.querySelectorAll('.post-item');
    if (posts.length === 0) {
        e.preventDefault();
        alert('Please add at least one post.');
        return false;
    }
    
    // Check if all posts have captions
    let hasEmptyCaptions = false;
    posts.forEach(post => {
        const caption = post.querySelector('textarea[name*="[caption]"]');
        if (!caption.value.trim()) {
            hasEmptyCaptions = true;
        }
    });
    
    if (hasEmptyCaptions) {
        e.preventDefault();
        alert('Please add captions to all posts.');
        return false;
    }
});

// Initialize with one post when page loads
document.addEventListener('DOMContentLoaded', function() {
    addPost();
});
</script>
@endpush
@endsection
