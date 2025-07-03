@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div class="flex justify-center items-center min-h-screen">
    <div class="w-full max-w-md bg-white p-8 rounded-xl shadow-md">
        <h2 class="text-2xl font-bold mb-6 text-center">Login</h2>

        {{-- Test box to confirm Tailwind --}}
        <div class="bg-green-500 text-white p-3 mb-4 rounded">Tailwind is working!</div>

        <form method="POST" action="{{ url('/login') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-gray-700 font-medium mb-1">Email</label>
                <input type="email" name="email" required
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-300">
                @error('email')
                    <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="block text-gray-700 font-medium mb-1">Password</label>
                <input type="password" name="password" required
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-300">
                @error('password')
                    <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit"
                    class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition">
                Login
            </button>
        </form>
    </div>
</div>
@endsection