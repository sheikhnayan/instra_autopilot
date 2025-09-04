<?php

namespace App\Http\Controllers;

use App\Models\ContentContainer;
use App\Models\InstagramPost;
use Illuminate\Http\Request;

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
        $request->validate([
            'name' => 'required|max:255',
            'description' => 'nullable|max:1000',
            'posts' => 'required|array|min:1',
            'posts.*.caption' => 'required|max:2200',
            'posts.*.images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB max
        ]);

        $container = ContentContainer::create([
            'name' => $request->name,
            'description' => $request->description,
            'type' => 'instagram',
            'is_active' => true
        ]);

        foreach ($request->posts as $index => $postData) {
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
                $hashtags = array_filter($hashtags); // Remove empty values
            }

            InstagramPost::create([
                'content_container_id' => $container->id,
                'caption' => $postData['caption'],
                'images' => $imagePaths,
                'image_path' => $singleImagePath,
                'hashtags' => $hashtags,
                'post_type' => 'photo',
                'order' => $index + 1,
                'status' => 'draft'
            ]);
        }

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
}
