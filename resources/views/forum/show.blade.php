@extends('layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-900 to-black p-6">
    <div class="flex w-full max-w-7xl gap-8">
        
        <!-- Sidebar: Most Viewed Threads -->
        <div class="w-1/3 bg-white bg-opacity-10 backdrop-blur-md rounded-2xl p-6 text-white shadow-2xl border border-white/20">
            <h2 class="text-2xl font-extrabold mb-6 text-white tracking-wide flex items-center gap-2">
                🔥 <span>Most Viewed</span>
            </h2>

            @forelse($mostViewed as $popular)
                <div class="mb-4 p-3 bg-white bg-opacity-5 rounded-lg hover:bg-opacity-10 transition duration-200 border border-white/10 shadow-sm">
                    <a href="{{ route('threads.show', $popular) }}" class="text-lg font-semibold text-blue-300 hover:underline block">
                        {{ $popular->title }}
                    </a>
                    <div class="text-sm text-gray-300 mt-1">
                        👁️ {{ $popular->views }} {{ Str::plural('view', $popular->views) }}
                    </div>
                </div>
            @empty
                <p class="text-gray-400">No popular threads yet.</p>
            @endforelse
        </div>

        <!-- Main Content: Thread & Replies -->
        <div class="flex-1 bg-white bg-opacity-10 backdrop-blur-md rounded-2xl shadow-lg p-8 text-white border border-white/10">
            
            <!-- Thread Content -->
            <h1 class="text-3xl font-bold mb-2">{{ $thread->title }}</h1>
            <p class="mb-4 text-gray-300"><strong>By:</strong> {{ $thread->guest_name }}</p>
            <p class="mb-6 text-white/90">{{ $thread->content }}</p>

            <hr class="border-white border-opacity-20 mb-6">

            <!-- Replies Section -->
            <h3 class="text-2xl font-semibold mb-4">💬 Replies</h3>
            @forelse($thread->replies as $reply)
                <div class="bg-white bg-opacity-20 rounded-lg p-4 mb-3 border border-white/10 shadow-sm">
                    <p class="text-blue-200 font-semibold">{{ $reply->guest_name }}</p>
                    <p class="text-white mt-1">{{ $reply->content }}</p>
                </div>
            @empty
                <p class="text-gray-400 mb-6">No replies yet. Be the first to respond!</p>
            @endforelse

            <hr class="border-white border-opacity-20 mt-8 mb-6">

            <!-- Reply Form -->
            <h3 class="text-2xl font-semibold mb-4">✍️ Post a Reply</h3>
            <form method="POST" action="{{ route('replies.store', $thread) }}" class="space-y-5">
                @csrf

                <div>
                    <input type="text" name="guest_name" placeholder="Your Name"
                        value="{{ old('guest_name') }}"
                        class="w-full px-4 py-3 rounded-lg bg-white bg-opacity-90 text-black placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-400 shadow">
                    @error('guest_name')
                        <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <textarea name="content" placeholder="Your reply..." rows="4"
                        class="w-full px-4 py-3 rounded-lg bg-white bg-opacity-90 text-black placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-400 shadow resize-none">{{ old('content') }}</textarea>
                    @error('content')
                        <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                    class="w-full py-3 rounded-lg bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-indigo-600 hover:to-blue-500 transition text-white font-semibold shadow-md">
                    ➕ Post Reply
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
