<!-- Story Preview Component -->
<div id="story-preview-modal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-75 flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900">📱 Story Preview</h3>
            <button type="button" onclick="closeStoryPreview()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <!-- Story Canvas -->
        <div class="relative mx-auto bg-gradient-to-br from-purple-500 to-pink-500 rounded-lg overflow-hidden" style="width: 200px; height: 356px;">
            <!-- Story Image -->
            <div id="story-image-preview" class="absolute inset-0 bg-gray-200 flex items-center justify-center text-gray-500 text-sm">
                Upload an image to preview
            </div>
            
            <!-- Story Stickers Overlay -->
            <div id="story-stickers-preview" class="absolute inset-0 pointer-events-none">
                <!-- Stickers will be positioned here -->
            </div>
            
            <!-- Story Duration Indicator -->
            <div class="absolute top-2 left-2 right-2">
                <div class="bg-white bg-opacity-20 rounded-full h-1">
                    <div id="story-duration-bar" class="bg-white rounded-full h-1 transition-all duration-1000" style="width: 0%"></div>
                </div>
            </div>
            
            <!-- Story Caption -->
            <div class="absolute bottom-4 left-4 right-4">
                <div id="story-caption-preview" class="text-white text-sm font-medium bg-black bg-opacity-30 rounded px-2 py-1">
                    Your caption will appear here...
                </div>
            </div>
        </div>
        
        <!-- Preview Controls -->
        <div class="mt-4 flex justify-center space-x-3">
            <button type="button" onclick="playStoryPreview()" 
                    class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors">
                ▶️ Play Preview
            </button>
            <button type="button" onclick="refreshStoryPreview()" 
                    class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                🔄 Refresh
            </button>
        </div>
        
        <div class="mt-3 text-center">
            <p class="text-xs text-gray-500">Duration: <span id="preview-duration">15</span> seconds</p>
        </div>
    </div>
</div>

<script>
// Story Preview Functions
function openStoryPreview(postIndex) {
    document.getElementById('story-preview-modal').classList.remove('hidden');
    updateStoryPreview(postIndex);
}

function closeStoryPreview() {
    document.getElementById('story-preview-modal').classList.add('hidden');
    stopStoryPreview();
}

function updateStoryPreview(postIndex) {
    const postItem = document.querySelector(`.post-item[data-post-index="${postIndex}"]`);
    if (!postItem) return;
    
    // Update caption
    const caption = postItem.querySelector('textarea[name*="[caption]"]').value;
    document.getElementById('story-caption-preview').textContent = caption || 'Your caption will appear here...';
    
    // Update duration
    const duration = postItem.querySelector('select[name*="[story_duration]"]')?.value || 15;
    document.getElementById('preview-duration').textContent = duration;
    
    // Update image if available
    const imagePreview = postItem.querySelector('.image-preview-container img');
    const storyImagePreview = document.getElementById('story-image-preview');
    
    if (imagePreview) {
        storyImagePreview.innerHTML = `<img src="${imagePreview.src}" class="w-full h-full object-cover">`;
    } else {
        storyImagePreview.innerHTML = 'Upload an image to preview';
    }
    
    // Update stickers
    updateStickersPreview(postIndex);
}

function updateStickersPreview(postIndex) {
    const stickersContainer = document.getElementById('story-stickers-preview');
    stickersContainer.innerHTML = '';
    
    const postItem = document.querySelector(`.post-item[data-post-index="${postIndex}"]`);
    const stickerItems = postItem.querySelectorAll('.sticker-item');
    
    stickerItems.forEach((stickerItem, index) => {
        const typeSelect = stickerItem.querySelector('.sticker-type-select');
        const type = typeSelect.value;
        
        if (!type) return;
        
        const posX = stickerItem.querySelector('input[name*="[position_x]"]').value || 0.5;
        const posY = stickerItem.querySelector('input[name*="[position_y]"]').value || 0.5;
        
        let stickerHtml = '';
        let stickerContent = '';
        
        switch(type) {
            case 'poll':
                const pollText = stickerItem.querySelector('input[name*="[text]"]')?.value || 'Poll Question';
                const option1 = stickerItem.querySelector('input[name*="[option1]"]')?.value || 'Yes';
                const option2 = stickerItem.querySelector('input[name*="[option2]"]')?.value || 'No';
                stickerContent = `
                    <div class="bg-white bg-opacity-90 rounded-lg p-2 text-xs text-black min-w-24">
                        <div class="font-medium mb-1">${pollText}</div>
                        <div class="space-y-1">
                            <div class="bg-blue-500 text-white px-2 py-1 rounded text-center">${option1}</div>
                            <div class="bg-gray-300 px-2 py-1 rounded text-center">${option2}</div>
                        </div>
                    </div>
                `;
                break;
                
            case 'question':
                const questionText = stickerItem.querySelector('input[name*="[text]"]')?.value || 'Ask me anything!';
                stickerContent = `
                    <div class="bg-gradient-to-r from-purple-400 to-pink-400 text-white px-3 py-2 rounded-full text-xs font-medium">
                        ❓ ${questionText}
                    </div>
                `;
                break;
                
            case 'mention':
                const username = stickerItem.querySelector('input[name*="[username]"]')?.value || 'username';
                stickerContent = `
                    <div class="bg-blue-500 text-white px-3 py-1 rounded-full text-xs font-medium">
                        @${username.replace('@', '')}
                    </div>
                `;
                break;
                
            case 'hashtag':
                const hashtag = stickerItem.querySelector('input[name*="[text]"]')?.value || 'hashtag';
                stickerContent = `
                    <div class="bg-indigo-500 text-white px-3 py-1 rounded-full text-xs font-medium">
                        #${hashtag.replace('#', '')}
                    </div>
                `;
                break;
                
            case 'location':
                const location = stickerItem.querySelector('input[name*="[location_name]"]')?.value || 'Location';
                stickerContent = `
                    <div class="bg-red-500 text-white px-3 py-1 rounded-full text-xs font-medium">
                        📍 ${location}
                    </div>
                `;
                break;
        }
        
        if (stickerContent) {
            stickerHtml = `
                <div class="absolute" style="left: ${posX * 100}%; top: ${posY * 100}%; transform: translate(-50%, -50%);">
                    ${stickerContent}
                </div>
            `;
            stickersContainer.insertAdjacentHTML('beforeend', stickerHtml);
        }
    });
}

function refreshStoryPreview() {
    const activePostIndex = document.querySelector('.post-item:last-child')?.dataset.postIndex || 0;
    updateStoryPreview(activePostIndex);
}

function playStoryPreview() {
    const duration = parseInt(document.getElementById('preview-duration').textContent) * 1000;
    const durationBar = document.getElementById('story-duration-bar');
    
    // Reset and animate duration bar
    durationBar.style.width = '0%';
    durationBar.style.transition = 'none';
    
    setTimeout(() => {
        durationBar.style.transition = `width ${duration}ms linear`;
        durationBar.style.width = '100%';
    }, 50);
    
    // Auto-close after duration
    setTimeout(() => {
        durationBar.style.width = '0%';
        durationBar.style.transition = 'none';
    }, duration + 100);
}

function stopStoryPreview() {
    const durationBar = document.getElementById('story-duration-bar');
    durationBar.style.width = '0%';
    durationBar.style.transition = 'none';
}
</script>
