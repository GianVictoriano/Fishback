{{-- resources/views/forum/index.blade.php --}}
@extends('layouts.app')

@section('content')
    <div class="min-h-screen flex justify-center bg-gradient-to-br from-gray-900 to-black p-6">
        <div class="flex w-full max-w-7xl gap-8">
            <!-- Left Sidebar: Most Viewed -->
            <div class="w-1/3 bg-white bg-opacity-10 backdrop-blur-md rounded-2xl p-6 text-white shadow-2xl border border-white/20">
                <h2 class="text-2xl font-extrabold mb-6 text-white tracking-wide flex items-center gap-2">
                    🔥 <span>Most Viewed</span>
                </h2>

                @forelse($mostViewed as $popular)
                    <div class="mb-4 p-3 bg-white bg-opacity-5 rounded-lg hover:bg-opacity-10 transition duration-200 border border-white/10 shadow-sm">
                        <a href="{{ route('threads.show', $popular) }}" class="text-lg font-semibold text-blue-300 hover:underline block">
                            {{ $popular->title }}
                        </a>
                        <div class="text-sm text-gray-300 mt-1 flex items-center gap-1">
                            👁️ {{ $popular->views }} {{ Str::plural('view', $popular->views) }}
                        </div>
                    </div>
                @empty
                    <p class="text-gray-400">No popular threads yet.</p>
                @endforelse
            </div>

            <!-- Main Content -->
            <div class="flex-1 bg-white bg-opacity-10 backdrop-blur-md rounded-2xl shadow-lg p-8 text-white">
                <h1 class="text-3xl font-bold mb-6">Forum Threads</h1>

                <div class="mb-6">
                    <a href="{{ route('threads.create') }}"
                        class="inline-block px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full hover:from-indigo-600 hover:to-blue-500 transition text-white font-semibold">
                        Create New Thread
                    </a>
                </div>

                @php use Illuminate\Support\Str; @endphp

                @foreach ($threads as $thread)
                    <div class="bg-white bg-opacity-20 rounded-xl p-4 mb-4 hover:bg-opacity-30 transition">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-white">
                                Title: <a href="{{ route('threads.show', $thread) }}" class="text-blue-300 hover:underline">
                                    {{ $thread->title }}
                                </a>
                            </h3>
                            <span class="text-gray-300 text-sm">
                                💬 {{ $thread->replies_count }} {{ Str::plural('reply', $thread->replies_count) }}
                            </span>
                        </div>
                        <p class="text-gray-400 text-sm mt-1">By: {{ $thread->guest_name }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
