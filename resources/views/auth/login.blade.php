@php
    $isEpayPlus = str_contains(request()->getHost(), 'epayplus');
    $brandName = $isEpayPlus ? 'ePay Plus' : 'INSA POS';
@endphp
<!DOCTYPE html>
<html>
<head>
    <title>{{ $brandName }} — Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-900">{{ $brandName }}</h1>
                <p class="text-gray-500 text-sm mt-1">Sign in to your account</p>
            </div>

            @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 p-3 rounded mb-4 text-sm">
                {{ $errors->first() }}
            </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                           class="w-full p-3 border rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" id="password" name="password" required
                           class="w-full p-3 border rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>

                <div class="flex items-center">
                    <input type="checkbox" id="remember" name="remember" class="w-4 h-4 rounded border-gray-300">
                    <label for="remember" class="ml-2 text-sm text-gray-600">Remember me</label>
                </div>

                <button type="submit"
                        class="w-full p-3 bg-blue-700 text-white font-semibold rounded hover:bg-blue-800 transition">
                    Sign In
                </button>
            </form>
        </div>
    </div>
</body>
</html>
