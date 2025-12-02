<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Folio;
use App\Models\FolioSubmission;
use App\Models\GroupChat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FolioController extends Controller
{
    /**
     * Get all folios
     */
    public function index(Request $request)
    {
        $query = Folio::with(['leadOrganizer', 'members']);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by journalists only
        if ($request->has('is_journalists_only')) {
            $query->where('is_journalists_only', $request->boolean('is_journalists_only'));
        }

        $folios = $query->orderBy('created_at', 'desc')->get();

        return response()->json($folios);
    }

    /**
     * Create a new folio
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'theme' => 'required|string|max:255',
            'lead_organizer_id' => 'required|exists:users,id',
            'is_journalists_only' => 'required|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'members' => 'array',
            'members.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            // Create the folio
            $folio = Folio::create([
                'title' => $request->title,
                'theme' => $request->theme,
                'lead_organizer_id' => $request->lead_organizer_id,
                'is_journalists_only' => $request->is_journalists_only,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'status' => 'draft',
            ]);

            // Attach members
            if ($request->has('members') && is_array($request->members)) {
                $folio->members()->attach($request->members);
            }

            // Create a group chat for this folio
            $groupChat = GroupChat::create([
                'name' => $request->title, // Use folio title as group chat name
                'scrum_board_id' => null, // Not associated with a scrum board
            ]);

            // Add all members to the group chat (including lead organizer)
            $allMemberIds = $request->members ?? [];
            if (!in_array($request->lead_organizer_id, $allMemberIds)) {
                $allMemberIds[] = $request->lead_organizer_id;
            }
            $groupChat->members()->attach($allMemberIds);

            // Link the group chat to the folio
            $folio->group_chat_id = $groupChat->id;
            $folio->save();

            // Load relationships
            $folio->load(['leadOrganizer', 'members', 'groupChat']);

            DB::commit();

            return response()->json([
                'message' => 'Literary folio created successfully!',
                'folio' => $folio
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to create folio', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            return response()->json([
                'message' => 'Failed to create folio',
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Get a specific folio
     */
    public function show($id)
    {
        $folio = Folio::with(['leadOrganizer', 'members', 'submissions.user'])->findOrFail($id);

        return response()->json($folio);
    }

    /**
     * Update a folio
     */
    public function update(Request $request, $id)
    {
        $folio = Folio::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'theme' => 'sometimes|string|max:255',
            'lead_organizer_id' => 'sometimes|exists:users,id',
            'is_journalists_only' => 'sometimes|boolean',
            'status' => 'sometimes|in:draft,open,closed,published',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'members' => 'sometimes|array',
            'members.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $folio->update($request->only([
            'title',
            'theme',
            'lead_organizer_id',
            'is_journalists_only',
            'status',
            'start_date',
            'end_date',
        ]));

        // Update members if provided
        if ($request->has('members')) {
            $folio->members()->sync($request->members);
        }

        $folio->load(['leadOrganizer', 'members']);

        return response()->json([
            'message' => 'Folio updated successfully',
            'folio' => $folio
        ]);
    }

    /**
     * Delete a folio
     */
    public function destroy($id)
    {
        $folio = Folio::findOrFail($id);
        $folio->delete();

        return response()->json([
            'message' => 'Folio deleted successfully'
        ]);
    }

    /**
     * Submit a work to a folio
     */
    public function submitWork(Request $request, $id)
    {
        $folio = Folio::findOrFail($id);
        $user = Auth::user();

        // Check if user can submit
        if (!$folio->canUserSubmit($user)) {
            return response()->json([
                'message' => 'You do not have permission to submit to this folio.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:poem,short_story,essay,article,other',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $submission = FolioSubmission::create([
            'folio_id' => $folio->id,
            'user_id' => $user->id,
            'title' => $request->title,
            'content' => $request->content,
            'type' => $request->type,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $submission->load('user');

        return response()->json([
            'message' => 'Work submitted successfully!',
            'submission' => $submission
        ], 201);
    }

    /**
     * Get submissions for a folio
     */
    public function getSubmissions($id)
    {
        $folio = Folio::findOrFail($id);
        $submissions = $folio->submissions()->with(['user', 'reviewer'])->orderBy('submitted_at', 'desc')->get();

        return response()->json($submissions);
    }

    /**
     * Review a submission
     */
    public function reviewSubmission(Request $request, $folioId, $submissionId)
    {
        $folio = Folio::findOrFail($folioId);
        $submission = FolioSubmission::where('folio_id', $folioId)->findOrFail($submissionId);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected,revision_requested',
            'feedback' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $submission->update([
            'status' => $request->status,
            'feedback' => $request->feedback,
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
        ]);

        $submission->load(['user', 'reviewer']);

        return response()->json([
            'message' => 'Submission reviewed successfully',
            'submission' => $submission
        ]);
    }

    /**
     * Add member to folio
     */
    public function addMember(Request $request, $id)
    {
        $folio = Folio::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($folio->hasMember($request->user_id)) {
            return response()->json([
                'message' => 'User is already a member of this folio'
            ], 400);
        }

        $folio->members()->attach($request->user_id);

        return response()->json([
            'message' => 'Member added successfully'
        ]);
    }

    /**
     * Remove member from folio
     */
    public function removeMember($id, $userId)
    {
        $folio = Folio::findOrFail($id);

        if (!$folio->hasMember($userId)) {
            return response()->json([
                'message' => 'User is not a member of this folio'
            ], 400);
        }

        $folio->members()->detach($userId);

        return response()->json([
            'message' => 'Member removed successfully'
        ]);
    }

    /**
     * Get the current active folio submission period
     */
    public function getActivePeriod()
    {
        $now = now();
        
        $period = Folio::where('status', 'open')
            ->where('is_journalists_only', false)
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->first();

        if (!$period) {
            return response()->json([
                'message' => 'No active folio submission period'
            ], 404);
        }

        return response()->json($period);
    }
}
