<?php

namespace App\Http\Controllers;

use App\Models\Thread;
use Illuminate\Http\Request;

class ThreadController extends Controller
{
    // Show list of all threads and most viewed
    public function index()
    {
        // Eager load replies count for each thread
        $threads = Thread::withCount('replies')->latest()->get();
        $mostViewed = Thread::orderByDesc('views')->take(5)->get();

        return view('forum.index', compact('threads', 'mostViewed'));
    }

    // Show create thread form with most viewed sidebar
    public function create()
    {
        $mostViewed = Thread::orderByDesc('views')->take(5)->get();

        return view('forum.create', compact('mostViewed'));
    }

    // Store new thread
    public function store(Request $request)
    {
        $request->validate([
            'guest_name' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        Thread::create([
            'guest_name' => $request->guest_name,
            'title' => $request->title,
            'content' => $request->content,
            'views' => 0, // default view count
        ]);

        return redirect()->route('threads.index')->with('success', 'Thread created successfully.');
    }

    // Show single thread and increment views
    public function show(Thread $thread)
    {
        // Increment view count
        $thread->increment('views');

        $mostViewed = Thread::orderByDesc('views')->take(5)->get();

        return view('forum.show', compact('thread', 'mostViewed'));
    }
}
