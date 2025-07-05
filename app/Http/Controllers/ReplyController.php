<?php

namespace App\Http\Controllers;

use App\Models\Reply;
use App\Models\Thread;
use Illuminate\Http\Request;

class ReplyController extends Controller
{
    public function store(Request $request, Thread $thread)
    {
        $request->validate([
            'guest_name' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $thread->replies()->create($request->only('guest_name', 'content'));

        return redirect()->route('threads.show', $thread)->with('success', 'Reply posted successfully.');
    }
}
