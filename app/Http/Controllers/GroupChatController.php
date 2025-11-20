<?php

namespace App\Http\Controllers;

use App\Models\GroupChat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupChatController extends Controller
{
    public function getGroupChats(Request $request)
    {
        // This log will tell us if authentication was successful
        Log::channel('stderr')->info('Inside getGroupChats. Authenticated User: ', ['user' => $request->user()]);

        if (!$request->user()) {
            Log::channel('stderr')->error('Authentication failed. User is null.');
            // The default 401 response will be sent automatically by the middleware
            return;
        }

        // ... your existing logic to fetch group chats
        $user = $request->user();
        $groupChats = $user->groupChats()->with('latestMessage.user')->get();
        return response()->json($groupChats);
    }
    public function index()
    {
        $user = Auth::user();
        // Temporarily load folio separately to avoid eager loading issues
        $chats = $user->groupChats()->with('scrumBoard')->get();
        
        // Load folio relationship for each chat
        $chats->load('folio');

        return response()->json($chats);
    }

    /**
     * Get a specific group chat with scrum board
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $groupChat = GroupChat::with('scrumBoard')->findOrFail($id);
            return response()->json($groupChat);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Group chat not found.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get all members of a specific group chat
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMembers($id)
    {
        try {
            $groupChat = GroupChat::findOrFail($id);
            
            // Get all members with their profiles
            $members = $groupChat->members()->with('profile')->get();
            
            return response()->json($members);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch group members.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update group chat status
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $groupChat = GroupChat::findOrFail($id);
            
            $validated = $request->validate([
                'status' => 'required|string|in:active,published,archived'
            ]);
            
            $groupChat->status = $validated['status'];
            $groupChat->save();
            
            return response()->json([
                'message' => 'Group chat status updated successfully.',
                'data' => $groupChat
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update group chat status.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
