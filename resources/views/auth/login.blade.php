<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SocialScheduler</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-lg shadow-md p-6">
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">SocialScheduler</h1>
            <p class="text-gray-600">Sign in to your account</p>
        </div>

        @if($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            
            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                <input type="email" id="email" name="email" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       value="{{ old('email') }}" placeholder="admin@admin.com">
            </div>

            <div class="mb-6">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                <input type="password" id="password" name="password" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Enter your password">
            </div>

            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center">
                    <input type="checkbox" id="remember" name="remember" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <label for="remember" class="ml-2 text-sm text-gray-600">Remember me</label>
                </div>
            </div>

            <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                Sign In
            </button>
        </form>

        <div class="mt-6 text-center text-sm text-gray-500">
            <p>Default login: admin@admin.com / password</p>
        </div>
    </div>
</body>
</html>
