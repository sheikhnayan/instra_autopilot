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

                    <!-- Image Requirements Info -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h4 class="text-sm font-medium text-blue-800">Instagram Content Requirements</h4>
                                <div class="mt-1 text-sm text-blue-700">
                                    <ul class="list-disc list-inside space-y-1">
                                        <li><strong>Posts:</strong> Aspect ratio between 4:5 (portrait) and 1.91:1 (landscape)</li>
                                        <li><strong>Stories:</strong> 9:16 aspect ratio (1080x1920px recommended)</li>
                                        <li><strong>Carousel Posts:</strong> Square (1:1) images work best for multiple images</li>
                                        <li><strong>Minimum Size:</strong> At least 320px width and height</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
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

<!-- Include Story Preview Component -->
@include('components.story-preview')

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
            
            <!-- Post Type Selection -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Content Type *</label>
                <div class="flex space-x-4">
                    <label class="flex items-center">
                        <input type="radio" name="posts[${postIndex}][post_type]" value="photo" checked
                               class="post-type-radio mr-2" data-post-index="${postIndex}">
                        <span class="text-sm text-gray-700">📷 Regular Post</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="posts[${postIndex}][post_type]" value="story"
                               class="post-type-radio mr-2" data-post-index="${postIndex}">
                        <span class="text-sm text-gray-700">📱 Instagram Story</span>
                    </label>
                </div>
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
                        <input type="file" multiple accept="image/*" class="hidden" name="posts[${postIndex}][images][]" id="images-${postIndex}" onchange="handleImageUpload(this)">
                        <div class="upload-placeholder cursor-pointer" onclick="document.getElementById('images-${postIndex}').click()">
                            <svg class="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            <p class="text-sm text-gray-500">Click to upload images</p>
                            <p class="text-xs text-gray-400">PNG, JPG, GIF up to 10MB each</p>
                            <div class="mt-2 p-2 bg-blue-50 rounded-md image-requirements" id="requirements-${postIndex}">
                                <p class="text-xs text-blue-700 font-medium">📏 Regular Post Requirements:</p>
                                <p class="text-xs text-blue-600">• Single image: 4:5 to 1.91:1 aspect ratio</p>
                                <p class="text-xs text-blue-600">• Multiple images: 4:5 to 1:1 (square) aspect ratio</p>
                                <p class="text-xs text-blue-600">• Minimum 320px wide, recommended 1080px</p>
                            </div>
                        </div>
                        <div class="image-preview-container hidden">
                            <div class="mb-2">
                                <p class="text-sm font-medium text-gray-700">📋 Image Order</p>
                                <p class="text-xs text-gray-500">Drag to reorder images for posting sequence</p>
                            </div>
                            <div class="sortable-images grid grid-cols-2 gap-2" id="preview-${postIndex}"></div>
                            <input type="hidden" name="posts[${postIndex}][image_order]" id="image-order-${postIndex}" value="">
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
            
            <!-- Story-specific options (hidden by default) -->
            <div class="story-options mt-4 hidden" id="story-options-${postIndex}">
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                    <h5 class="text-sm font-medium text-purple-800 mb-3">📱 Instagram Story Options</h5>
                    
                    <!-- Story Duration -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Story Duration (seconds)</label>
                        <select name="posts[${postIndex}][story_duration]" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            <option value="10">10 seconds</option>
                            <option value="15" selected>15 seconds (recommended)</option>
                            <option value="20">20 seconds</option>
                            <option value="30">30 seconds</option>
                        </select>
                    </div>
                    
                    <!-- Interactive Stickers -->
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-medium text-gray-700">Interactive Elements</label>
                            <button type="button" onclick="openStoryPreview(${postIndex})" 
                                    class="text-purple-600 hover:text-purple-800 text-sm font-medium">
                                👁️ Preview Story
                            </button>
                        </div>
                        <div class="space-y-3" id="stickers-container-${postIndex}">
                            <!-- Stickers will be added here -->
                        </div>
                        <button type="button" class="mt-2 bg-purple-600 text-white px-3 py-1 rounded text-sm hover:bg-purple-700 transition-colors"
                                onclick="addSticker(${postIndex})">
                            + Add Interactive Element
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('posts-container').insertAdjacentHTML('beforeend', postHtml);
    
    // Add event listener for the file input
    const fileInput = document.getElementById(`images-${postIndex}`);
    fileInput.addEventListener('change', function(e) {
        handleImageUpload(e.target);
    });
    
    // Add event listener for post type radio buttons
    const postTypeRadios = document.querySelectorAll(`input[name="posts[${postIndex}][post_type]"]`);
    postTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            toggleStoryOptions(postIndex, this.value);
        });
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

