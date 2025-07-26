<?php

namespace App\Http\Controllers;

use App\Models\ReviewContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class ReviewContentController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:txt|max:2048',
                'group_id' => 'required|integer|exists:group_chats,id',
                'user_id' => 'required|integer|exists:users,id',
                'status' => 'sometimes|string',
                'no_of_approval' => 'sometimes|integer',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $path = $request->file('file')->store('review_uploads', 'public');

            $reviewContent = ReviewContent::create([
                'file' => $path,
                'group_id' => $request->input('group_id'),
                'user_id' => $request->input('user_id'),
                'status' => $request->input('status', 'pending'),
                'no_of_approval' => $request->input('no_of_approval', 0),
                'uploaded_at' => now(),
            ]);

            return response()->json($reviewContent, 201);

        } catch (Exception $e) {
            Log::error('Failed to create review content: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'message' => 'An unexpected error occurred on the server.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $query = ReviewContent::query();

            if ($request->has('group_id')) {
                $query->where('group_id', $request->group_id);
            } else {
                $groupIds = $user->groupChats()->pluck('group_chats.id');
                $query->whereIn('group_id', $groupIds);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $reviewContents = $query->with('user:id,name')->orderByDesc('uploaded_at')->get();

            return response()->json($reviewContents);

        } catch (Exception $e) {
            Log::error('Failed to fetch review content: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch review content.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve review content.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function approve($id)
    {
        try {
            $reviewContent = ReviewContent::findOrFail($id);
            $reviewContent->status = 'approved';
            $reviewContent->no_of_approval += 1;
            $reviewContent->save();
            return response()->json($reviewContent);
        } catch (Exception $e) {
            Log::error("Failed to approve review content for id {$id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to approve content.'], 500);
        }
    }

    /**
     * Reject review content.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function reject($id)
    {
        try {
            $reviewContent = ReviewContent::findOrFail($id);
            $reviewContent->status = 'rejected';
            $reviewContent->save();
            return response()->json($reviewContent);
        } catch (Exception $e) {
            Log::error("Failed to reject review content for id {$id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to reject content.'], 500);
        }
    }
}

