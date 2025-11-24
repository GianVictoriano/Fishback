<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

//aguys
class ActivityController extends Controller
{
    /**
     * Display a listing of activities.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Get activities where user is creator or member
        $activities = Activity::where('created_by', $user->id)
            ->orWhereHas('members', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with(['creator', 'members'])
            ->orderBy('date', 'desc')
            ->get();

        return response()->json([
            'activities' => $activities
        ]);
    }

    /**
     * Store a newly created activity in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'required_members' => 'nullable|integer|min:1',
            'members' => 'nullable|array',
            'members.*' => 'exists:users,id',
        ]);

        try {
            $activity = DB::transaction(function () use ($validated, $request) {
                // 1. Create the Activity
                $activity = Activity::create([
                    'title' => $validated['title'],
                    'date' => $validated['date'],
                    'description' => $validated['description'] ?? null,
                    'location' => $validated['location'] ?? null,
                    'required_members' => $validated['required_members'] ?? null,
                    'created_by' => Auth::id(),
                    'status' => 'scheduled',
                ]);

                // 2. Collect all member IDs and ensure creator is included
                $memberIds = $validated['members'] ?? [];
                $creatorId = Auth::id();
                
                // Add creator to members if not already included
                if (!in_array($creatorId, $memberIds)) {
                    $memberIds[] = $creatorId;
                }

                // 3. Add all members to the activity
                foreach ($memberIds as $userId) {
                    ActivityMember::create([
                        'activity_id' => $activity->id,
                        'user_id' => $userId,
                        // Creator gets 'accepted' status, others get 'invited'
                        'status' => $userId == $creatorId ? 'accepted' : 'invited',
                    ]);
                }

                return $activity;
            });

            // Load relationships for the response
            $activity->load(['creator', 'members']);

            return response()->json([
                'message' => 'Activity created successfully!',
                'activity' => $activity
            ], 201);

        } catch (\Exception $e) {
            // Log the error for debugging
            report($e);

            return response()->json([
                'message' => 'An error occurred while creating the activity.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified activity.
     */
    public function show(string $id)
    {
        $activity = Activity::with(['creator', 'members', 'activityMembers.user'])
            ->findOrFail($id);

        return response()->json([
            'activity' => $activity
        ]);
    }

    /**
     * Update the specified activity.
     */
    public function update(Request $request, string $id)
    {
        $activity = Activity::findOrFail($id);

        // Check if user is the creator
        if ($activity->created_by !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized. Only the creator can update this activity.'
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'date' => 'sometimes|required|date',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'required_members' => 'nullable|integer|min:1',
            'status' => 'sometimes|required|in:scheduled,ongoing,completed,cancelled',
        ]);

        $activity->update($validated);

        return response()->json([
            'message' => 'Activity updated successfully!',
            'activity' => $activity->load(['creator', 'members'])
        ]);
    }

    /**
     * Remove the specified activity.
     */
    public function destroy(string $id)
    {
        $activity = Activity::findOrFail($id);

        // Check if user is the creator
        if ($activity->created_by !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized. Only the creator can delete this activity.'
            ], 403);
        }

        $activity->delete();

        return response()->json([
            'message' => 'Activity deleted successfully!'
        ]);
    }

    /**
     * Update member status (accept/decline invitation, mark attendance).
     */
    public function updateMemberStatus(Request $request, string $activityId)
    {
        $validated = $request->validate([
            'status' => 'required|in:invited,accepted,declined,attended',
            'notes' => 'nullable|string',
        ]);

        $activityMember = ActivityMember::where('activity_id', $activityId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $activityMember->update($validated);

        return response()->json([
            'message' => 'Status updated successfully!',
            'activity_member' => $activityMember
        ]);
    }

    /**
     * Add a member to an activity.
     */
    public function addMember(Request $request, string $activityId)
    {
        $activity = Activity::findOrFail($activityId);

        // Check if user is the creator
        if ($activity->created_by !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized. Only the creator can add members.'
            ], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        // Check if member already exists
        $exists = ActivityMember::where('activity_id', $activityId)
            ->where('user_id', $validated['user_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'User is already a member of this activity.'
            ], 400);
        }

        $activityMember = ActivityMember::create([
            'activity_id' => $activityId,
            'user_id' => $validated['user_id'],
            'status' => 'invited',
        ]);

        return response()->json([
            'message' => 'Member added successfully!',
            'activity_member' => $activityMember->load('user')
        ], 201);
    }

    /**
     * Remove a member from an activity.
     */
    public function removeMember(Request $request, string $activityId, string $userId)
    {
        $activity = Activity::findOrFail($activityId);

        // Check if user is the creator
        if ($activity->created_by !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized. Only the creator can remove members.'
            ], 403);
        }

        $activityMember = ActivityMember::where('activity_id', $activityId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $activityMember->delete();

        return response()->json([
            'message' => 'Member removed successfully!'
        ]);
    }
}