// Toggle story options based on post type
function toggleStoryOptions(postIndex, postType) {
    const storyOptions = document.getElementById(`story-options-${postIndex}`);
    const imageRequirements = document.getElementById(`requirements-${postIndex}`);
    
    if (postType === 'story') {
        storyOptions.classList.remove('hidden');
        imageRequirements.innerHTML = `
            <p class="text-xs text-purple-700 font-medium">📱 Instagram Story Requirements:</p>
            <p class="text-xs text-purple-600">• Aspect ratio: 9:16 (vertical)</p>
            <p class="text-xs text-purple-600">• Recommended: 1080x1920px</p>
            <p class="text-xs text-purple-600">• Single image only for stories</p>
        `;
        
        // Update file input to only accept single file for stories
        const fileInput = document.getElementById(`images-${postIndex}`);
        fileInput.removeAttribute('multiple');
        
    } else {
        storyOptions.classList.add('hidden');
        imageRequirements.innerHTML = `
            <p class="text-xs text-blue-700 font-medium">📏 Regular Post Requirements:</p>
            <p class="text-xs text-blue-600">• Single image: 4:5 to 1.91:1 aspect ratio</p>
            <p class="text-xs text-blue-600">• Multiple images: 4:5 to 1:1 (square) aspect ratio</p>
            <p class="text-xs text-blue-600">• Minimum 320px wide, recommended 1080px</p>
        `;
        
        // Re-enable multiple file selection for regular posts
        const fileInput = document.getElementById(`images-${postIndex}`);
        fileInput.setAttribute('multiple', 'multiple');
    }
}

// Add interactive sticker to story
let stickerIndex = 0;
function addSticker(postIndex) {
    const stickersContainer = document.getElementById(`stickers-container-${postIndex}`);
    const stickerHtml = `
        <div class="sticker-item border border-purple-200 rounded-lg p-3 bg-white" data-sticker-index="${stickerIndex}">
            <div class="flex items-center justify-between mb-2">
                <select name="posts[${postIndex}][stickers][${stickerIndex}][type]" 
                        class="sticker-type-select text-sm border border-gray-300 rounded px-2 py-1"
                        onchange="updateStickerOptions(${postIndex}, ${stickerIndex}, this.value)">
                    <option value="">Select Type</option>
                    <option value="poll">🗳️ Poll</option>
                    <option value="question">❓ Question</option>
                    <option value="mention">👤 Mention</option>
                    <option value="hashtag"># Hashtag</option>
                    <option value="location">📍 Location</option>
                </select>
                <button type="button" onclick="removeSticker(this)" 
                        class="text-red-600 hover:text-red-800 text-sm">Remove</button>
            </div>
            
            <div class="sticker-options" id="sticker-options-${postIndex}-${stickerIndex}">
                <p class="text-xs text-gray-500">Select a sticker type above to configure options</p>
            </div>
            
            <!-- Position controls -->
            <div class="mt-2 grid grid-cols-2 gap-2">
                <div>
                    <label class="text-xs text-gray-600">X Position (0.0-1.0)</label>
                    <input type="number" name="posts[${postIndex}][stickers][${stickerIndex}][position_x]" 
                           min="0" max="1" step="0.1" value="0.5"
                           class="w-full text-xs px-2 py-1 border border-gray-300 rounded">
                </div>
                <div>
                    <label class="text-xs text-gray-600">Y Position (0.0-1.0)</label>
                    <input type="number" name="posts[${postIndex}][stickers][${stickerIndex}][position_y]" 
                           min="0" max="1" step="0.1" value="0.5"
                           class="w-full text-xs px-2 py-1 border border-gray-300 rounded">
                </div>
            </div>
        </div>
    `;
    
    stickersContainer.insertAdjacentHTML('beforeend', stickerHtml);
    stickerIndex++;
}

// Update sticker options based on type
function updateStickerOptions(postIndex, stickerIndex, stickerType) {
    const optionsContainer = document.getElementById(`sticker-options-${postIndex}-${stickerIndex}`);
    let optionsHtml = '';
    
    switch(stickerType) {
        case 'poll':
            optionsHtml = `
                <div class="space-y-2">
                    <div>
                        <label class="text-xs text-gray-600">Poll Question</label>
                        <input type="text" name="posts[${postIndex}][stickers][${stickerIndex}][text]" 
                               placeholder="What's your question?"
                               class="w-full text-sm px-2 py-1 border border-gray-300 rounded">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs text-gray-600">Option 1</label>
                            <input type="text" name="posts[${postIndex}][stickers][${stickerIndex}][option1]" 
                                   placeholder="Yes" value="Yes"
                                   class="w-full text-sm px-2 py-1 border border-gray-300 rounded">
                        </div>
                        <div>
                            <label class="text-xs text-gray-600">Option 2</label>
                            <input type="text" name="posts[${postIndex}][stickers][${stickerIndex}][option2]" 
                                   placeholder="No" value="No"
                                   class="w-full text-sm px-2 py-1 border border-gray-300 rounded">
                        </div>
                    </div>
                </div>
            `;
            break;
            
        case 'question':
            optionsHtml = `
                <div>
                    <label class="text-xs text-gray-600">Question Prompt</label>
                    <input type="text" name="posts[${postIndex}][stickers][${stickerIndex}][text]" 
                           placeholder="Ask me anything!"
                           class="w-full text-sm px-2 py-1 border border-gray-300 rounded">
                </div>
            `;
            break;
            
        case 'mention':
            optionsHtml = `
                <div>
                    <label class="text-xs text-gray-600">Username (without @)</label>
                    <input type="text" name="posts[${postIndex}][stickers][${stickerIndex}][username]" 
                           placeholder="username"
                           class="w-full text-sm px-2 py-1 border border-gray-300 rounded">
                </div>
            `;
            break;
            
        case 'hashtag':
            optionsHtml = `
                <div>
                    <label class="text-xs text-gray-600">Hashtag (without #)</label>
                    <input type="text" name="posts[${postIndex}][stickers][${stickerIndex}][text]" 
                           placeholder="hashtag"
                           class="w-full text-sm px-2 py-1 border border-gray-300 rounded">
                </div>
            `;
            break;
            
        case 'location':
            optionsHtml = `
                <div>
                    <label class="text-xs text-gray-600">Location Name</label>
                    <input type="text" name="posts[${postIndex}][stickers][${stickerIndex}][location_name]" 
                           placeholder="New York, NY"
                           class="w-full text-sm px-2 py-1 border border-gray-300 rounded">
                </div>
            `;
            break;
            
        default:
            optionsHtml = '<p class="text-xs text-gray-500">Select a sticker type above to configure options</p>';
    }
    
    optionsContainer.innerHTML = optionsHtml;
}

