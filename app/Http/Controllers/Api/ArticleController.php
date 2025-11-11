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
                'existing_media' => 'nullable|array',
                'existing_media.*' => 'string',
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

            // Log media upload attempt
            Log::info('Checking for media files', [
                'has_media' => $request->hasFile('media'),
                'all_files' => $request->allFiles(),
                'media_input' => $request->input('media'),
            ]);

            if ($request->hasFile('media')) {
                Log::info('Media files found, processing uploads');
                $this->handleMediaUploads($request->file('media'), $article);
            } else {
                Log::warning('No media files in request');
            }

            // Handle existing media from Browse Works
            if ($request->has('existing_media')) {
                Log::info('Existing media paths found', [
                    'paths' => $request->input('existing_media')
                ]);
                $this->handleExistingMedia($request->input('existing_media'), $article);
            }

            // Post to Facebook if requested
            if ($request->input('post_to_facebook', false)) {
                try {
                    // Reload article with media to ensure relationship is loaded
                    $article->refresh();
                    $article->load('media');
                    
                    Log::info('About to post to Facebook', [
                        'article_id' => $article->id,
                        'media_loaded' => $article->relationLoaded('media'),
                        'media_count' => $article->media->count()
                    ]);
                    
                    $this->postToFacebook($article);
                } catch (\Exception $e) {
                    Log::error('Failed to post article to Facebook: ' . $e->getMessage(), [
                        'trace' => $e->getTraceAsString()
                    ]);
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

    protected function handleExistingMedia($paths, Article $article)
    {
        if (!is_array($paths)) {
            $paths = [$paths];
        }

        Log::info('handleExistingMedia called', [
            'article_id' => $article->id,
            'path_count' => count($paths)
        ]);

        foreach ($paths as $index => $storagePath) {
            try {
                Log::info("Processing existing media {$index}", [
                    'storage_path' => $storagePath
                ]);

                // Check if file exists in storage
                if (!Storage::disk('public')->exists($storagePath)) {
                    Log::error('Existing media file not found', ['path' => $storagePath]);
                    continue;
                }

                // Copy the file to the article's media directory
                $fileName = basename($storagePath);
                $newPath = 'articles/' . $article->id . '/' . time() . '_' . $fileName;
                
                Storage::disk('public')->copy($storagePath, $newPath);
                
                Log::info('File copied', [
                    'from' => $storagePath,
                    'to' => $newPath
                ]);

                // Get file info
                $fullPath = Storage::disk('public')->path($newPath);
                $mimeType = Storage::disk('public')->mimeType($newPath);
                $size = Storage::disk('public')->size($newPath);
                $extension = pathinfo($fileName, PATHINFO_EXTENSION);

                // Create media record
                $media = $article->media()->create([
                    'file_path' => $newPath,
                    'file_name' => $fileName,
                    'file_type' => $extension,
                    'mime_type' => $mimeType,
                    'size' => $size,
                ]);

                Log::info('Media record created from existing file', [
                    'media_id' => $media->id,
                    'file_path' => $newPath
                ]);

            } catch (\Exception $e) {
                Log::error('Error handling existing media: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
                continue;
            }
        }

        Log::info('handleExistingMedia completed', [
            'article_id' => $article->id,
            'total_media_count' => $article->media()->count()
        ]);
    }

    protected function handleMediaUploads($files, Article $article)
    {
        if (!is_array($files)) {
            $files = [$files];
        }

        Log::info('handleMediaUploads called', [
            'article_id' => $article->id,
            'file_count' => count($files)
        ]);

        foreach ($files as $index => $file) {
            try {
                Log::info("Processing file {$index}", [
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'is_valid' => $file->isValid()
                ]);

                if (!$file->isValid()) {
                    Log::error('Invalid file uploaded: ' . $file->getClientOriginalName());
                    continue;
                }

                $fileName = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('articles/' . $article->id, $fileName, 'public');
                
                Log::info('File stored', [
                    'path' => $path,
                    'file_name' => $fileName
                ]);
                
                if (!$path) {
                    throw new \Exception('Failed to store file: ' . $file->getClientOriginalName());
                }
                
                $media = $article->media()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getClientOriginalExtension(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
                
                Log::info('Media record created', [
                    'media_id' => $media->id,
                    'file_path' => $path
                ]);
                
            } catch (\Exception $e) {
                Log::error('Error uploading media: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                continue;
            }
        }
        
        Log::info('handleMediaUploads completed', [
            'article_id' => $article->id,
            'total_media_count' => $article->media()->count()
        ]);
    }

    protected function postToFacebook(Article $article)
    {
        $pageAccessToken = env('FB_PAGE_ACCESS_TOKEN');
        $pageId = env('FB_PAGE_ID');
        
        if (!$pageAccessToken || !$pageId) {
            Log::warning('Facebook credentials not configured');
            return;
        }

        // Convert HTML to plain text while preserving line breaks
        $content = $article->content;
        
        // Convert common HTML line breaks to newlines
        $content = preg_replace('/<br\s*\/?>/i', "\n", $content);
        $content = preg_replace('/<\/p>/i', "\n\n", $content);
        $content = preg_replace('/<p[^>]*>/i', '', $content);
        $content = preg_replace('/<\/h[1-6]>/i', "\n\n", $content);
        $content = preg_replace('/<h[1-6][^>]*>/i', '', $content);
        $content = preg_replace('/<\/li>/i', "\n", $content);
        $content = preg_replace('/<li[^>]*>/i', '• ', $content);
        
        // Strip remaining HTML tags
        $plainContent = strip_tags($content);
        
        // Clean up excessive newlines (more than 2 in a row)
        $plainContent = preg_replace("/\n{3,}/", "\n\n", $plainContent);
        
        // Trim whitespace
        $plainContent = trim($plainContent);
        
        // Facebook allows up to 63,206 characters for posts
        // We'll use a reasonable limit of 5000 characters to keep posts readable
        $maxLength = 5000;
        $finalContent = strlen($plainContent) > $maxLength 
            ? substr($plainContent, 0, $maxLength - 3) . '...' 
            : $plainContent;
        
        // Create the Facebook post message
        $message = $article->title . "\n\n" . $finalContent;

        // Check if article has media
        $firstMedia = $article->media->first();
        
        // Log all media details for debugging
        $mediaDetails = $article->media->map(function($media) {
            return [
                'id' => $media->id,
                'file_path' => $media->file_path,
                'mime_type' => $media->mime_type,
                'file_name' => $media->file_name,
            ];
        })->toArray();
        
        Log::info('Attempting Facebook post', [
            'article_id' => $article->id,
            'has_media' => $firstMedia ? 'yes' : 'no',
            'media_count' => $article->media->count(),
            'all_media' => $mediaDetails
        ]);
        
        if ($firstMedia && in_array($firstMedia->mime_type, ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'])) {
            // Post with image
            $imagePath = Storage::disk('public')->path($firstMedia->file_path);
            
            Log::info('Attempting to post with image', [
                'image_path' => $imagePath,
                'file_exists' => file_exists($imagePath),
                'mime_type' => $firstMedia->mime_type
            ]);
            
            if (file_exists($imagePath)) {
                // Try posting with image URL first (more reliable than file upload)
                $imageUrl = Storage::disk('public')->url($firstMedia->file_path);
                
                // Make sure we have a full URL
                if (!str_starts_with($imageUrl, 'http')) {
                    $imageUrl = url($imageUrl);
                }
                
                Log::info('Posting to Facebook with image URL', [
                    'image_url' => $imageUrl,
                    'image_path' => $imagePath
                ]);
                
                // Try URL method first
                $response = Http::post("https://graph.facebook.com/{$pageId}/photos", [
                    'url' => $imageUrl,
                    'message' => $message,
                    'access_token' => $pageAccessToken,
                ]);
                
                // If URL method fails, try file upload method
                if (!$response->successful()) {
                    Log::warning('URL method failed, trying file upload', [
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                    
                    $response = Http::attach('source', file_get_contents($imagePath), basename($imagePath))
                        ->post("https://graph.facebook.com/{$pageId}/photos", [
                            'message' => $message,
                            'access_token' => $pageAccessToken,
                        ]);
                }
                
                if ($response->successful()) {
                    Log::info('Article posted to Facebook with image', [
                        'article_id' => $article->id,
                        'response' => $response->json()
                    ]);
                } else {
                    Log::error('Facebook API error when posting with image', [
                        'article_id' => $article->id,
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                }
            } else {
                Log::warning('Image file not found, posting text only', ['path' => $imagePath]);
                // Fallback to text-only if image file not found
                $this->postTextToFacebook($pageId, $pageAccessToken, $message, $article->id);
            }
        } else {
            Log::info('No suitable image found, posting text only', [
                'article_id' => $article->id,
                'first_media_type' => $firstMedia ? $firstMedia->mime_type : 'none'
            ]);
            // Post text only
            $this->postTextToFacebook($pageId, $pageAccessToken, $message, $article->id);
        }
    }

    protected function postTextToFacebook($pageId, $accessToken, $message, $articleId)
    {
        $response = Http::post("https://graph.facebook.com/{$pageId}/feed", [
            'message' => $message,
            'access_token' => $accessToken,
        ]);
        
        if ($response->successful()) {
            Log::info('Article posted to Facebook as text', [
                'article_id' => $articleId,
                'response' => $response->json()
            ]);
        } else {
            Log::error('Facebook API error when posting text', [
                'article_id' => $articleId,
                'status' => $response->status(),
                'response' => $response->body()
            ]);
        }
    }
}