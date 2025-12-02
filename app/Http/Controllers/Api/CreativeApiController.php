<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Creative;
use App\Models\CreativeMetrics;
use App\Models\CreativeReaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CreativeApiController extends Controller
{
    /**
     * Get a specific creative work
     */
    public function show($id)
    {
        try {
            $creative = Creative::with(['user', 'metrics', 'media'])
                ->findOrFail($id);

            // Check if creative is published
            if ($creative->status !== 'published') {
                return response()->json([
                    'success' => false,
                    'message' => 'This creative work is not available'
                ], 403);
            }

            // Get or create metrics
            $metrics = $creative->metrics ?? CreativeMetrics::create([
                'creative_id' => $id,
                'visits' => 0,
                'like_count' => 0,
                'heart_count' => 0,
                'sad_count' => 0,
                'wow_count' => 0,
            ]);

            return response()->json([
                'success' => true,
                'data' => $creative,
                'metrics' => $metrics
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching creative: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch creative: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Track a visit to a creative work
     * Only increments visit count once per unique IP address per day
     */
    public function visit($id)
    {
        try {
            $creative = Creative::findOrFail($id);
            
            // Check if creative is published
            if ($creative->status !== 'published') {
                return response()->json([
                    'success' => false,
                    'message' => 'This creative work is not available'
                ], 403);
            }

            $userIp = request()->ip();
            $today = now()->toDateString();
            
            // Get or create metrics record
            $metrics = $creative->metrics ?? CreativeMetrics::create([
                'creative_id' => $id,
                'visits' => 0,
                'like_count' => 0,
                'heart_count' => 0,
                'sad_count' => 0,
                'wow_count' => 0,
            ]);

            // Check if this IP has already visited today by checking the visit_tracking table
            // First, check if a visit tracking record exists for this IP today
            $existingVisit = DB::table('creative_visit_tracking')
                ->where('creative_id', $id)
                ->where('ip_address', $userIp)
                ->whereDate('visited_at', $today)
                ->first();

            // Only increment if this is a new unique visitor (new IP or first visit today)
            if (!$existingVisit) {
                $metrics->increment('visits');
                
                // Record this visit
                DB::table('creative_visit_tracking')->insert([
                    'creative_id' => $id,
                    'ip_address' => $userIp,
                    'user_id' => Auth::id(),
                    'visited_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                Log::info('Creative visit incremented', [
                    'creative_id' => $id,
                    'user_id' => Auth::id(),
                    'ip' => $userIp,
                    'new_visit_count' => $metrics->visits,
                ]);
            } else {
                Log::info('Creative visit already recorded today', [
                    'creative_id' => $id,
                    'user_id' => Auth::id(),
                    'ip' => $userIp,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Visit tracked successfully',
                'metrics' => $metrics->fresh(),
                'is_new_visit' => !$existingVisit,
            ]);

        } catch (\Exception $e) {
            Log::error('Error tracking visit: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to track visit: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Track a reaction to a creative work
     */
    public function react($id, Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'required|in:like,heart,sad,wow',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $creative = Creative::findOrFail($id);
            
            // Check if creative is published
            if ($creative->status !== 'published') {
                return response()->json([
                    'success' => false,
                    'message' => 'This creative work is not available'
                ], 403);
            }

            $reactionType = $request->type;

            // Get or create metrics record
            $metrics = $creative->metrics ?? CreativeMetrics::create([
                'creative_id' => $id,
                'visits' => 0,
                'like_count' => 0,
                'heart_count' => 0,
                'sad_count' => 0,
                'wow_count' => 0,
            ]);

            // Check if user/IP already has a reaction to this creative
            $existingReaction = CreativeReaction::where('creative_id', $id)
                ->where(function ($query) {
                    if (Auth::check()) {
                        $query->where('user_id', Auth::id());
                    } else {
                        $query->where('ip_address', request()->ip());
                    }
                })
                ->first();

            if ($existingReaction) {
                // If reacting with same type, remove it (toggle off)
                if ($existingReaction->reaction_type === $reactionType) {
                    $metrics->decrement("{$reactionType}_count");
                    $existingReaction->delete();
                    
                    Log::info('Creative reaction removed', [
                        'creative_id' => $id,
                        'user_id' => Auth::id(),
                        'reaction_type' => $reactionType,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Reaction removed successfully',
                        'metrics' => $metrics->fresh()
                    ]);
                } else {
                    // If reacting with different type, replace the old reaction
                    $oldType = $existingReaction->reaction_type;
                    $metrics->decrement("{$oldType}_count");
                    
                    $existingReaction->update(['reaction_type' => $reactionType]);
                    $metrics->increment("{$reactionType}_count");
                    
                    Log::info('Creative reaction changed', [
                        'creative_id' => $id,
                        'user_id' => Auth::id(),
                        'old_type' => $oldType,
                        'new_type' => $reactionType,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Reaction changed successfully',
                        'metrics' => $metrics->fresh()
                    ]);
                }
            }

            // Create new reaction
            CreativeReaction::create([
                'creative_id' => $id,
                'user_id' => Auth::id(),
                'reaction_type' => $reactionType,
                'ip_address' => request()->ip(),
            ]);

            // Increment metric
            $metrics->increment("{$reactionType}_count");

            Log::info('Creative reaction tracked', [
                'creative_id' => $id,
                'user_id' => Auth::id(),
                'reaction_type' => $reactionType,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reaction tracked successfully',
                'metrics' => $metrics->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error tracking reaction: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to track reaction: ' . $e->getMessage()
            ], 500);
        }
    }
}
