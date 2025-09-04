@extends('layouts.app')

@section('title', 'Content Containers - SocialScheduler')
@section('header', 'Content Containers')

@section('header-actions')
<a href="{{ route('containers.create') }}" 
   class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
    + Create Container
</a>
@endsection

@section('content')
<div class="mb-6">
    <p class="text-gray-600">Create bundles with up to 10 photos for your campaigns</p>
</div>

@if(session('success'))
<div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
    {{ session('success') }}
</div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @forelse($containers as $container)
    <div class="bg-white rounded-lg shadow-sm border hover:shadow-md transition-shadow">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-medium text-gray-900">{{ $container->name }}</h3>
                <span class="text-sm bg-blue-100 text-blue-800 px-2 py-1 rounded flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4 3a2 2 0 100 4h12a2 2 0 100-4H4zM3 8a1 1 0 000 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a1 1 0 100-2H3z"></path>
                    </svg>
                    {{ $container->posts->count() }}
                </span>
            </div>

            @if($container->description)
            <p class="text-sm text-gray-600 mb-4">{{ $container->description }}</p>
            @endif

            <!-- Preview Images -->
            <div class="mb-4">
                @if($container->posts->count() > 0)
                <div class="flex space-x-2">
                    @foreach($container->posts->take(3) as $post)
                        @if(is_array($post->images) && count($post->images) > 0)
                        <div class="w-16 h-16 bg-gray-200 rounded-lg flex items-center justify-center overflow-hidden">
                            @if($post->images[0] !== '/images/placeholder.jpg')
                                <img src="{{ asset($post->images[0]) }}" alt="Post preview" class="w-full h-full object-cover">
                            @else
                                <svg class="w-8 h-8 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                                </svg>
                            @endif
                        </div>
                        @endif
                    @endforeach
                    @if($container->posts->count() > 3)
                    <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center">
                        <span class="text-xs text-gray-500">+{{ $container->posts->count() - 3 }} more</span>
                    </div>
                    @endif
                </div>
                @else
                <div class="w-full h-32 bg-gray-100 rounded-lg flex items-center justify-center">
                    <div class="text-center">
                        <svg class="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        <p class="text-xs text-gray-500">No posts yet</p>
                    </div>
                </div>
                @endif
            </div>

            <div class="text-xs text-gray-500 mb-4">
                Created {{ $container->created_at->diffForHumans() }}
            </div>

            <div class="flex items-center justify-between">
                <div class="flex space-x-2">
                    <a href="{{ route('containers.show', $container) }}" class="text-sm text-gray-600 hover:text-gray-800 flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path>
                            <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path>
                        </svg>
                        Preview
                    </a>
                    <a href="{{ route('containers.edit', $container) }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                        </svg>
                        Edit
                    </a>
                </div>
                <form action="{{ route('containers.destroy', $container) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this container?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm text-red-600 hover:text-red-800 flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" clip-rule="evenodd"></path>
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414L7.586 12l-1.293 1.293a1 1 0 101.414 1.414L9 13.414l2.293 2.293a1 1 0 001.414-1.414L11.414 12l1.293-1.293z" clip-rule="evenodd"></path>
                        </svg>
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
    @empty
    <div class="col-span-full text-center py-12">
        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
        </svg>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No containers yet</h3>
        <p class="text-gray-600 mb-4">Create your first content container to get started.</p>
        <a href="{{ route('containers.create') }}" 
           class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
            Create Container
        </a>
    </div>
    @endforelse
</div>
@endsection
