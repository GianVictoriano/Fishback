<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TopicController extends Controller
{
    // GET /api/topics
    public function index(Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = 20;
        
        $query = Topic::with(['user.profile'])
            ->withCount('comments'); // Add comment count
        
        // Handle search on backend
        if ($request->has('search')) {
            $search = $request->get('search');
            if (str_starts_with($search, '#')) {
                // Search in category for hashtag searches
                $hashtag = substr($search, 1);
                $query->where('category', 'LIKE', '%'.$hashtag.'%');
            } else {
                // Search in title for regular searches
                $query->where('title', 'LIKE', '%'.$search.'%');
            }
        }
        
        // Handle category filter
        if ($request->has('category') && $request->get('category') !== 'All') {
            $query->where('category', $request->get('category'));
        }
        
        $topics = $query->latest()->paginate($perPage, ['*'], 'page', $page);
        
        return response()->json($topics);
    }

    // POST /api/topics
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'category' => 'nullable|string|max:50',
            'secret' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $topic = Topic::create([
            'user_id' => $request->user()->id,
            'title'   => $request->title,
            'body'    => $request->body,
            'category' => $request->category,
            'secret'  => $request->secret,
            'status'  => Topic::STATUS_ACTIVE,
        ]);

        return response()->json($topic, 201);
    }

    public function report(Topic $topic)
    {
        $topic->update(['status' => Topic::STATUS_REPORTED]);
        return response()->json(['message' => 'Topic has been reported']);
    }

    // GET /api/users/{userId}/posts-count
    public function getUserPostsCount($userId)
    {
        $count = Topic::where('user_id', $userId)
            ->where('status', Topic::STATUS_ACTIVE)
            ->count();
        
        return response()->json(['count' => $count]);
    }

    public function delete(Topic $topic)
    {
        $topic->update(['status' => Topic::STATUS_DELETED]);
        return response()->json(['message' => 'Topic has been deleted']);
    }

    // GET /api/topics/{topic}
    public function show(Topic $topic)
    {
        $topic->load(['user.profile', 'comments.user.profile']);
        return response()->json($topic);
    }
}
