<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstagramAccountController;
use App\Http\Controllers\InstagramAuthController;
use App\Http\Controllers\ContentContainerController;
use App\Http\Controllers\ScheduleController;
use App\Services\InstagramApiService;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Instagram Authentication
Route::get('/auth/instagram', [InstagramAuthController::class, 'redirectToInstagram'])->name('instagram.auth');
Route::get('/auth/instagram/callback', [InstagramAuthController::class, 'handleInstagramCallback'])->name('instagram.callback');
Route::post('/accounts/{account}/disconnect', [InstagramAuthController::class, 'disconnect'])->name('instagram.disconnect');
Route::post('/accounts/{account}/refresh', [InstagramAuthController::class, 'refreshToken'])->name('instagram.refresh');

// Test route for Instagram API
Route::get('/test-instagram-api', function(InstagramApiService $service) {
    $authUrl = $service->getAuthorizationUrl();
    return response()->json([
        'status' => 'Instagram API Service is working',
        'auth_url' => $authUrl,
        'config' => [
            'client_id' => config('services.instagram.client_id') ? 'Set' : 'Not Set',
            'client_secret' => config('services.instagram.client_secret') ? 'Set' : 'Not Set',
            'redirect_uri' => config('services.instagram.redirect_uri'),
        ]
    ]);
})->name('test.instagram.api');

// Test route for immediate Instagram posting
Route::get('/test-instagram-post', function(InstagramApiService $service) {
    try {
        // Get the first active Instagram account
        $account = App\Models\InstagramAccount::where('is_active', true)->first();
        
        if (!$account) {
            return response()->json([
                'status' => 'error',
                'message' => 'No active Instagram account found'
            ], 404);
        }
        
        // Get the first draft post
        $post = App\Models\InstagramPost::latest()->first();
        
        if (!$post) {
            return response()->json([
                'status' => 'error',
                'message' => 'No draft posts found'
            ], 404);
        }
        
        // Test the posting process
        $result = [
            'status' => 'testing',
            'account' => [
                'id' => $account->id,
                'username' => $account->username,
                'has_access_token' => !empty($account->access_token),
                'has_page_token' => !empty($account->facebook_page_access_token),
                'business_account_id' => $account->instagram_business_account_id,
            ],
            'post' => [
                'id' => $post->id,
                'caption' => substr($post->caption, 0, 100) . '...',
                'image_path' => $post->image_path,
                'status' => $post->status,
            ],
            'tests' => []
        ];
        
        // Test 1: Token validation
        try {
            $tokenValid = $service->validateToken($account->access_token);
            $result['tests']['token_validation'] = $tokenValid ? 'PASS' : 'FAIL';
        } catch (Exception $e) {
            $result['tests']['token_validation'] = 'ERROR: ' . $e->getMessage();
        }
        
        // Test 2: Image URL accessibility
        $imageUrl = config('app.url') . '/storage/' . $post->image_path;
        $result['tests']['image_url'] = $imageUrl;
        
        // Check if image file exists
        $imagePath = storage_path('app/public/' . $post->image_path);
        $result['tests']['image_exists'] = file_exists($imagePath) ? 'PASS' : 'FAIL';
        
        // Test 3: Try actual posting (if requested)
        if (request()->get('actually_post') === 'yes') {
            try {
                // Dispatch the job
                App\Jobs\PostToInstagramJob::dispatch($account, $post);
                $result['tests']['job_dispatched'] = 'PASS - Job added to queue';
                $result['message'] = 'Job dispatched successfully! Check queue worker for results.';
            } catch (Exception $e) {
                $result['tests']['job_dispatched'] = 'ERROR: ' . $e->getMessage();
            }
        } else {
            $result['tests']['job_dispatch'] = 'SKIPPED - Add ?actually_post=yes to dispatch real job';
        }
        
        return response()->json($result);
        
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
})->name('test.instagram.post');

// Test route for manual Instagram posting (immediate execution)
Route::get('/test-instagram-post-now', function(InstagramApiService $service) {
    try {
        // Get the first active Instagram account
        $account = App\Models\InstagramAccount::where('is_active', true)->first();
        
        if (!$account) {
            return response()->json([
                'status' => 'error',
                'message' => 'No active Instagram account found'
            ], 404);
        }
        
        // Get the first draft post
        $post = App\Models\InstagramPost::where('status', 'draft')->first();
        
        if (!$post) {
            return response()->json([
                'status' => 'error',
                'message' => 'No draft posts found'
            ], 404);
        }
        
        // Execute the posting logic directly (not via queue)
        $result = [
            'status' => 'attempting_post',
            'account' => $account->username,
            'post_id' => $post->id,
            'caption' => substr($post->caption, 0, 50) . '...'
        ];
        
        // Check requirements
        if (!$account->access_token || !$account->facebook_page_access_token || !$account->instagram_business_account_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing required Instagram account tokens or business account ID',
                'details' => [
                    'has_access_token' => !empty($account->access_token),
                    'has_page_token' => !empty($account->facebook_page_access_token),
                    'has_business_id' => !empty($account->instagram_business_account_id)
                ]
            ], 400);
        }
        
        // Get image URL
        $imageUrl = config('app.url') . '/storage/' . $post->image_path;
        
        // Try to post using the service
        $postResult = $service->postPhoto(
            $account->facebook_page_access_token,
            $account->instagram_business_account_id,
            $imageUrl,
            $post->caption
        );
        
        if ($postResult && isset($postResult['id'])) {
            // Update post status
            $post->update([
                'status' => 'posted',
                'instagram_media_id' => $postResult['id'],
                'posted_at' => now(),
                'error_message' => null
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Post published successfully!',
                'instagram_media_id' => $postResult['id'],
                'post_id' => $post->id,
                'posted_at' => now()->toDateTimeString()
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to post to Instagram',
                'api_response' => $postResult
            ], 500);
        }
        
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
})->name('test.instagram.post.now');

