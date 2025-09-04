@extends('layouts.app')

@section('title', 'Create Schedule - SocialScheduler')
@section('header', 'Create New Schedule')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow-sm border">
        <div class="p-6">
            <form action="{{ route('schedules.store') }}" method="POST">
                @csrf
                
                <!-- Schedule Details -->
                <div class="mb-8">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Schedule Details</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Schedule Name</label>
                            <input type="text" id="name" name="name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="e.g., Summer Campaign Schedule"
                                   value="{{ old('name') }}">
                            @error('name')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label for="content_container_id" class="block text-sm font-medium text-gray-700 mb-2">Content Container</label>
                            <select id="content_container_id" name="content_container_id" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select a container</option>
                                @foreach($containers as $container)
                                    <option value="{{ $container->id }}" {{ (old('content_container_id', $selectedContainerId ?? '') == $container->id) ? 'selected' : '' }}>
                                        {{ $container->name }} ({{ $container->posts->count() }} posts)
                                    </option>
                                @endforeach
                            </select>
                            @error('content_container_id')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="instagram_account_id" class="block text-sm font-medium text-gray-700 mb-2">Instagram Account</label>
                            <select id="instagram_account_id" name="instagram_account_id" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select an account</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" {{ (old('instagram_account_id', $selectedAccountId ?? '') == $account->id) ? 'selected' : '' }}>
                                        {{ $account->username }} - {{ $account->display_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('instagram_account_id')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                            <input type="date" id="start_date" name="start_date" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   value="{{ old('start_date', date('Y-m-d')) }}">
                            @error('start_date')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="start_time" class="block text-sm font-medium text-gray-700 mb-2">Start Time</label>
                            <input type="time" id="start_time" name="start_time" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   value="{{ old('start_time', '09:00') }}">
                            @error('start_time')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="interval_minutes" class="block text-sm font-medium text-gray-700 mb-2">Posting Interval</label>
                            <select id="interval_minutes" name="interval_minutes" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="15" {{ old('interval_minutes') == 15 ? 'selected' : '' }}>Every 15 minutes</option>
                                <option value="30" {{ old('interval_minutes') == 30 ? 'selected' : '' }}>Every 30 minutes</option>
                                <option value="60" {{ old('interval_minutes', 60) == 60 ? 'selected' : '' }}>Every hour</option>
                                <option value="120" {{ old('interval_minutes') == 120 ? 'selected' : '' }}>Every 2 hours</option>
                                <option value="180" {{ old('interval_minutes') == 180 ? 'selected' : '' }}>Every 3 hours</option>
                                <option value="360" {{ old('interval_minutes') == 360 ? 'selected' : '' }}>Every 6 hours</option>
                                <option value="720" {{ old('interval_minutes') == 720 ? 'selected' : '' }}>Every 12 hours</option>
                                <option value="1440" {{ old('interval_minutes') == 1440 ? 'selected' : '' }}>Every 24 hours</option>
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
                    
                    <div class="flex items-center">
                        <input type="checkbox" id="repeat_cycle" name="repeat_cycle" value="1" 
                               class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                               {{ old('repeat_cycle', true) ? 'checked' : '' }}>
                        <label for="repeat_cycle" class="ml-2 block text-sm text-gray-700">
                            Repeat cycle when all posts are published
                        </label>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">When enabled, the schedule will start over from the first post after all posts have been published.</p>
                </div>

                <!-- Submit Buttons -->
                <div class="flex items-center justify-between">
                    <a href="{{ route('schedules.index') }}" class="text-gray-600 hover:text-gray-800">
                        Cancel
                    </a>
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        Create Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
