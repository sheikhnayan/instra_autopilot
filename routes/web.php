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