// Instagram Accounts
Route::resource('accounts', InstagramAccountController::class);

// Content Containers
Route::resource('containers', ContentContainerController::class);
Route::get('containers/{container}/posts', [ContentContainerController::class, 'posts'])->name('containers.posts');

// Instagram Posts
Route::put('posts/{post}', [ContentContainerController::class, 'updatePost'])->name('posts.update');
Route::delete('posts/{post}', [ContentContainerController::class, 'deletePost'])->name('posts.delete');

// Schedules
Route::resource('schedules', ScheduleController::class);
Route::post('schedules/{schedule}/toggle', [ScheduleController::class, 'toggle'])->name('schedules.toggle');

// Log viewer route for debugging
Route::get('/view-logs', function () {
    $logFile = storage_path('logs/laravel.log');
    
    if (!file_exists($logFile)) {
        return response()->json(['error' => 'Log file not found']);
    }
    
    // Get last 200 lines of the log file
    $lines = [];
    $file = new SplFileObject($logFile);
    $file->seek(PHP_INT_MAX);
    $totalLines = $file->key();
    
    $startLine = max(0, $totalLines - 200); // Get last 200 lines
    $file->seek($startLine);
    
    while (!$file->eof()) {
        $line = $file->fgets();
        if ($line) {
            $lines[] = $line;
        }
    }
    
    $content = '<h2>Laravel Logs (Last 200 lines)</h2>';
    $content .= '<p><a href="/clear-logs">Clear Logs</a> | <a href="/view-logs">Refresh</a></p>';
    $content .= '<pre style="background: #000; color: #fff; padding: 20px; overflow: auto; max-height: 80vh;">';
    $content .= htmlspecialchars(implode('', $lines));
    $content .= '</pre>';
    
    return response($content)->header('Content-Type', 'text/html');
});

// Clear logs route
Route::get('/clear-logs', function () {
    $logFile = storage_path('logs/laravel.log');
    
    if (file_exists($logFile)) {
        file_put_contents($logFile, '');
        return redirect('/view-logs')->with('success', 'Logs cleared');
    }
    
    return response()->json(['error' => 'Log file not found']);
});
