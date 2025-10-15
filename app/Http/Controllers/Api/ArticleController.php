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

        // Check if article has media
        $firstMedia = $article->media()->first();
        
        if ($firstMedia && in_array($firstMedia->mime_type, ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'])) {
            // Post with image
            $imagePath = Storage::disk('public')->path($firstMedia->file_path);
            
            if (file_exists($imagePath)) {
                Http::attach('source', file_get_contents($imagePath), basename($imagePath))
                    ->post("https://graph.facebook.com/{$pageId}/photos", [
                        'message' => $message,
                        'access_token' => $pageAccessToken,
                    ]);
                
                Log::info('Article posted to Facebook with image', ['article_id' => $article->id]);
            } else {
                // Fallback to text-only if image file not found
                $this->postTextToFacebook($pageId, $pageAccessToken, $message, $article->id);
            }
        } else {
            // Post text only
            $this->postTextToFacebook($pageId, $pageAccessToken, $message, $article->id);
        }
    }

    protected function postTextToFacebook($pageId, $accessToken, $message, $articleId)
    {
        Http::post("https://graph.facebook.com/{$pageId}/feed", [
            'message' => $message,
            'access_token' => $accessToken,
        ]);
        
        Log::info('Article posted to Facebook as text', ['article_id' => $articleId]);
    }
}