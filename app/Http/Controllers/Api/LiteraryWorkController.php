<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LiteraryWork;
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
            $query = LiteraryWork::with(['user']);

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
            $literaryWork = LiteraryWork::with(['user'])
                ->findOrFail($id);

            // If authenticated, check if user can view this work
            if (Auth::check()) {
                if ((!Auth::user()->profile || Auth::user()->profile->role !== 'admin') && $literaryWork->user_id !== Auth::id()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized'
                    ], 403);
                }
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
}
