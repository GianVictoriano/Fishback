<?php

namespace App\Http\Controllers;

use App\Models\ImportantNote;
use App\Models\GroupChat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ImportantNoteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ImportantNote::query();

        // Always eager-load the relations we need for the UI
        $query->with(['user:id,name', 'groupChat:id,name']);

        // Filter by group_chat_id when provided (kept for backward-compat)
        if ($request->filled('group_chat_id')) {
            $query->where('group_chat_id', $request->query('group_chat_id'));
        }

        // Polymorphic filters for a specific file (document or image)
        if ($request->filled('versionable_type')) {
            $query->where('versionable_type', $request->query('versionable_type'));
        }
        if ($request->filled('versionable_id')) {
            $query->where('versionable_id', $request->query('versionable_id'));
        }
        if ($request->filled('version')) {
            $query->where('version', $request->query('version'));
        }

        // By default return only active notes unless caller sets active_only=false
        $activeOnly = $request->boolean('active_only', true);
        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $notes = $query->orderByDesc('created_at')->get();

        return response()->json($notes);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'group_chat_id' => 'required|exists:group_chats,id',
            'content' => 'required|string|max:5000',
        ]);

        $user = Auth::user();

        // Check if user is a member of the group chat
        $isMember = GroupChat::find($request->group_chat_id)
            ->members()
            ->where('user_id', $user->id)
            ->exists();

        if (!$isMember) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $note = ImportantNote::create([
                'group_chat_id' => $request->group_chat_id,
                'user_id' => $user->id,
                'content' => $request->content,
                'is_active' => true,
            ]);

            $note->load(['user:id,name', 'groupChat:id,name']);

            return response()->json($note, 201);
        } catch (\Exception $e) {
            Log::error('Failed to create important note: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create note'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $note = ImportantNote::with(['user:id,name', 'groupChat:id,name'])->findOrFail($id);
        return response()->json($note);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $note = ImportantNote::findOrFail($id);
        $user = Auth::user();

        // Only the creator can update their note
        if ($note->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        try {
            $note->update([
                'content' => $request->content,
            ]);

            $note->load(['user:id,name', 'groupChat:id,name']);

            return response()->json($note);
        } catch (\Exception $e) {
            Log::error('Failed to update important note: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update note'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $note = ImportantNote::findOrFail($id);
        $user = Auth::user();

        // Only the creator can delete their note
        if ($note->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $note->delete();
            return response()->json(['message' => 'Note deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to delete important note: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to delete note'], 500);
        }
    }
}
