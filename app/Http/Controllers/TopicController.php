<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TopicController extends Controller
{
    // GET /api/topics
    public function index()
    {
        $topics = Topic::with('user:id,name', 'comments')->latest()->get();
        return response()->json($topics);
    }

    // POST /api/topics
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'category' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $topic = Topic::create([
            'user_id' => $request->user()->id,
            'title'   => $request->title,
            'body'    => $request->body,
            'category' => $request->category,
        ]);

        return response()->json($topic, 201);
    }

    // GET /api/topics/{topic}
    public function show(Topic $topic)
    {
        $topic->load('user:id,name', 'comments.user:id,name');
        return response()->json($topic);
    }
}
