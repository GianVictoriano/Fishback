<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'FishBack')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-900">
    <nav class="bg-white shadow p-4 flex justify-between">
        <div class="text-xl font-bold text-blue-600">FishBack</div>
        <div>
            <a href="/" class="text-sm text-blue-600 hover:underline mr-4">Home</a>
            <a href="/about" class="text-sm text-blue-600 hover:underline">About</a>
            <a href="/login" class="text-sm text-blue-600 hover:underline">login</a>
            <a href="/forum" class="text-sm text-blue-600 hover:underline">forum</a>
            <a href="{{ url('/user') }}">User</a>
        </div>
    </nav>

    <main class="p-6">
        @yield('content')
    </main>
</body>
</html>
