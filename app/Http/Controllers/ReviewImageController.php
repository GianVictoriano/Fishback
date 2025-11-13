<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\ReviewImage;
use App\Models\ChatMessage;
use Exception;

class ReviewImageController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:20480',
            'group_id' => 'required|integer|exists:group_chats,id',
            'user_id' => 'required|integer|exists:users,id',
            'current_reviewer_id' => 'nullable|integer|exists:users,id',
            'review_stage' => 'sometimes|string|in:initial,peer_review,lead_review,approved',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $path = $request->file('image')->store('review_images', 'public');
        $reviewImage = ReviewImage::create([
            'file' => $path,
            'group_id' => $request->input('group_id'),
            'user_id' => $request->input('user_id'),
            'current_reviewer_id' => $request->input('current_reviewer_id'),
            'review_stage' => $request->input('review_stage', 'initial'),
            'status' => 'pending',
            'no_of_approval' => 0,
            'uploaded_at' => now(),
            'is_folio_submission' => $request->input('is_folio_submission', false),
            'folio_id' => $request->input('folio_id'),
        ]);
        
        // Update group_chat track status to 'review' when file is uploaded
        \DB::table('group_chats')
            ->where('id', $request->input('group_id'))
            ->update(['track' => 'review']);
        
        // Post system message to group chat
        ChatMessage::create([
            'user_id' => null,
            'message' => 'An image draft was uploaded by ' . (auth()->user() ? auth()->user()->name : 'a user') . ' and is awaiting review.',
            'group_chat_id' => $reviewImage->group_id,
            'system' => true,
            'type' => 'sent',
        ]);
        return response()->json($reviewImage, 201);
    }

    public function approve($id)
    {
        try {
            $reviewImage = ReviewImage::findOrFail($id);
            $reviewImage->status = 'approved';
            $reviewImage->no_of_approval += 1;
            $reviewImage->save();
            
            // Update group_chat track status to 'approved' when lead reviewer approves
            \DB::table('group_chats')
                ->where('id', $reviewImage->group_id)
                ->update(['track' => 'approved']);
            
            // Post system message
            ChatMessage::create([
                'user_id' => null,
                'message' => 'The image draft was approved by ' . (auth()->user() ? auth()->user()->name : 'a reviewer') . '.',
                'group_chat_id' => $reviewImage->group_id,
                'system' => true,
                'type' => 'approve',
            ]);
            return response()->json($reviewImage);
        } catch (Exception $e) {
            Log::error("Failed to approve review image for id {$id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to approve image.'], 500);
        }
    }

    public function reject($id)
    {
        try {
            $reviewImage = ReviewImage::findOrFail($id);
            $reviewImage->status = 'rejected';
            $reviewImage->save();
            // Post system message
            ChatMessage::create([
                'user_id' => null,
                'message' => 'The image draft was rejected by ' . (auth()->user() ? auth()->user()->name : 'a reviewer') . '.',
                'group_chat_id' => $reviewImage->group_id,
                'system' => true,
                'type' => 'reject',
            ]);
            return response()->json($reviewImage);
        } catch (Exception $e) {
            Log::error("Failed to reject review image for id {$id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to reject image.'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $reviewImage = ReviewImage::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'current_reviewer_id' => 'sometimes|integer|exists:users,id',
                'review_stage' => 'sometimes|string|in:initial,peer_review,lead_review,approved',
            ]);
            
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            
            if ($request->has('current_reviewer_id')) {
                $reviewImage->current_reviewer_id = $request->current_reviewer_id;
            }
            
            if ($request->has('review_stage')) {
                $reviewImage->review_stage = $request->review_stage;
            }
            
            $reviewImage->save();
            
            return response()->json($reviewImage);
        } catch (Exception $e) {
            Log::error("Failed to update review image for id {$id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to update image.'], 500);
        }
    }

    public function show($id)
    {
        $reviewImage = ReviewImage::findOrFail($id);
        return response()->json($reviewImage);
    }

    public function index(Request $request)
    {
        $query = ReviewImage::query();
        if ($request->has('group_id')) {
            $query->where('group_id', $request->group_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('review_stage')) {
            $query->where('review_stage', $request->review_stage);
        }
        if ($request->has('current_reviewer_id')) {
            $query->where('current_reviewer_id', $request->current_reviewer_id);
        }
        
        // Exclude folio submissions - they should only appear in manage folio
        $query->where(function($q) {
            $q->where('is_folio_submission', false)
              ->orWhereNull('is_folio_submission');
        });
        
        $images = $query->with(['user:id,name', 'group:id,name,status'])->orderByDesc('uploaded_at')->get();
        return response()->json($images);
    }
}
