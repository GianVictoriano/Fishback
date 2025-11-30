<?php

namespace App\Http\Controllers;

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BroadcastController extends Controller
{
    /**
     * Create a new broadcast.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'activity_date' => 'required|date',
            'activity_location' => 'nullable|string|max:255',
            'required_writers' => 'nullable|integer|min:0',
            'required_photographers' => 'nullable|integer|min:0',
            'available_collaborators' => 'required|array',
            'available_collaborators.*' => 'exists:users,id',
            'activity_id' => 'nullable|exists:activities,id',
        ]);

        try {
            DB::beginTransaction();

            // Create the broadcast
            $broadcast = Broadcast::create([
                'title' => $request->title,
                'description' => $request->description,
                'activity_date' => $request->activity_date,
                'activity_location' => $request->activity_location,
                'required_writers' => $request->required_writers,
                'required_photographers' => $request->required_photographers,
                'total_required_members' => ($request->required_writers ?: 0) + ($request->required_photographers ?: 0),
                'status' => 'pending',
                'sender_id' => Auth::id(),
                'total_recipients' => count($request->available_collaborators),
                'activity_id' => $request->activity_id, // Add activity_id if provided
            ]);

            // Create recipients for available collaborators
            $recipients = [];
            foreach ($request->available_collaborators as $collaboratorId) {
                // Find the availability info for this collaborator
                $availabilityInfo = $this->getCollaboratorAvailability($collaboratorId, $request->activity_date);
                
                $recipients[] = [
                    'broadcast_id' => $broadcast->id,
                    'user_id' => $collaboratorId,
                    'response_status' => 'pending',
                    'availability_type' => $availabilityInfo['type'] ?? null,
                    'availability_times' => $availabilityInfo['times'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            BroadcastRecipient::insert($recipients);

            // Mark broadcast as sent
            $broadcast->markAsSent();

            DB::commit();

            return response()->json([
                'message' => 'Broadcast sent successfully!',
                'broadcast' => $broadcast->load(['recipients.user', 'sender'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Failed to send broadcast: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get collaborator availability info for a specific date/time.
     */
    private function getCollaboratorAvailability($userId, $activityDate)
    {
        $dateTime = new \DateTime($activityDate);
        $dayOfWeek = $dateTime->format('l'); // Full day name (Monday, Tuesday, etc.)
        $timeString = $dateTime->format('H:i'); // HH:MM format

        $user = User::with('workingHours')->find($userId);
        
        if (!$user || !$user->workingHours) {
            return ['type' => null, 'times' => null];
        }

        // Find working hours for this day
        $dayHours = $user->workingHours
            ->where('day_of_week', strtolower($dayOfWeek))
            ->first();

        if (!$dayHours) {
            return ['type' => null, 'times' => null];
        }

        // Check if the activity time falls within preferred hours
        if ($dayHours->preferred_start_time && $dayHours->preferred_end_time &&
            $dayHours->preferred_start_time <= $timeString && 
            $dayHours->preferred_end_time >= $timeString) {
            
            return [
                'type' => 'preferred',
                'times' => $dayHours->preferred_start_time . '-' . $dayHours->preferred_end_time
            ];
        }

        // Check if the activity time falls within possible hours
        if ($dayHours->possible_start_time && $dayHours->possible_end_time &&
            $dayHours->possible_start_time <= $timeString && 
            $dayHours->possible_end_time >= $timeString) {
            
            return [
                'type' => 'possible',
                'times' => $dayHours->possible_start_time . '-' . $dayHours->possible_end_time
            ];
        }

        return ['type' => null, 'times' => null];
    }

    /**
     * Get all broadcasts sent by the authenticated user.
     */
    public function index(Request $request)
    {
        $broadcasts = Broadcast::with(['recipients.user', 'sender'])
            ->where('sender_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 10);

        return response()->json($broadcasts);
    }

    /**
     * Get broadcasts where the authenticated user is a recipient.
     */
    public function myBroadcasts(Request $request)
    {
        $broadcasts = Broadcast::with(['sender', 'recipients'])
            ->whereHas('recipients', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 10);

        return response()->json($broadcasts);
    }

    /**
     * Respond to a broadcast (accept/decline).
     */
    public function respond(Request $request, Broadcast $broadcast)
    {
        $request->validate([
            'response' => 'required|in:accepted,declined',
            'message' => 'nullable|string|max:500',
        ]);

        $recipient = $broadcast->recipients()
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($request->response === 'accepted') {
            $recipient->accept($request->message);
            
            // Add user to activity_members if the activity exists
            if ($broadcast->activity_id) {
                $activity = \App\Models\Activity::find($broadcast->activity_id);
                if ($activity) {
                    // Check if user is already a member
                    $existingMember = \App\Models\ActivityMember::where('activity_id', $broadcast->activity_id)
                        ->where('user_id', Auth::id())
                        ->first();
                    
                    if (!$existingMember) {
                        \App\Models\ActivityMember::create([
                            'activity_id' => $broadcast->activity_id,
                            'user_id' => Auth::id(),
                            'joined_at' => now(),
                            'status' => 'accepted'
                        ]);
                    }
                }
            }
        } else {
            $recipient->decline($request->message);
        }

        return response()->json([
            'message' => 'Response submitted successfully',
            'recipient' => $recipient
        ]);
    }
}
