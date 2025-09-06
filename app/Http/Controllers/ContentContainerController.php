<?php

namespace App\Http\Controllers;

use App\Models\ContentContainer;
use App\Models\InstagramPost;
use Illuminate\Http\Request;
use Log;

class ContentContainerController extends Controller
{
    public function index()
    {
        $containers = ContentContainer::with('posts')->orderBy('created_at', 'desc')->get();
        return view('containers.index', compact('containers'));
    }

    public function show(ContentContainer $container)
    {
        $container->load('posts');
        return view('containers.show', compact('container'));
    }

    public function create()
    {
        return view('containers.create');
    }

    public function store(Request $request)
    {
        // Add detailed logging
        \Log::info('Container creation started', [
            'all_data' => $request->all(),
            'has_files' => $request->hasFile('posts'),
            'posts_count' => is_array($request->posts) ? count($request->posts) : 0
        ]);

        try {
            $request->validate([
                'name' => 'required|max:255',
                'description' => 'nullable|max:1000',
                'posts' => 'required|array|min:1',
                'posts.*.caption' => 'required|max:2200',
                'posts.*.images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB max
            ]);
            
            \Log::info('Validation passed');

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            throw $e;
        }

        \Log::info('Creating container', [
            'name' => $request->name,
            'description' => $request->description
        ]);

        $container = ContentContainer::create([
            'name' => $request->name,
            'description' => $request->description,
            'type' => 'instagram',
            'is_active' => true
        ]);

        \Log::info('Container created', ['container_id' => $container->id]);

        foreach ($request->posts as $index => $postData) {
            \Log::info('Processing post', [
                'index' => $index,
                'post_data' => $postData,
                'has_caption' => isset($postData['caption']),
                'has_images' => isset($postData['images'])
            ]);

            $imagePaths = [];
            $singleImagePath = null;
            
            // Handle image uploads
            if (isset($postData['images']) && is_array($postData['images'])) {
                foreach ($postData['images'] as $imageIndex => $image) {
                    if ($image && $image->isValid()) {
                        // Validate image aspect ratio
                        $validation = $this->validateImageAspectRatio($image);
                        if (!$validation['valid']) {
                            Log::warning('Image validation failed', [
                                'post_index' => $index,
                                'image_index' => $imageIndex,
                                'message' => $validation['message'],
                                'filename' => $image->getClientOriginalName()
                            ]);
                            
                            return redirect()->back()
                                ->withErrors(['posts.' . $index . '.images.' . $imageIndex => $validation['message']])
                                ->withInput();
                        }

                        $filename = time() . '_' . $index . '_' . $image->getClientOriginalName();
                        $path = $image->storeAs('posts', $filename, 'public');
                        $imagePaths[] = '/storage/' . $path;
                        
                        Log::info('Image uploaded successfully', [
                            'filename' => $filename,
                            'validation_message' => $validation['message']
                        ]);
                        
                        // Store the first image as the primary image path
                        if ($singleImagePath === null) {
                            $singleImagePath = 'posts/' . $filename;
                        }
                    }
                }
            }
            
            // If no images uploaded, use placeholder
            if (empty($imagePaths)) {
                $imagePaths = ['/images/placeholder.jpg'];
            }

            // Process hashtags
            $hashtags = [];
            if (isset($postData['hashtags']) && !empty($postData['hashtags'])) {
                $hashtags = array_map('trim', explode(' ', $postData['hashtags']));
                $hashtags = array_filter($hashtags); // Remove empty values
            }

            $post = InstagramPost::create([
                'content_container_id' => $container->id,
                'caption' => $postData['caption'],
                'images' => $imagePaths,
                'image_path' => $singleImagePath,
                'hashtags' => $hashtags,
                'post_type' => 'photo',
                'order' => $index + 1,
                'status' => 'draft'
            ]);

            \Log::info('Post created', ['post_id' => $post->id]);
        }

        \Log::info('Container creation completed successfully', ['container_id' => $container->id]);

        return redirect()->route('containers.show', $container)
            ->with('success', 'Container created successfully!');
    }

    public function edit(ContentContainer $container)
    {
        $container->load('posts');
        return view('containers.edit', compact('container'));
    }

