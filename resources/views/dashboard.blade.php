@extends('layouts.app')

@section('title', 'Instagram Accounts - SocialScheduler')
@section('header', 'Instagram Accounts')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <p class="text-gray-600">Manage your Instagram accounts and schedule content</p>
    <a href="{{ route('instagram.auth') }}" 
       class="bg-gradient-to-r from-purple-500 to-pink-500 text-white px-4 py-2 rounded-lg hover:from-purple-600 hover:to-pink-600 transition-colors flex items-center space-x-2">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
        </svg>
        <span>Connect Instagram Account</span>
    </a>
</div>

@if(session('success'))
    <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
        {{ session('error') }}
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @foreach($accounts as $account)
    <div class="bg-white rounded-lg shadow-sm border">
        <div class="p-6">
            <div class="flex items-center space-x-4 mb-4">
                <div class="w-12 h-12 rounded-full flex items-center justify-center text-white font-semibold text-lg"
                     style="background-color: {{ $account->avatar_color }}">
                    {{ $account->avatar_letter }}
                </div>
                <div class="flex-1">
                    <h3 class="font-medium text-gray-900">{{ $account->username }}</h3>
                    <p class="text-sm text-gray-500">{{ $account->display_name }}</p>
                    @if($account->account_type)
                        <span class="inline-block text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded mt-1">
                            {{ ucfirst(strtolower($account->account_type)) }}
                        </span>
                    @endif
                </div>
                <div class="flex flex-col items-end space-y-1">
                    @if($account->is_active && $account->isTokenValid())
                        <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">Connected</span>
                    @else
                        <span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded">Disconnected</span>
                    @endif
                </div>
            </div>
            
            @if($account->is_active && $account->isTokenValid())
                <div class="flex items-center justify-center py-4 border-2 border-dashed border-green-200 rounded-lg bg-green-50 mb-4">
                    <div class="text-center">
                        <svg class="w-8 h-8 text-green-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <p class="text-sm text-green-700 font-medium">Ready to Schedule</p>
                    </div>
                </div>
                
                <div class="flex space-x-2">
                    <a href="{{ route('schedules.create') }}?account={{ $account->id }}" 
                       class="flex-1 bg-blue-600 text-white text-center py-2 px-4 rounded hover:bg-blue-700 transition-colors text-sm">
                        Schedule Content
                    </a>
                    <form action="{{ route('instagram.refresh', $account) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="bg-gray-100 text-gray-700 px-3 py-2 rounded hover:bg-gray-200 transition-colors text-sm">
                            Refresh
                        </button>
                    </form>
                </div>
                
                @if($account->last_sync_at)
                    <p class="text-xs text-gray-500 mt-2">Last synced: {{ $account->last_sync_at->diffForHumans() }}</p>
                @endif
            @else
                <div class="flex items-center justify-center py-6 border-2 border-dashed border-red-200 rounded-lg bg-red-50 mb-4">
                    <div class="text-center">
                        <svg class="w-8 h-8 text-red-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.734-.833-2.464 0L3.349 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        <p class="text-sm text-red-700 font-medium">Connection Required</p>
                    </div>
                </div>
                
                <div class="flex space-x-2">
                    <a href="{{ route('instagram.auth') }}" 
                       class="flex-1 bg-purple-600 text-white text-center py-2 px-4 rounded hover:bg-purple-700 transition-colors text-sm">
                        Reconnect
                    </a>
                    <form action="{{ route('instagram.disconnect', $account) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="bg-gray-100 text-gray-700 px-3 py-2 rounded hover:bg-gray-200 transition-colors text-sm">
                            Remove
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
    @endforeach
</div>

@if($containers->count() > 0)
<div class="mt-12">
    <h3 class="text-lg font-semibold text-gray-900 mb-6">Recent Content Containers</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($containers->take(6) as $container)
        <div class="bg-white rounded-lg shadow-sm border hover:shadow-md transition-shadow">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="font-medium text-gray-900">{{ $container->name }}</h4>
                    <span class="text-sm bg-blue-100 text-blue-800 px-2 py-1 rounded">
                        {{ $container->posts->count() }} posts
                    </span>
                </div>
                
                @if($container->description)
                <p class="text-sm text-gray-600 mb-4">{{ $container->description }}</p>
                @endif

                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500">Created {{ $container->created_at->diffForHumans() }}</span>
                    <div class="flex space-x-2">
                        <a href="{{ route('containers.show', $container) }}" 
                           class="text-sm text-blue-600 hover:text-blue-800">Preview</a>
                        <a href="{{ route('containers.edit', $container) }}" 
                           class="text-sm text-gray-600 hover:text-gray-800">Edit</a>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif
@endsection
