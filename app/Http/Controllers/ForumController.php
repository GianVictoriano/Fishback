<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Thread;
use App\Models\Reply;

class ForumController extends Controller
{
    // Show all threads
    public function index()
    {
        $threads = Thread::latest()->withCount('replies')->get();
        return view('forum.index', compact('threads'));
    }

    // Show form to create new thread
    public function create()
    {
        return view('forum.create');
    }

    // Store new thread
    public function store(Request $request)
    {
        $request->validate([
            'guest_name' => 'required|max:255',
            'title' => 'required|max:255',
            'content' => 'required',
        ]);

        Thread::create($request->only('guest_name', 'title', 'content'));

        return redirect()->route('forum.index')->with('success', 'Thread created!');
    }

    // Show a single thread and its replies
    public function show($id)
    {
        $thread = Thread::with('replies')->findOrFail($id);
        return view('forum.show', compact('thread'));
    }

    // Store a reply
    public function storeReply(Request $request, $threadId)
    {
        $request->validate([
            'guest_name' => 'required|max:255',
            'content' => 'required',
        ]);

        $thread = Thread::findOrFail($threadId);

        $thread->replies()->create([
            'guest_name' => $request->guest_name,
            'content' => $request->content,
        ]);

        return redirect()->route('forum.show', $threadId)->with('success', 'Reply posted!');
    }
}