    public function update(Request $request, ContentContainer $container)
    {
        $request->validate([
            'name' => 'required|max:255',
            'description' => 'nullable|max:1000',
            'new_posts.*.caption' => 'nullable|max:2200',
            'new_posts.*.images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
        ]);

        $container->update($request->only(['name', 'description']));

        // Handle new posts if any
        if ($request->has('new_posts')) {
            $maxOrder = $container->posts()->max('order') ?? 0;
            
            foreach ($request->new_posts as $index => $postData) {
                if (!empty($postData['caption'])) {
                    $imagePaths = [];
                    $singleImagePath = null;
                    
                    // Handle image uploads
                    if (isset($postData['images']) && is_array($postData['images'])) {
                        foreach ($postData['images'] as $image) {
                            if ($image && $image->isValid()) {
                                $filename = time() . '_' . $index . '_' . $image->getClientOriginalName();
                                $path = $image->storeAs('posts', $filename, 'public');
                                $imagePaths[] = '/storage/' . $path;
                                
                                // Store the first image as the primary image path
                                if ($singleImagePath === null) {
                                    $singleImagePath = 'posts/' . $filename;
                                }
                            }
                        }
                    }
                    
                    // If no images uploaded, use placeholder
                    if (empty($imagePaths)) {
                        $imagePaths = ['/images/placeholder.jpg'];
                    }

                    // Process hashtags
                    $hashtags = [];
                    if (isset($postData['hashtags']) && !empty($postData['hashtags'])) {
                        $hashtags = array_map('trim', explode(' ', $postData['hashtags']));
                        $hashtags = array_filter($hashtags);
                    }

                    InstagramPost::create([
                        'content_container_id' => $container->id,
                        'caption' => $postData['caption'],
                        'images' => $imagePaths,
                        'image_path' => $singleImagePath,
                        'hashtags' => $hashtags,
                        'post_type' => 'photo',
                        'order' => $maxOrder + $index + 1,
                        'status' => 'draft'
                    ]);
                }
            }
        }

        return redirect()->route('containers.show', $container)
            ->with('success', 'Container updated successfully!');
    }

    public function destroy(ContentContainer $container)
    {
        $container->delete();
        return redirect()->route('containers.index')
            ->with('success', 'Container deleted successfully!');
    }

    public function posts(ContentContainer $container)
    {
        $container->load('posts');
        return view('containers.posts', compact('container'));
    }

    public function updatePost(Request $request, InstagramPost $post)
    {
        $request->validate([
            'caption' => 'required|max:2200',
            'hashtags' => 'nullable|string',
        ]);

        // Process hashtags
        $hashtags = [];
        if (!empty($request->hashtags)) {
            $hashtags = array_map('trim', explode(' ', $request->hashtags));
            $hashtags = array_filter($hashtags);
        }

        $post->update([
            'caption' => $request->caption,
            'hashtags' => $hashtags,
        ]);

        return redirect()->route('containers.edit', $post->content_container_id)
            ->with('success', 'Post updated successfully!');
    }

    public function deletePost(InstagramPost $post)
    {
        $containerId = $post->content_container_id;
        
        // Delete associated images from storage
        if (is_array($post->images)) {
            foreach ($post->images as $imagePath) {
                if ($imagePath !== '/images/placeholder.jpg' && str_starts_with($imagePath, '/storage/')) {
                    $fullPath = public_path($imagePath);
                    if (file_exists($fullPath)) {
                        unlink($fullPath);
                    }
                }
            }
        }
        
        $post->delete();

        return redirect()->route('containers.edit', $containerId)
            ->with('success', 'Post deleted successfully!');
    }

    /**
     * Validate image aspect ratio for Instagram requirements
     */
    private function validateImageAspectRatio($image)
    {
        try {
            // Get image dimensions
            $imageInfo = getimagesize($image->getPathname());
            if (!$imageInfo) {
                return ['valid' => false, 'message' => 'Could not read image dimensions'];
            }

            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $aspectRatio = $width / $height;

            // Instagram requirements:
            // Single posts: 4:5 (0.8) to 1.91:1 (1.91)
            // Carousel posts: 4:5 (0.8) to 1:1 (1.0) - square is safest
            
            $minRatio = 0.8;  // 4:5 (portrait)
            $maxRatio = 1.91; // 1.91:1 (landscape)
            
            if ($aspectRatio < $minRatio) {
                return [
                    'valid' => false, 
                    'message' => 'Image too tall - Instagram requires aspect ratio between 4:5 and 1.91:1. Current: ' . round($aspectRatio, 2)
                ];
            }
            
            if ($aspectRatio > $maxRatio) {
                return [
                    'valid' => false, 
                    'message' => 'Image too wide - Instagram requires aspect ratio between 4:5 and 1.91:1. Current: ' . round($aspectRatio, 2)
                ];
            }

            // Check minimum dimensions
            if ($width < 320 || $height < 320) {
                return [
                    'valid' => false, 
                    'message' => 'Image too small - Instagram requires at least 320px width and height. Current: ' . $width . 'x' . $height
                ];
            }

            return ['valid' => true, 'message' => 'Image meets Instagram requirements'];

        } catch (Exception $e) {
            return ['valid' => false, 'message' => 'Error validating image: ' . $e->getMessage()];
        }
    }
}
