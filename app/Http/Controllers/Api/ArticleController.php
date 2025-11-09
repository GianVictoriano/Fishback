<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Services\RecommendationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ArticleController extends Controller
{
    public function index()
    {
        $articles = Article::with(['media', 'user'])
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(10);

        return ArticleResource::collection($articles);
    }

    public function publicArticles(Request $request)
    {
        $query = Article::with(['media', 'user'])
            ->where('status', 'published')
            ->whereNotNull('published_at');

        if ($request->has('genre')) {
            $query->where('genre', $request->input('genre'));
        }

        $articles = $query->orderBy('published_at', 'desc')->paginate(15);

        return ArticleResource::collection($articles);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'genre' => 'required|in:articles,opinions,sports,editorial,artworks',
                'media' => 'nullable|array',
                'media.*' => 'file|mimes:jpg,jpeg,png,gif,pdf,doc,docx|max:10240',
                'post_to_facebook' => 'nullable|boolean',
            ]);

            $article = Article::create([
                'user_id' => Auth::id(),
                'title' => $validated['title'],
                'content' => $validated['content'],
                'genre' => $validated['genre'],
                'status' => 'published',
                'published_at' => now(),
            ]);

            if ($request->hasFile('media')) {
                $this->handleMediaUploads($request->file('media'), $article);
            }

            // Post to Facebook if requested
            if ($request->input('post_to_facebook', false)) {
                try {
                    $this->postToFacebook($article);
                } catch (\Exception $e) {
                    Log::error('Failed to post article to Facebook: ' . $e->getMessage());
                    // Continue even if Facebook posting fails
                }
            }

            return new ArticleResource($article->load('media'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error creating article: ' . $e->getMessage());
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error creating article: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to create article', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(Article $article)
    {
        $article->load(['media', 'user', 'metrics']);
        return new ArticleResource($article);
    }

    public function trendingArticles(Request $request)
    {
        $threeDaysAgo = now()->subDays(3);
        $limit = 10;
        $genre = $request->query('genre');

        // First, get trending articles from the last 3 days
        $trendingQuery = Article::with(['media', 'user', 'metrics'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->whereDate('published_at', '>=', $threeDaysAgo);
        
        // Apply genre filter if provided
        if ($genre) {
            $trendingQuery->where('genre', $genre);
        }
        
        $trendingArticles = $trendingQuery
            ->leftJoin('article_metrics', 'articles.id', '=', 'article_metrics.article_id')
            ->orderByRaw('ISNULL(article_metrics.visits) ASC, article_metrics.visits DESC')
            ->select('articles.*')
            ->limit($limit)
            ->get();

        // If we don't have enough trending articles, get the most recent ones to fill the gap
        if ($trendingArticles->count() < $limit) {
            $remaining = $limit - $trendingArticles->count();
            $excludedIds = $trendingArticles->pluck('id')->toArray();
            
            $recentQuery = Article::with(['media', 'user', 'metrics'])
                ->where('status', 'published')
                ->whereNotNull('published_at')
                ->whereNotIn('id', $excludedIds);
            
            // Apply genre filter if provided
            if ($genre) {
                $recentQuery->where('genre', $genre);
            }
            
            $recentArticles = $recentQuery
                ->orderBy('published_at', 'desc')
                ->limit($remaining)
                ->get();

            $articles = $trendingArticles->merge($recentArticles);
        } else {
            $articles = $trendingArticles;
        }

        return ArticleResource::collection($articles);
    }

    public function react(Request $request, Article $article)
    {
        try {
            $request->validate(['type' => 'required|in:like,heart,sad,wow']);

            $user = $request->user();
            $ip = $request->ip();
            $reactionType = $request->input('type');

            $query = $article->reactions();
            if ($user) {
                $query->where('user_id', $user->id);
            } else {
                $query->where('ip_address', $ip);
            }

            $existingReaction = $query->first();
            $metrics = $article->metrics()->firstOrCreate(['article_id' => $article->id]);

            if ($existingReaction) {
                if ($existingReaction->reaction_type === $reactionType) {
                    $metrics->decrement($reactionType . '_count');
                    $existingReaction->delete();
                    return response()->json(['message' => 'Reaction removed', 'metrics' => $metrics->fresh()]);
                } else {
                    $metrics->decrement($existingReaction->reaction_type . '_count');
                    $existingReaction->update(['reaction_type' => $reactionType]);
                }
            } else {
                $article->reactions()->create([
                    'user_id' => $user ? $user->id : null,
                    'ip_address' => $ip,
                    'reaction_type' => $reactionType,
                ]);
            }

            $metrics->increment($reactionType . '_count');

            return response()->json(['message' => 'Reaction recorded', 'metrics' => $metrics->fresh()]);
        } catch (\Exception $e) {
            Log::error('Error in react method: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'An internal error occurred'], 500);
        }
    }

    public function visit(Request $request, Article $article): JsonResponse
    {
        $ip = $request->ip();
        $metrics = $article->metrics()->firstOrCreate(['article_id' => $article->id]);

        $visitorIps = $metrics->visitor_ips ?? [];
        if (!in_array($ip, $visitorIps)) {
            $visitorIps[] = $ip;
            $metrics->visitor_ips = $visitorIps;
            $metrics->increment('visits');
            $metrics->save();
        }

        return response()->json(['visits' => $metrics->visits]);
    }

    /**
     * Get personalized recommendations for the user
     */
    public function recommendations(Request $request)
    {
        try {
            $userId = $request->user() ? $request->user()->id : null;
            $limit = $request->input('limit', 5);
            
            $recommendationEngine = new RecommendationEngine();
            $recommendations = $recommendationEngine->getRecommendations($userId, $limit);
            
            return ArticleResource::collection($recommendations);
        } catch (\Exception $e) {
            Log::error('Error getting recommendations: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to get recommendations'], 500);
        }
    }

    /**
     * Record user interaction for ML learning
     */
    public function recordInteraction(Request $request, Article $article)
    {
        try {
            $validated = $request->validate([
                'interaction_type' => 'required|in:view,like,heart,sad,wow,time_spent,scroll',
                'time_spent' => 'nullable|integer|min:0',
                'scroll_percentage' => 'nullable|numeric|min:0|max:100',
                'session_id' => 'nullable|string',
            ]);

            $userId = $request->user() ? $request->user()->id : null;
            
            if (!$userId) {
                return response()->json(['message' => 'User authentication required'], 401);
            }

            $recommendationEngine = new RecommendationEngine();
            $recommendationEngine->recordInteraction(
                $userId,
                $article->id,
                $validated['interaction_type'],
                [
                    'time_spent' => $validated['time_spent'] ?? 0,
                    'scroll_percentage' => $validated['scroll_percentage'] ?? 0,
                    'session_id' => $validated['session_id'] ?? null,
                ]
            );

            return response()->json(['message' => 'Interaction recorded successfully']);
        } catch (\Exception $e) {
            Log::error('Error recording interaction: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to record interaction'], 500);
        }
    }

    public function uploadMedia(Request $request)
    {
        try {
            $request->validate([
                'media' => 'required|array',
                'media.*' => 'file|mimes:jpg,jpeg,png,gif|max:10240',
            ]);

            $uploadedFiles = [];

            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $file) {
                    try {
                        if (!$file->isValid()) {
                            Log::error('Invalid file uploaded: ' . $file->getClientOriginalName());
                            continue;
                        }

                        $fileName = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                        $path = $file->storeAs('articles/inline', $fileName, 'public');
                        
                        if (!$path) {
                            throw new \Exception('Failed to store file: ' . $file->getClientOriginalName());
                        }
                        
                        $uploadedFiles[] = [
                            'file_path' => $path,
                            'file_name' => $file->getClientOriginalName(),
                            'file_type' => $file->getClientOriginalExtension(),
                            'mime_type' => $file->getMimeType(),
                            'size' => $file->getSize(),
                        ];
                        
                    } catch (\Exception $e) {
                        Log::error('Error uploading media: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                        continue;
                    }
                }
            }

            return response()->json([
                'message' => 'Media uploaded successfully',
                'data' => $uploadedFiles
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error uploading media: ' . $e->getMessage());
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error uploading media: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to upload media', 'error' => $e->getMessage()], 500);
        }
    }

    protected function handleMediaUploads($files, Article $article)
    {
        if (!is_array($files)) {
            $files = [$files];
        }

        foreach ($files as $file) {
            try {
                if (!$file->isValid()) {
                    Log::error('Invalid file uploaded: ' . $file->getClientOriginalName());
                    continue;
                }

                $fileName = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('articles/' . $article->id, $fileName, 'public');
                
                if (!$path) {
                    throw new \Exception('Failed to store file: ' . $file->getClientOriginalName());
                }
                
                $article->media()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getClientOriginalExtension(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
                
            } catch (\Exception $e) {
                Log::error('Error uploading media: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                continue;
            }
        }
    }

    protected function postToFacebook(Article $article)
    {
        $pageAccessToken = env('FB_PAGE_ACCESS_TOKEN');
        $pageId = env('FB_PAGE_ID');
        
        if (!$pageAccessToken || !$pageId) {
            Log::warning('Facebook credentials not configured');
            return;
        }

        // Strip HTML tags from content and create a summary
        $plainContent = strip_tags($article->content);
        $summary = strlen($plainContent) > 300 
            ? substr($plainContent, 0, 297) . '...' 
            : $plainContent;
        
        // Create the Facebook post message
        $message = $article->title . "\n\n" . $summary;

        // Get all images associated with the article
        $allMedia = $article->media()->where('mime_type', 'like', 'image/%')->orderBy('created_at', 'asc')->get();
        
        if ($allMedia->isNotEmpty()) {
            // Post with multiple images - cover image first, then inline images
            $this->postMultipleImagesToFacebook($pageId, $pageAccessToken, $message, $allMedia, $article->id);
        } else {
            // Post text only
            $this->postTextToFacebook($pageId, $pageAccessToken, $message, $article->id);
        }
    }

    protected function postMultipleImagesToFacebook($pageId, $accessToken, $message, $mediaCollection, $articleId)
    {
        try {
            // Prepare multiple images for upload
            $attachedImages = [];
            $uploadData = [
                'message' => $message,
                'access_token' => $accessToken,
            ];
            
            // Attach all images (cover image first, then inline images in order)
            foreach ($mediaCollection as $index => $media) {
                $imagePath = Storage::disk('public')->path($media->file_path);
                
                if (file_exists($imagePath)) {
                    $imageData = file_get_contents($imagePath);
                    $imageName = basename($imagePath);
                    
                    // Facebook API expects 'source[0]', 'source[1]', etc. for multiple photos
                    $uploadData["source[$index]"] = $imageData;
                    $attachedImages[] = $imageName;
                    
                    Log::info("Attached image for Facebook upload: {$imageName}", ['article_id' => $articleId]);
                } else {
                    Log::warning("Image file not found: {$media->file_path}", ['article_id' => $articleId]);
                }
            }
            
            if (!empty($attachedImages)) {
                // Post to Facebook with multiple photos
                $response = Http::post("https://graph.facebook.com/{$pageId}/photos", $uploadData);
                
                if ($response->successful()) {
                    Log::info('Article posted to Facebook with multiple images', [
                        'article_id' => $articleId,
                        'images_count' => count($attachedImages),
                        'images' => $attachedImages
                    ]);
                } else {
                    Log::error('Failed to post to Facebook with multiple images', [
                        'article_id' => $articleId,
                        'response' => $response->body()
                    ]);
                    // Fallback to text-only post
                    $this->postTextToFacebook($pageId, $accessToken, $message, $articleId);
                }
            } else {
                // No valid images found, fallback to text-only
                $this->postTextToFacebook($pageId, $accessToken, $message, $articleId);
            }
            
        } catch (\Exception $e) {
            Log::error('Error posting multiple images to Facebook', [
                'article_id' => $articleId,
                'error' => $e->getMessage()
            ]);
            // Fallback to text-only post
            $this->postTextToFacebook($pageId, $accessToken, $message, $articleId);
        }
    }

    protected function postTextToFacebook($pageId, $accessToken, $message, $articleId)
    {
        Http::post("https://graph.facebook.com/{$pageId}/feed", [
            'message' => $message,
            'access_token' => $accessToken,
        ]);
        
        Log::info('Article posted to Facebook (text only)', ['article_id' => $articleId]);
    }

    public function getDashboardStats(Request $request)
    {
        $user = $request->user();
        
        // Get user's group chats
        $userGroupChats = $user->groupChats()->with('scrumBoard', 'folio')->get();
        
        Log::info('Dashboard stats for user', [
            'user_id' => $user->id,
            'total_group_chats' => $userGroupChats->count(),
            'group_chats' => $userGroupChats->pluck('id')->toArray()
        ]);
        
        // Initialize counters
        $pendingTasks = 0;
        $inReview = 0;
        $approved = 0;
        
        foreach ($userGroupChats as $groupChat) {
            // Use the track column for simple status determination
            $track = $groupChat->track ?? 'pending';
            
            Log::info('Processing group chat', [
                'group_chat_id' => $groupChat->id,
                'track' => $track
            ]);
            
            if ($track === 'approved') {
                $approved++;
            } elseif ($track === 'review') {
                $inReview++;
            } else {
                // 'pending' or null
                $pendingTasks++;
            }
        }
        
        // Active Projects: Total group chats user is part of
        $activeProjects = $userGroupChats->count();
        
        Log::info('Final dashboard stats', [
            'user_id' => $user->id,
            'pending_tasks' => $pendingTasks,
            'in_review' => $inReview,
            'approved' => $approved,
            'active_projects' => $activeProjects
        ]);
        
        // Get upcoming activities for the user
        $upcomingActivities = \App\Models\Activity::where('date', '>=', now())
            ->whereHas('members', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with(['creator'])
            ->orderBy('date', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'title' => $activity->title,
                    'date' => $activity->date->format('M d, Y'),
                    'time' => $activity->date->format('h:i A'),
                    'location' => $activity->location,
                    'creator' => $activity->creator->name,
                ];
            });

        // Get top contributors (most involved in group chats) for last 30 days
        Log::info('Fetching top contributors...');
        
        try {
            // Let's check what group chats exist and their dates
            $allGroupChats = \App\Models\GroupChat::all();
            Log::info('All group chats in database', [
                'total' => $allGroupChats->count(),
                'chats' => $allGroupChats->map(function($chat) {
                    return [
                        'id' => $chat->id,
                        'name' => $chat->name,
                        'created_at' => $chat->created_at->toDateTimeString(),
                        'updated_at' => $chat->updated_at->toDateTimeString(),
                    ];
                })->toArray()
            ]);
            
            // Check all users with group chats (no date filter first)
            $allUsersWithChats = \App\Models\User::whereHas('groupChats')
                ->with(['profile'])
                ->withCount(['groupChats'])
                ->orderBy('group_chats_count', 'desc')
                ->limit(5)
                ->get();
            
            Log::info('Top 5 users by total group chats', [
                'count' => $allUsersWithChats->count(),
                'users' => $allUsersWithChats->map(function($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->profile ? $user->profile->name : $user->name,
                        'email' => $user->email,
                        'group_chats_count' => $user->group_chats_count,
                    ];
                })->toArray()
            ]);
            
            // Process top contributors - use track column for simple status
            $topContributors = $allUsersWithChats->map(function ($user) {
                Log::info('Processing user for stats', ['user_id' => $user->id, 'name' => $user->profile ? $user->profile->name : $user->name]);
                
                // Get ALL user's group chats (no date filter for dashboard)
                $userGroupChats = $user->groupChats()->get();
                
                Log::info('User group chats (all)', [
                    'user_id' => $user->id,
                    'total_chats' => $userGroupChats->count(),
                    'chat_ids' => $userGroupChats->pluck('id')->toArray()
                ]);
                
                $approvedCount = 0;
                $pendingCount = 0;
                $inReviewCount = 0;
                
                // Use track column for status determination
                foreach ($userGroupChats as $groupChat) {
                    $track = $groupChat->track ?? 'pending';
                    
                    if ($track === 'approved') {
                        $approvedCount++;
                    } elseif ($track === 'review') {
                        $inReviewCount++;
                    } else {
                        $pendingCount++;
                    }
                }
                
                $result = [
                    'id' => $user->id,
                    'name' => $user->profile ? $user->profile->name : $user->name,
                    'email' => $user->email,
                    'total_assigned' => $userGroupChats->count(),
                    'approved' => $approvedCount,
                    'pending' => $pendingCount,
                    'in_review' => $inReviewCount,
                    'avatar' => $user->profile ? $user->profile->avatar : null,
                ];
                
                Log::info('Final user stats', $result);
                
                return $result;
            });
            
            Log::info('Final contributors list', [
                'count' => $topContributors->count(),
                'contributors' => $topContributors->toArray()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching top contributors', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $topContributors = collect([]);
        }

        return response()->json([
            'pending_tasks' => $pendingTasks,
            'in_review' => $inReview,
            'approved' => $approved,
            'active_projects' => $activeProjects,
            'upcoming_activities' => $upcomingActivities,
            'top_contributors' => $topContributors,
        ]);
    }

    public function getAllContributors(Request $request)
    {
        try {
            $search = $request->input('search', '');
            $timeframe = $request->input('timeframe', '30'); // days
            $role = $request->input('role', 'all');
            
            Log::info('Fetching all contributors', [
                'search' => $search,
                'timeframe' => $timeframe,
                'role' => $role
            ]);
            
            // Get all users with group chats first (no date filtering in query)
            $query = \App\Models\User::whereHas('groupChats')
                ->with(['profile'])
                ->withCount(['groupChats']);
            
            // We'll filter by date during processing to avoid SQL errors
            $cutoffDate = now()->subDays((int)$timeframe);
            
            Log::info('Setting up timeframe filtering', [
                'timeframe' => $timeframe,
                'cutoff_date' => $cutoffDate->toDateTimeString()
            ]);
            
            // Apply search filter
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }
            
            // Apply role filter
            if ($role !== 'all') {
                $query->whereHas('profile', function($profileQuery) use ($role) {
                    $profileQuery->where('role', $role);
                });
            }
            
            $contributors = $query->orderBy('group_chats_count', 'desc')
                ->paginate(20);
            
            Log::info('Initial query results', [
                'total' => $contributors->total(),
                'count' => $contributors->count(),
                'users' => $contributors->getCollection()->map(function($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->profile ? $user->profile->name : $user->name,
                        'group_chats_count' => $user->group_chats_count,
                    ];
                })->toArray()
            ]);
            
            // Process each contributor to add detailed stats
            $processedContributors = $contributors->getCollection()->map(function ($user) use ($timeframe, $cutoffDate) {
                try {
                    Log::info('Processing user', [
                        'user_id' => $user->id,
                        'user_name' => $user->profile ? $user->profile->name : $user->name,
                        'group_chats_count_from_query' => $user->group_chats_count
                    ]);
                    
                    // Use direct DB query to count group chats - more reliable
                    $totalAssigned = \DB::table('group_chat_user')
                        ->where('user_id', $user->id)
                        ->count();
                    
                    // Get group chat IDs for this user
                    $groupChatIds = \DB::table('group_chat_user')
                        ->where('user_id', $user->id)
                        ->pluck('group_chat_id')
                        ->toArray();
                    
                    // Get group chats within timeframe
                    $filteredGroupChatIds = \DB::table('group_chats')
                        ->whereIn('id', $groupChatIds)
                        ->where('updated_at', '>=', $cutoffDate)
                        ->pluck('id')
                        ->toArray();
                    
                    Log::info('User group chat counts (DB query)', [
                        'user_id' => $user->id,
                        'user_name' => $user->profile ? $user->profile->name : $user->name,
                        'all_chats' => $totalAssigned,
                        'filtered_chats' => count($filteredGroupChatIds),
                        'timeframe' => $timeframe,
                        'group_chat_ids' => $groupChatIds,
                        'filtered_ids' => $filteredGroupChatIds
                    ]);
                    
                    // Get the actual group chat models for status calculation
                    $filteredGroupChats = \App\Models\GroupChat::whereIn('id', $filteredGroupChatIds)
                        ->with('folio')
                        ->get();
                    
                    // Calculate status counts from filtered group chats
                    $approvedCount = 0;
                    $pendingCount = 0;
                    $inReviewCount = 0;
                    
                    Log::info('User group chats retrieved', [
                        'user_id' => $user->id,
                        'count' => $filteredGroupChats->count(),
                        'chat_ids' => $filteredGroupChats->pluck('id')->toArray()
                    ]);
                    
                    foreach ($filteredGroupChats as $groupChat) {
                        // Use the new track column for simple status determination
                        $track = $groupChat->track ?? 'pending';
                        
                        Log::info('Processing group chat for user', [
                            'user_id' => $user->id,
                            'group_chat_id' => $groupChat->id,
                            'track' => $track
                        ]);
                        
                        if ($track === 'approved') {
                            $approvedCount++;
                        } elseif ($track === 'review') {
                            $inReviewCount++;
                        } else {
                            $pendingCount++;
                        }
                    }
                    
                    $result = [
                        'id' => $user->id,
                        'name' => $user->profile ? $user->profile->name : $user->name,
                        'email' => $user->email,
                        'total_assigned' => $totalAssigned, // Use the count from initial query
                        'approved' => $approvedCount,
                        'pending' => $pendingCount,
                        'in_review' => $inReviewCount,
                        'avatar' => $user->profile ? $user->profile->avatar : null,
                    ];
                    
                    Log::info('User result', $result);
                    
                    return $result;
                    
                } catch (\Exception $e) {
                    Log::error('Error processing user stats', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                    
                    // Return safe fallback data
                    return [
                        'id' => $user->id,
                        'name' => $user->profile ? $user->profile->name : $user->name,
                        'email' => $user->email,
                        'total_assigned' => 0,
                        'approved' => 0,
                        'pending' => 0,
                        'in_review' => 0,
                        'avatar' => $user->profile ? $user->profile->avatar : null,
                    ];
                }
            });
            
            // Replace the collection with processed data
            $contributors->setCollection($processedContributors);
            
            Log::info('All contributors fetched successfully', [
                'timeframe' => $timeframe,
                'total' => $contributors->total(),
                'count' => $contributors->count()
            ]);
            
            return response()->json($contributors);
            
        } catch (\Exception $e) {
            Log::error('Error in getAllContributors', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to fetch contributors',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}