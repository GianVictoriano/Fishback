<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LiteraryWork;
use App\Models\LiteraryWorkMetric;
use App\Models\LiteraryWorkReaction;
use App\Models\LiteraryWorkInteraction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LiteraryWorkController extends Controller
{
    /**
     * Create a new literary work with Heyzine URL
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'heyzine_url' => 'required|url',
        ]);

        if ($validator->fails()) {
            Log::error('Literary work validation failed:', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->all(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            
            // Validate that it's a Heyzine URL
            $heyzineUrl = $request->heyzine_url;
            if (!str_contains($heyzineUrl, 'heyzine.com')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please provide a valid Heyzine flipbook URL'
                ], 422);
            }
            
            // Create literary work record
            $literaryWork = LiteraryWork::create([
                'title' => $request->title,
                'description' => $request->description,
                'user_id' => $user->id,
                'heyzine_url' => $heyzineUrl,
                'status' => 'published',
                'published_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Literary work created successfully',
                'data' => [
                    'id' => $literaryWork->id,
                    'title' => $literaryWork->title,
                    'description' => $literaryWork->description,
                    'heyzine_url' => $literaryWork->heyzine_url,
                    'status' => $literaryWork->status,
                    'published_at' => $literaryWork->published_at,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating literary work: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create literary work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all literary works
     */
    public function index(Request $request)
    {
        try {
            $query = LiteraryWork::with(['user', 'metrics']);

            // Filter by status if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // If authenticated, filter based on user role
            if (Auth::check()) {
                if (Auth::user()->profile && Auth::user()->profile->role === 'admin') {
                    // Admins can see all works
                } else {
                    // Regular users: show all published works + their own works
                    $query->where(function ($q) {
                        $q->where('status', 'published')
                          ->orWhere('user_id', Auth::id());
                    });
                }
            } else {
                // If not authenticated, only show published works
                $query->where('status', 'published');
            }

            $literaryWorks = $query->orderBy('created_at', 'desc')->get();

            // Add user's reaction to each work if authenticated
            if (Auth::check()) {
                $literaryWorks->each(function ($work) {
                    $userReaction = LiteraryWorkReaction::where('literary_work_id', $work->id)
                        ->where('user_id', Auth::id())
                        ->first();
                    $work->userReaction = $userReaction?->reaction_type;
                });
            }

            return response()->json([
                'success' => true,
                'data' => $literaryWorks
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching literary works: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch literary works: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific literary work
     */
    public function show($id)
    {
        try {
            $literaryWork = LiteraryWork::with(['user', 'metrics'])
                ->findOrFail($id);

            // If authenticated, check if user can view this work
            if (Auth::check()) {
                if ((!Auth::user()->profile || Auth::user()->profile->role !== 'admin') && $literaryWork->user_id !== Auth::id()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized'
                    ], 403);
                }
                
                // Add user's reaction
                $userReaction = LiteraryWorkReaction::where('literary_work_id', $id)
                    ->where('user_id', Auth::id())
                    ->first();
                $literaryWork->userReaction = $userReaction?->reaction_type;
            } else {
                // If not authenticated, only allow viewing published works
                if ($literaryWork->status !== 'published') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized'
                    ], 403);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $literaryWork
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching literary work: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch literary work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Track a visit to a literary work
     * Only increments visit count once per unique IP address per day
     */
    public function trackVisit($id)
    {
        try {
            $literaryWork = LiteraryWork::findOrFail($id);
            $userIp = request()->ip();
            $today = now()->toDateString();
            
            // Get or create metrics record
            $metrics = $literaryWork->metrics ?? LiteraryWorkMetric::create([
                'literary_work_id' => $id,
                'visits' => 0,
                'like_count' => 0,
                'heart_count' => 0,
                'sad_count' => 0,
                'wow_count' => 0,
            ]);

            // Check if this IP has already visited today
            $existingVisit = LiteraryWorkInteraction::where('literary_work_id', $id)
                ->where('interaction_type', 'view')
                ->where('ip_address', $userIp)
                ->whereDate('created_at', $today)
                ->first();

            // Only increment if this is a new unique visitor (new IP or first visit today)
            if (!$existingVisit) {
                $metrics->incrementVisits();
                Log::info('Literary work visit incremented', [
                    'literary_work_id' => $id,
                    'user_id' => Auth::id(),
                    'ip' => $userIp,
                    'new_visit_count' => $metrics->visits,
                ]);
            } else {
                Log::info('Literary work visit already recorded today', [
                    'literary_work_id' => $id,
                    'user_id' => Auth::id(),
                    'ip' => $userIp,
                ]);
            }

            // Always record the interaction (for analytics)
            LiteraryWorkInteraction::create([
                'literary_work_id' => $id,
                'user_id' => Auth::id(),
                'interaction_type' => 'view',
                'ip_address' => $userIp,
            ]);

            // Get user's current reaction
            $userReaction = LiteraryWorkReaction::where('literary_work_id', $id)
                ->where('user_id', Auth::id())
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Visit tracked successfully',
                'metrics' => $metrics->fresh(),
                'is_new_visit' => !$existingVisit,
                'userReaction' => $userReaction?->reaction_type,
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
     * Track a reaction to a literary work
     */
    public function trackReaction($id, Request $request)
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

            $literaryWork = LiteraryWork::findOrFail($id);
            $reactionType = $request->type;

            // Get or create metrics record
            $metrics = $literaryWork->metrics ?? LiteraryWorkMetric::create([
                'literary_work_id' => $id,
                'visits' => 0,
                'like_count' => 0,
                'heart_count' => 0,
                'sad_count' => 0,
                'wow_count' => 0,
            ]);

            // Check if user already has ANY reaction to this work
            $existingReaction = LiteraryWorkReaction::where('literary_work_id', $id)
                ->where('user_id', Auth::id())
                ->first();

            if ($existingReaction) {
                // If reacting with same type, remove it (toggle off)
                if ($existingReaction->reaction_type === $reactionType) {
                    $metrics->decrement("{$reactionType}_count");
                    $existingReaction->delete();
                    
                    Log::info('Literary work reaction removed', [
                        'literary_work_id' => $id,
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
                    $metrics->incrementReaction($reactionType);
                    
                    Log::info('Literary work reaction changed', [
                        'literary_work_id' => $id,
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

            // Create new reaction (user has no existing reaction)
            LiteraryWorkReaction::create([
                'literary_work_id' => $id,
                'user_id' => Auth::id(),
                'reaction_type' => $reactionType,
                'ip_address' => request()->ip(),
            ]);

            // Increment metric
            $metrics->incrementReaction($reactionType);

            Log::info('Literary work reaction tracked', [
                'literary_work_id' => $id,
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

    /**
     * Track user interactions (time spent, scroll depth)
     */
    public function trackInteraction($id, Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'interaction_type' => 'required|in:view,time_spent,scroll',
                'time_spent' => 'nullable|integer|min:0',
                'scroll_percentage' => 'nullable|integer|min:0|max:100',
                'session_id' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $literaryWork = LiteraryWork::findOrFail($id);

            // Create interaction record
            $interaction = LiteraryWorkInteraction::create([
                'literary_work_id' => $id,
                'user_id' => Auth::id(),
                'interaction_type' => $request->interaction_type,
                'time_spent' => $request->time_spent,
                'scroll_percentage' => $request->scroll_percentage,
                'session_id' => $request->session_id,
                'ip_address' => request()->ip(),
            ]);

            Log::info('Literary work interaction tracked', [
                'literary_work_id' => $id,
                'user_id' => Auth::id(),
                'interaction_type' => $request->interaction_type,
                'time_spent' => $request->time_spent,
                'scroll_percentage' => $request->scroll_percentage,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Interaction tracked successfully',
                'data' => $interaction
            ]);

        } catch (\Exception $e) {
            Log::error('Error tracking interaction: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to track interaction: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get metrics for a literary work
     */
    public function getMetrics($id)
    {
        try {
            $literaryWork = LiteraryWork::findOrFail($id);
            
            $metrics = $literaryWork->metrics ?? LiteraryWorkMetric::create([
                'literary_work_id' => $id,
                'visits' => 0,
                'like_count' => 0,
                'heart_count' => 0,
                'sad_count' => 0,
                'wow_count' => 0,
            ]);

            return response()->json([
                'success' => true,
                'data' => $metrics
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching metrics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch metrics: ' . $e->getMessage()
            ], 500);
        }
    }
}
