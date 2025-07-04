@extends('layouts.app')

@section('title', 'Journalist Management')

@section('content')
<div class="flex min-h-screen">
    <!-- SIDEBAR (left) -->
    <aside class="w-64 bg-[#183057] text-white p-6">
        <h2 class="text-xl font-bold mb-6">Admin Panel</h2>

        <a href="{{ route('admin') }}" class="block px-4 py-2 rounded hover:bg-blue-800 transition">Dashboard</a>
        <a href="{{ route('admin.journalist') }}" class="block px-4 py-2 mt-2 rounded hover:bg-blue-800 transition">Journalist</a>
    </aside>

    <!-- MAIN CONTENT (right) -->
    <main class="flex-1 p-10 bg-gray-50">
        <div class="flex gap-8">
            <!-- Journalist List (LEFT) -->
            <div class="w-1/2 bg-white p-6 rounded shadow-md">
                <h2 class="text-xl font-semibold mb-4">Journalist Accounts</h2>

                @if($journalists->isEmpty())
                    <p class="text-gray-500">No journalists found.</p>
                @else
                    <ul class="divide-y divide-gray-200">
                        @foreach($journalists as $journalist)
                            <li class="py-2">
                                <div class="font-semibold">{{ $journalist->fname }} {{ $journalist->lname }}</div>
                                <div class="text-sm text-gray-600">{{ $journalist->email }}</div>
                                <div class="text-xs text-gray-400">Position: {{ $journalist->position ?? 'Unassigned' }}</div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <!-- Journalist Creation Form (RIGHT) -->
            <div class="w-1/2">
                <h1 class="text-2xl font-bold mb-6 text-right">Create Journalist Account</h1>

                @if(session('success'))
                    <div class="mb-4 bg-green-100 text-green-700 p-3 rounded text-right">
                        {{ session('success') }}
                    </div>
                @endif

                <form action="{{ route('admin.journalist.store') }}" method="POST" class="bg-white p-6 rounded shadow-md">
                    @csrf

                    <div class="mb-4">
                        <label class="block font-semibold mb-1">First Name</label>
                        <input type="text" name="fname" required class="w-full border px-4 py-2 rounded">
                    </div>

                    <div class="mb-4">
                        <label class="block font-semibold mb-1">Last Name</label>
                        <input type="text" name="lname" required class="w-full border px-4 py-2 rounded">
                    </div>

                    <div class="mb-4">
                        <label class="block font-semibold mb-1">Email (must be BatStateU)</label>
                        <input type="email" name="email" required class="w-full border px-4 py-2 rounded">
                    </div>

                    <div class="mb-4">
                        <label class="block font-semibold mb-1">Password</label>
                        <input type="password" name="password" required class="w-full border px-4 py-2 rounded">
                    </div>

                    <div class="mb-4">
                        <label class="block font-semibold mb-1">Position</label>
                        <input type="text" name="position" class="w-full border px-4 py-2 rounded" placeholder="Leave blank for now">
                    </div>

                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 w-full">
                        Create Journalist
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>
@endsection