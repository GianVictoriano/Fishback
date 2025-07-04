@extends('layouts.app')

@section('title', 'Forgot Password')

@section('content')
<div class="flex justify-center items-center min-h-screen">
    <div class="bg-white p-8 rounded shadow-md w-full max-w-md">
        <h2 class="text-2xl font-bold mb-4">Reset Password</h2>

        @if (session('status'))
            <div class="bg-green-100 text-green-800 p-3 rounded mb-4">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <label class="block mb-2 font-semibold">Email</label>
            <input type="email" name="email" required class="w-full p-2 border rounded mb-4">
            @error('email')
                <div class="text-red-500 text-sm mb-2">{{ $message }}</div>
            @enderror

            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">
                Send Reset Link
            </button>
        </form>
    </div>
</div>
@endsection