@extends('layouts.app')

@section('title', 'Container Details - SocialScheduler')
@section('header', $container->name)

@section('header-actions')
<div class="flex space-x-2">
    <a href="{{ route('containers.edit', $container) }}" 
       class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
        Edit Container
    </a>
    <a href="{{ route('schedules.create') }}?container={{ $container->id }}" 
       class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
        Create Schedule
    </a>
</div>
@endsection

@section('content')
@if(session('success'))
<div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
    {{ session('success') }}
</div>
@endif

<div class="mb-6">
    <div class="bg-white rounded-lg shadow-sm border p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-2">Container Information</h3>
        @if($container->description)
        <p class="text-gray-600 mb-4">{{ $container->description }}</p>
        @endif
        <div class="flex items-center space-x-6 text-sm text-gray-600">
            <div class="flex items-center">
                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z"></path>
                </svg>
                {{ $container->posts->count() }} posts
            </div>
            <div class="flex items-center">
                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Created {{ $container->created_at->diffForHumans() }}
            </div>
        </div>
    </div>
</div>

<!-- Posts -->
<div class="space-y-4">
    <h3 class="text-lg font-medium text-gray-900">Posts in this Container</h3>
    
    @forelse($container->posts as $post)
    <div class="bg-white rounded-lg shadow-sm border p-6">
        <div class="flex items-start space-x-4">
            <div class="flex-shrink-0">
                <div class="w-16 h-16 bg-gray-200 rounded-lg flex items-center justify-center overflow-hidden">
                    @if(is_array($post->images) && count($post->images) > 0 && $post->images[0] !== '/images/placeholder.jpg')
                        <img src="{{ asset($post->images[0]) }}" alt="Post image" class="w-full h-full object-cover">
                    @else
                        <svg class="w-8 h-8 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                        </svg>
                    @endif
                </div>
                @if(is_array($post->images) && count($post->images) > 1)
                <p class="text-xs text-gray-500 mt-1 text-center">+{{ count($post->images) - 1 }} more</p>
                @endif
            </div>
            
            <div class="flex-1">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="font-medium text-gray-900">Post {{ $post->order }}</h4>
                    <span class="px-2 py-1 text-xs font-medium rounded-full
                        {{ $post->status === 'draft' ? 'bg-gray-100 text-gray-800' : '' }}
                        {{ $post->status === 'scheduled' ? 'bg-blue-100 text-blue-800' : '' }}
                        {{ $post->status === 'posted' ? 'bg-green-100 text-green-800' : '' }}
                        {{ $post->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}">
                        {{ ucfirst($post->status) }}
                    </span>
                </div>
                
                <p class="text-gray-700 text-sm mb-3">{{ $post->caption }}</p>
                
                @if(is_array($post->hashtags) && count($post->hashtags) > 0)
                <div class="flex flex-wrap gap-1">
                    @foreach($post->hashtags as $hashtag)
                    <span class="text-xs bg-blue-50 text-blue-600 px-2 py-1 rounded">{{ $hashtag }}</span>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
    @empty
    <div class="text-center py-8">
        <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
        </svg>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No posts yet</h3>
        <p class="text-gray-600">Add some posts to this container to get started.</p>
    </div>
    @endforelse
</div>
@endsection
