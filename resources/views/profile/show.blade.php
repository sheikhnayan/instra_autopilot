@extends('layouts.app')

@section('title', 'Profile - SocialScheduler')
@section('header', 'Profile')

@section('header-actions')
<a href="{{ route('profile.edit') }}" 
   class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
    Edit Profile
</a>
@endsection

@section('content')
@if(session('success'))
<div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
    {{ session('success') }}
</div>
@endif

<div class="max-w-2xl">
    <div class="bg-white rounded-lg shadow-sm border">
        <div class="p-6">
            <div class="flex items-center space-x-4 mb-6">
                <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold text-xl">
                    {{ substr(auth()->user()->name, 0, 1) }}
                </div>
                <div>
                    <h3 class="text-xl font-semibold text-gray-900">{{ auth()->user()->name }}</h3>
                    <p class="text-gray-600">{{ auth()->user()->email }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Account Information</h4>
                    <div class="space-y-3 text-sm">
                        <div>
                            <span class="text-gray-500">Name:</span>
                            <span class="ml-2 text-gray-900">{{ auth()->user()->name }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Email:</span>
                            <span class="ml-2 text-gray-900">{{ auth()->user()->email }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Member since:</span>
                            <span class="ml-2 text-gray-900">{{ auth()->user()->created_at->format('M j, Y') }}</span>
                        </div>
                    </div>
                </div>

                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Quick Actions</h4>
                    <div class="space-y-2">
                        <a href="{{ route('profile.edit') }}" 
                           class="block text-sm text-blue-600 hover:text-blue-800">
                            → Edit profile information
                        </a>
                        <a href="{{ route('profile.edit') }}#password" 
                           class="block text-sm text-blue-600 hover:text-blue-800">
                            → Change password
                        </a>
                        <form action="{{ route('logout') }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-sm text-red-600 hover:text-red-800">
                                → Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
