<?php

namespace App\Http\Controllers;

use App\Models\ReviewComment;
use App\Models\ReviewContent;
use App\Models\ReviewImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class ReviewCommentController extends Controller
{
    /**
     * Store a newly created comment in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'review_content_id' => 'required_without:review_image_id|integer|exists:review_content,id',
                'review_image_id' => 'required_without:review_content_id|integer|exists:review_images,id',
                'comment' => 'required|string|max:1000',
                'start_index' => 'required|integer|min:0',
                'end_index' => 'required|integer|min:0|gt:start_index',
                'highlighted_text' => 'required|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Verify the user has access to this review content or image
            $user = $request->user();
            $groupId = null;
            
            if ($request->has('review_content_id')) {
                $reviewContent = ReviewContent::findOrFail($request->review_content_id);
                $groupId = $reviewContent->group_id;
            } elseif ($request->has('review_image_id')) {
                $reviewImage = ReviewImage::findOrFail($request->review_image_id);
                $groupId = $reviewImage->group_id;
            }
            
            if (!$user->groupChats()->where('group_chats.id', $groupId)->exists()) {
                return response()->json(['message' => 'Unauthorized to comment on this review.'], 403);
            }

            $comment = ReviewComment::create([
                'review_content_id' => $request->review_content_id,
                'review_image_id' => $request->review_image_id,
                'user_id' => $user->id,
                'comment' => $request->comment,
                'start_index' => $request->start_index,
                'end_index' => $request->end_index,
                'highlighted_text' => $request->highlighted_text,
            ]);

            $comment->load(['user:id,name', 'reviewContent:id,file', 'reviewImage:id,file']);

            return response()->json($comment, 201);

        } catch (Exception $e) {
            Log::error('Failed to create review comment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create comment.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display comments for a specific review content.
     *
     * @param  int  $reviewContentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function index($reviewContentId)
    {
        try {
            $reviewContent = ReviewContent::findOrFail($reviewContentId);
            $user = request()->user();
            
            // Verify the user has access to this review content
            if (!$user->groupChats()->where('group_chats.id', $reviewContent->group_id)->exists()) {
                return response()->json(['message' => 'Unauthorized to view comments for this review.'], 403);
            }

            $comments = ReviewComment::where('review_content_id', $reviewContentId)
                ->with(['user:id,name'])
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json($comments);

        } catch (Exception $e) {
            Log::error('Failed to fetch review comments: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch comments.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified comment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $comment = ReviewComment::findOrFail($id);
            $user = $request->user();

            // Only the comment author can update it
            if ($comment->user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized to update this comment.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'comment' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $comment->update(['comment' => $request->comment]);
            $comment->load(['user:id,name']);

            return response()->json($comment);

        } catch (Exception $e) {
            Log::error('Failed to update review comment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update comment.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified comment.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $comment = ReviewComment::findOrFail($id);
            $user = request()->user();

            // Only the comment author can delete it
            if ($comment->user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized to delete this comment.'], 403);
            }

            $comment->delete();

            return response()->json(['message' => 'Comment deleted successfully.']);

        } catch (Exception $e) {
            Log::error('Failed to delete review comment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete comment.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
