@extends('layouts.app')

@section('title', 'Schedules - SocialScheduler')
@section('header', 'Schedules')

@section('header-actions')
<a href="{{ route('schedules.create') }}" 
   class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
    + Create Schedule
</a>
@endsection

@section('content')
<div class="mb-6">
    <p class="text-gray-600">Manage your automated posting schedules</p>
</div>

@if(session('success'))
<div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
    {{ session('success') }}
</div>
@endif

<div class="space-y-4">
    @forelse($schedules as $schedule)
    <div class="bg-white rounded-lg shadow-sm border hover:shadow-md transition-shadow">
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="flex items-center space-x-4">
                        <div>
                            <h3 class="font-medium text-gray-900">{{ $schedule->name }}</h3>
                            <div class="flex items-center space-x-4 mt-1">
                                <span class="text-sm text-gray-500">
                                    Container: {{ $schedule->contentContainer->name ?? 'N/A' }}
                                </span>
                                <span class="text-sm text-gray-500">
                                    Account: {{ $schedule->instagramAccount->username ?? 'N/A' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3 flex items-center space-x-6 text-sm text-gray-600">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Start: {{ $schedule->start_date->format('M j, Y') }} at {{ \Carbon\Carbon::parse($schedule->start_time)->format('g:i A') }}
                        </div>
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Every {{ $schedule->interval_minutes }} minutes
                        </div>
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z"></path>
                            </svg>
                            {{-- Post {{ $schedule->current_post_index + 1 }} of {{ $schedule->contentContainer->posts->count() ?? 0}} --}}
                        </div>
                    </div>
                </div>

                <div class="flex items-center space-x-4">
                    <!-- Status Badge -->
                    <span class="px-3 py-1 rounded-full text-xs font-medium
                        {{ $schedule->status === 'active' ? 'bg-green-100 text-green-800' : '' }}
                        {{ $schedule->status === 'paused' ? 'bg-yellow-100 text-yellow-800' : '' }}
                        {{ $schedule->status === 'completed' ? 'bg-blue-100 text-blue-800' : '' }}
                        {{ $schedule->status === 'stopped' ? 'bg-red-100 text-red-800' : '' }}">
                        {{ ucfirst($schedule->status) }}
                    </span>

                    <!-- Actions -->
                    <div class="flex items-center space-x-2">
                        <form action="{{ route('schedules.toggle', $schedule) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-sm px-3 py-1 rounded border 
                                {{ $schedule->status === 'active' ? 'border-yellow-300 text-yellow-700 hover:bg-yellow-50' : 'border-green-300 text-green-700 hover:bg-green-50' }}">
                                {{ $schedule->status === 'active' ? 'Pause' : 'Activate' }}
                            </button>
                        </form>
                        
                        <a href="{{ route('schedules.show', $schedule) }}" 
                           class="text-sm text-blue-600 hover:text-blue-800 px-3 py-1 border border-blue-300 rounded hover:bg-blue-50">
                            View
                        </a>
                        
                        <a href="{{ route('schedules.edit', $schedule) }}" 
                           class="text-sm text-gray-600 hover:text-gray-800 px-3 py-1 border border-gray-300 rounded hover:bg-gray-50">
                            Edit
                        </a>
                        
                        <form action="{{ route('schedules.destroy', $schedule) }}" method="POST" class="inline" 
                              onsubmit="return confirm('Are you sure you want to delete this schedule? This action cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm text-red-600 hover:text-red-800 px-3 py-1 border border-red-300 rounded hover:bg-red-50">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="text-center py-12">
        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
        </svg>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No schedules yet</h3>
        <p class="text-gray-600 mb-4">Create your first schedule to start automating your posts.</p>
        <a href="{{ route('schedules.create') }}" 
           class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
            Create Schedule
        </a>
    </div>
    @endforelse
</div>
@endsection