// Remove sticker
function removeSticker(button) {
    button.closest('.sticker-item').remove();
}

// Image handling and ordering functionality
function handleImageUpload(input) {
    const files = Array.from(input.files);
    const postIndex = input.id.replace('images-', '');
    const preview = document.getElementById(`preview-${postIndex}`);
    const uploadPlaceholder = input.closest('.image-upload-area').querySelector('.upload-placeholder');
    const previewContainer = input.closest('.image-upload-area').querySelector('.image-preview-container');
    const orderInput = document.getElementById(`image-order-${postIndex}`);
    
    if (files.length > 0) {
        uploadPlaceholder.classList.add('hidden');
        previewContainer.classList.remove('hidden');
        preview.innerHTML = '';
        
        // Set initial order
        const initialOrder = files.map((_, index) => index);
        orderInput.value = initialOrder.join(',');
        
        files.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const imgElement = document.createElement('div');
                imgElement.className = 'sortable-image relative bg-gray-100 rounded-lg overflow-hidden cursor-move border-2 border-transparent hover:border-blue-300 transition-colors';
                imgElement.draggable = true;
                imgElement.dataset.originalIndex = index;
                imgElement.innerHTML = `
                    <img src="${e.target.result}" alt="Preview" class="w-full h-24 object-cover">
                    <div class="absolute top-1 right-1 bg-black bg-opacity-50 text-white text-xs px-1 rounded">
                        ${index + 1}
                    </div>
                    <div class="absolute inset-0 bg-black bg-opacity-0 hover:bg-opacity-20 transition-opacity flex items-center justify-center">
                        <svg class="w-4 h-4 text-white opacity-0 hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                        </svg>
                    </div>
                `;
                
                // Add drag event listeners
                imgElement.addEventListener('dragstart', handleDragStart);
                imgElement.addEventListener('dragover', handleDragOver);
                imgElement.addEventListener('drop', handleDrop);
                imgElement.addEventListener('dragend', handleDragEnd);
                
                preview.appendChild(imgElement);
            };
            reader.readAsDataURL(file);
        });
    } else {
        uploadPlaceholder.classList.remove('hidden');
        previewContainer.classList.add('hidden');
        orderInput.value = '';
    }
}

// Drag and drop functionality for image ordering
let draggedElement = null;

function handleDragStart(e) {
    draggedElement = this;
    this.style.opacity = '0.5';
    e.dataTransfer.effectAllowed = 'move';
}

function handleDragOver(e) {
    if (e.preventDefault) {
        e.preventDefault();
    }
    e.dataTransfer.dropEffect = 'move';
    return false;
}

function handleDrop(e) {
    if (e.stopPropagation) {
        e.stopPropagation();
    }
    
    if (draggedElement !== this) {
        const preview = this.parentNode;
        const draggedIndex = Array.from(preview.children).indexOf(draggedElement);
        const targetIndex = Array.from(preview.children).indexOf(this);
        
        if (draggedIndex < targetIndex) {
            preview.insertBefore(draggedElement, this.nextSibling);
        } else {
            preview.insertBefore(draggedElement, this);
        }
        
        updateImageOrder(preview);
    }
    
    return false;
}

function handleDragEnd(e) {
    this.style.opacity = '';
    draggedElement = null;
}

function updateImageOrder(preview) {
    const images = preview.querySelectorAll('.sortable-image');
    const postIndex = preview.id.replace('preview-', '');
    const orderInput = document.getElementById(`image-order-${postIndex}`);
    const order = [];
    
    images.forEach((img, index) => {
        const numberBadge = img.querySelector('.absolute.top-1.right-1');
        numberBadge.textContent = index + 1;
        order.push(parseInt(img.dataset.originalIndex));
    });
    
    // Store the order in the hidden field
    orderInput.value = order.join(',');
    
    console.log(`Post ${postIndex} image order:`, order);
}
</script>
@endpush
@endsection
