@extends('layouts.app')

@section('title', 'Schedule Details - SocialScheduler')
@section('header', 'Schedule: ' . $schedule->name)

@section('header-actions')
<div class="flex space-x-3">
    <a href="{{ route('schedules.edit', $schedule) }}" 
       class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
        Edit Schedule
    </a>
    
    <form action="{{ route('schedules.toggle', $schedule) }}" method="POST" class="inline">
        @csrf
        @if($schedule->status === 'active')
            <button type="submit" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition-colors">
                Pause Schedule
            </button>
        @else
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                Activate Schedule
            </button>
        @endif
    </form>
</div>
@endsection

@section('content')
@if(session('success'))
<div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
    {{ session('success') }}
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Schedule Overview -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Status Card -->
        <div class="bg-white rounded-lg shadow-sm border">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Schedule Status</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="text-center">
                        <div class="text-3xl font-bold {{ $schedule->status === 'active' ? 'text-green-600' : ($schedule->status === 'paused' ? 'text-yellow-600' : 'text-gray-600') }}">
                            {{ ucfirst($schedule->status) }}
                        </div>
                        <div class="text-sm text-gray-600">Current Status</div>
                    </div>
                    
                    <div class="text-center">
                        <div class="text-3xl font-bold text-blue-600">{{ $schedule->current_post_index ?? 0 }}</div>
                        <div class="text-sm text-gray-600">Posts Published</div>
                    </div>
                    
                    <div class="text-center">
                        <div class="text-3xl font-bold text-purple-600">{{ $schedule->contentContainer->posts->count() }}</div>
                        <div class="text-sm text-gray-600">Total Posts</div>
                    </div>
                    
                    <div class="text-center">
                        <div class="text-3xl font-bold text-indigo-600">{{ $schedule->interval_minutes }}m</div>
                        <div class="text-sm text-gray-600">Interval</div>
                    </div>
                </div>
                
                @if($schedule->last_posted_at)
                <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-600">
                        <strong>Last Posted:</strong> {{ $schedule->getLastPostedTimeNY()->format('M j, Y \a\t g:i A') }}
                        ({{ $schedule->getLastPostedTimeNY()->diffForHumans() }})
                    </p>
                </div>
                @endif
            </div>
        </div>

        <!-- Configuration Details -->
        <div class="bg-white rounded-lg shadow-sm border">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Configuration</h3>
                
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Content Container</label>
                            <div class="mt-1">
                                <a href="{{ route('containers.show', $schedule->contentContainer) }}" 
                                   class="text-blue-600 hover:text-blue-800 font-medium">
                                    {{ $schedule->contentContainer->name }}
                                </a>
                                <span class="text-gray-500 ml-2">({{ $schedule->contentContainer->posts->count() }} posts)</span>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Instagram Account</label>
                            <div class="mt-1 flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-semibold mr-2"
                                     style="background-color: {{ $schedule->instagramAccount->avatar_color }}">
                                    {{ $schedule->instagramAccount->avatar_letter }}
                                </div>
                                <span class="font-medium">{{ $schedule->instagramAccount->username }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Start Date</label>
                            <div class="mt-1 text-gray-900">{{ $schedule->start_date->format('M j, Y') }}</div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Start Time</label>
                            <div class="mt-1 text-gray-900">{{ $schedule->start_time ? \Carbon\Carbon::parse($schedule->start_time)->format('g:i A') : 'N/A' }}</div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Posting Interval</label>
                            <div class="mt-1 text-gray-900">
                                @if($schedule->interval_minutes < 60)
                                    Every {{ $schedule->interval_minutes }} minutes
                                @elseif($schedule->interval_minutes == 60)
                                    Every hour
                                @elseif($schedule->interval_minutes < 1440)
                                    Every {{ $schedule->interval_minutes / 60 }} hours
                                @else
                                    Every {{ $schedule->interval_minutes / 1440 }} day(s)
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Options</label>
                        <div class="mt-1">
                            @if($schedule->repeat_cycle)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Repeat Cycle Enabled
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    No Repeat
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Posts in Container -->
        <div class="bg-white rounded-lg shadow-sm border">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Posts in Container</h3>
                    <span class="text-sm text-gray-500">{{ $schedule->contentContainer->posts->count() }} total</span>
                </div>
                
                <div class="space-y-3">
                    @foreach($schedule->contentContainer->posts->sortBy('order') as $index => $post)
                    <div class="flex items-center space-x-4 p-3 {{ $index < ($schedule->current_post_index ?? 0) ? 'bg-green-50 border-green-200' : 'bg-gray-50' }} border rounded-lg">
                        <div class="flex-shrink-0">
                            @if($post->images && count($post->images) > 0 && $post->images[0] !== '/images/placeholder.jpg')
                                <img src="{{ $post->images[0] }}" alt="Post image" class="w-12 h-12 object-cover rounded">
                            @else
                                <div class="w-12 h-12 bg-gray-200 rounded flex items-center justify-center">
                                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                            @endif
                        </div>
                        
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-900 truncate">
                                {{ Str::limit($post->caption, 80) }}
                            </p>
                            <div class="flex items-center space-x-2 mt-1">
                                <span class="text-xs text-gray-500">Post {{ $index + 1 }}</span>
                                @if($index < ($schedule->current_post_index ?? 0))
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                        Posted
                                    </span>
                                @elseif($index == ($schedule->current_post_index ?? 0) && $schedule->status === 'active')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                        Next
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                        Pending
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow-sm border">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
                
                <div class="space-y-3">
                    <a href="{{ route('schedules.edit', $schedule) }}" 
                       class="w-full bg-blue-600 text-white text-center px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors block">
                        Edit Schedule
                    </a>
                    
                    <form action="{{ route('schedules.toggle', $schedule) }}" method="POST">
                        @csrf
                        @if($schedule->status === 'active')
                            <button type="submit" class="w-full bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition-colors">
                                Pause Schedule
                            </button>
                        @else
                            <button type="submit" class="w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                                Activate Schedule
                            </button>
                        @endif
                    </form>
                    
                    <a href="{{ route('containers.show', $schedule->contentContainer) }}" 
                       class="w-full bg-gray-100 text-gray-700 text-center px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors block">
                        View Container
                    </a>
                </div>
            </div>
        </div>

        <!-- Schedule Timeline -->
        <div class="bg-white rounded-lg shadow-sm border">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Timeline</h3>
                
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Created</span>
                        <span class="text-gray-900">{{ $schedule->created_at->format('M j, Y') }}</span>
                    </div>
                    
                    @if($schedule->last_posted_at)
                    <div class="flex justify-between">
                        <span class="text-gray-600">Last Posted</span>
                        <span class="text-gray-900">{{ $schedule->getLastPostedTimeNY()->diffForHumans() }}</span>
                    </div>
                    @endif
                    
                    @if($schedule->status === 'active' && $schedule->last_posted_at)
                    <div class="flex justify-between">
                        <span class="text-gray-600">Next Post</span>
                        <span class="text-gray-900">{{ $schedule->getNextPostTime()->diffForHumans() }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
