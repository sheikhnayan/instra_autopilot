@extends('layouts.app')

@section('title', 'Edit Schedule - SocialScheduler')
@section('header', 'Edit Schedule')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow-sm border">
        <div class="p-6">
            <form action="{{ route('schedules.update', $schedule) }}" method="POST">
                @csrf
                @method('PUT')
                {{ \Carbon\Carbon::now() }}
                <!-- Schedule Details -->
                <div class="mb-8">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Schedule Details</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Schedule Name</label>
                            <input type="text" id="name" name="name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="e.g., Summer Campaign Schedule"
                                   value="{{ old('name', $schedule->name) }}">
                            @error('name')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Content Container</label>
                            <div class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg text-gray-700">
                                {{ $schedule->contentContainer->name ?? 'N/A' }} ({{ $schedule->contentContainer->posts->count() ?? 0 }} posts)
                            </div>
                            <p class="text-sm text-gray-500 mt-1">Container cannot be changed after creation</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Instagram Account</label>
                            <div class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg text-gray-700">
                                {{ $schedule->instagramAccount->username ?? 'N/A' }}
                            </div>
                            <p class="text-sm text-gray-500 mt-1">Account cannot be changed after creation</p>
                        </div>
                    </div>
                </div>

                <!-- Timing Configuration -->
                <div class="mb-8">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Timing Configuration</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                            <input type="date" id="start_date" name="start_date" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   value="{{ old('start_date', $schedule->start_date->format('Y-m-d')) }}">
                            @error('start_date')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label for="start_time" class="block text-sm font-medium text-gray-700 mb-2">Start Time</label>
                            <input type="time" id="start_time" name="start_time" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   value="{{ old('start_time', $schedule->start_time ?  \Carbon\Carbon::parse($schedule->start_time)->format('H:i') : '') }}">
                            @error('start_time')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="interval_minutes" class="block text-sm font-medium text-gray-700 mb-2">Posting Interval</label>
                            <select id="interval_minutes" name="interval_minutes" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="1" {{ old('interval_minutes', $schedule->interval_minutes) == 1 ? 'selected' : '' }}>Every 1 minute</option>
                                <option value="5" {{ old('interval_minutes', $schedule->interval_minutes) == 5 ? 'selected' : '' }}>Every 5 minutes</option>
                                <option value="15" {{ old('interval_minutes', $schedule->interval_minutes) == 15 ? 'selected' : '' }}>Every 15 minutes</option>
                                <option value="30" {{ old('interval_minutes', $schedule->interval_minutes) == 30 ? 'selected' : '' }}>Every 30 minutes</option>
                                <option value="60" {{ old('interval_minutes', $schedule->interval_minutes) == 60 ? 'selected' : '' }}>Every hour</option>
                                <option value="120" {{ old('interval_minutes', $schedule->interval_minutes) == 120 ? 'selected' : '' }}>Every 2 hours</option>
                                <option value="240" {{ old('interval_minutes', $schedule->interval_minutes) == 240 ? 'selected' : '' }}>Every 4 hours</option>
                                <option value="480" {{ old('interval_minutes', $schedule->interval_minutes) == 480 ? 'selected' : '' }}>Every 8 hours</option>
                                <option value="720" {{ old('interval_minutes', $schedule->interval_minutes) == 720 ? 'selected' : '' }}>Every 12 hours</option>
                                <option value="1440" {{ old('interval_minutes', $schedule->interval_minutes) == 1440 ? 'selected' : '' }}>Every 24 hours</option>
                            </select>
                            @error('interval_minutes')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Options -->
                <div class="mb-8">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Options</h3>
                    
                    <div class="space-y-4">
                        <div class="flex items-center">
                            <input type="checkbox" id="repeat_cycle" name="repeat_cycle" value="1" 
                                   {{ old('repeat_cycle', $schedule->repeat_cycle) ? 'checked' : '' }}
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="repeat_cycle" class="ml-2 text-sm text-gray-700">
                                Repeat cycle when all posts are published
                            </label>
                        </div>
                        <p class="text-sm text-gray-500 ml-6">
                            When enabled, the schedule will restart from the first post after publishing all posts in the container.
                        </p>
                    </div>
                </div>

                <!-- Current Progress -->
                <div class="mb-8">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Current Progress</h3>
                    
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                            <div>
                                <div class="text-2xl font-bold text-blue-600">{{ $schedule->current_post_index ?? 0 }}</div>
                                <div class="text-sm text-gray-600">Posts Published</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-green-600">{{ $schedule->contentContainer->posts->count() ?? 0 }}</div>
                                <div class="text-sm text-gray-600">Total Posts</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-purple-600">{{ ucfirst($schedule->status) }}</div>
                                <div class="text-sm text-gray-600">Status</div>
                            </div>
                        </div>
                        
                        @if($schedule->last_posted_at)
                        <div class="mt-4 text-center">
                            <p class="text-sm text-gray-600">
                                Last posted: {{ $schedule->last_posted_at->format('M j, Y \a\t g:i A') }}
                            </p>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-between pt-6 border-t">
                    <a href="{{ route('schedules.show', $schedule) }}" 
                       class="text-gray-600 hover:text-gray-800 transition-colors">
                        ← Back to Schedule
                    </a>
                    
                    <div class="flex space-x-3">
                        <button type="submit" 
                                class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                            Update Schedule
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
