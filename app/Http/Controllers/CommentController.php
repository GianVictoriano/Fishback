<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    // POST /api/topics/{topic}/comments
    public function store(Request $request, Topic $topic)
    {
        $validator = Validator::make($request->all(), [
            'body' => 'required|string',
            'secret' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $comment = Comment::create([
            'topic_id' => $topic->id,
            'user_id'  => $request->user()->id,
            'body'     => $request->body,
            'secret'   => $request->secret,
            'status'   => Comment::STATUS_ACTIVE,
        ])->load('user:id,name');

        return response()->json($comment, 201);
    }

    public function report(Comment $comment)
    {
        $comment->update(['status' => Comment::STATUS_REPORTED]);
        return response()->json(['message' => 'Comment has been reported']);
    }

    public function delete(Comment $comment)
    {
        $comment->update(['status' => Comment::STATUS_DELETED]);
        return response()->json(['message' => 'Comment has been deleted']);
    }
}
