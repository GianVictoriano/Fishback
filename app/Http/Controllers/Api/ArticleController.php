<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\ArticleSummaryResource;
use App\Models\Article;
use App\Models\Featured;
use App\Services\RecommendationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $articles = Article::with(['media', 'user'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return ArticleResource::collection($articles);
    }

    public function publicArticleSummaries(Request $request)
    {
        $query = Article::with(['media:id,article_id,file_path'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('status', '!=', 'archived');

        if ($request->has('genre')) {
            $query->where('genre', $request->input('genre'));
        }

        // Limit results to reduce payload; front-end paginates client side.
        $limit = $request->input('limit', 40);
        $articles = $query->orderBy('published_at', 'desc')->take($limit)->get();

        return ArticleSummaryResource::collection($articles);
    }

    public function publicArticles(Request $request)
    {
        $query = Article::with(['media', 'user'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('status', '!=', 'archived'); // Exclude archived articles

        if ($request->has('genre')) {
            $query->where('genre', $request->input('genre'));
        }

        // Handle sort parameter
        $sort = $request->input('sort', 'published_at:desc');
        if ($sort) {
            [$column, $direction] = explode(':', $sort) + [null, 'desc'];
            if (in_array($column, ['published_at', 'created_at', 'title']) && in_array($direction, ['asc', 'desc'])) {
                $query->orderBy($column, $direction);
            } else {
                $query->orderBy('published_at', 'desc');
            }
        }

        $articles = $query->paginate(15);

        return ArticleResource::collection($articles);
    }

    public function debugFacebook()
    {
        try {
            $pageAccessToken = env('FB_PAGE_ACCESS_TOKEN');
            $pageId = env('FB_PAGE_ID');
            
            // Test Facebook API connection
            $testResponse = Http::get("https://graph.facebook.com/{$pageId}?access_token={$pageAccessToken}");
            
            $debug = [
                'fb_page_access_token_set' => !empty($pageAccessToken),
                'fb_page_id' => $pageId,
                'fb_api_test_status' => $testResponse->status(),
                'fb_api_test_successful' => $testResponse->successful(),
                'fb_api_test_response' => $testResponse->json()
            ];
            
            // Test with a simple text post
            $testMessage = "Test post from Fisherman app at " . now()->format('Y-m-d H:i:s');
            $textPostResponse = Http::post("https://graph.facebook.com/{$pageId}/feed", [
                'message' => $testMessage,
                'access_token' => $pageAccessToken,
            ]);
            
            $debug['text_post_test'] = [
                'status' => $textPostResponse->status(),
                'successful' => $textPostResponse->successful(),
                'response' => $textPostResponse->json()
            ];
            
            return response()->json($debug);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'genre' => 'required|in:articles,opinions,sports,editorial,artworks,creative',
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
        $limit = 25; // Updated to match frontend expectations
        $genre = $request->query('genre');

        // First, get trending articles from the last 3 days
        $trendingQuery = Article::with(['media', 'user', 'metrics'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('status', '!=', 'archived') // Exclude archived articles
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
                ->where('status', '!=', 'archived') // Exclude archived articles
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

    protected function handleExistingMedia($mediaPaths, Article $article)
    {
        if (!is_array($mediaPaths)) {
            $mediaPaths = [$mediaPaths];
        }

        Log::info('handleExistingMedia called', [
            'article_id' => $article->id,
            'media_paths' => $mediaPaths
        ]);

        foreach ($mediaPaths as $index => $mediaPath) {
            try {
                Log::info("Processing existing media {$index}", [
                    'media_path' => $mediaPath
                ]);

                // Check if the file exists in storage
                $fullPath = Storage::disk('public')->path($mediaPath);
                if (!Storage::disk('public')->exists($mediaPath)) {
                    Log::warning("Existing media file not found: {$mediaPath}");
                    continue;
                }

                // Get file info
                $fileInfo = pathinfo($mediaPath);
                $fileName = $fileInfo['basename'];
                $fileExt = strtolower($fileInfo['extension'] ?? '');
                
                // Determine MIME type
                $mimeType = $this->getMimeTypeFromExtension($fileExt);
                
                // Get file size
                $size = Storage::disk('public')->size($mediaPath);

                // Create media record
                $media = $article->media()->create([
                    'file_path' => $mediaPath,
                    'file_name' => $fileName,
                    'file_type' => $fileExt,
                    'mime_type' => $mimeType,
                    'size' => $size,
                ]);
                
                Log::info('Existing media record created', [
                    'media_id' => $media->id,
                    'file_path' => $mediaPath
                ]);
                
            } catch (\Exception $e) {
                Log::error('Error handling existing media: ' . $e->getMessage(), [
                    'media_path' => $mediaPath,
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

    protected function getMimeTypeFromExtension($extension)
    {
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        
        return $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
    }

    protected function postToFacebook(Article $article)
    {
        $pageAccessToken = env('FB_PAGE_ACCESS_TOKEN');
        $pageId = env('FB_PAGE_ID');
        
        Log::info('Starting Facebook post process', [
            'article_id' => $article->id,
            'has_page_token' => !empty($pageAccessToken),
            'has_page_id' => !empty($pageId),
            'page_id' => $pageId
        ]);
        
        if (!$pageAccessToken || !$pageId) {
            Log::warning('Facebook credentials not configured', [
                'article_id' => $article->id,
                'fb_page_access_token_set' => !empty($pageAccessToken),
                'fb_page_id_set' => !empty($pageId)
            ]);
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

        Log::info('Facebook message prepared', [
            'article_id' => $article->id,
            'title_length' => strlen($article->title),
            'content_length' => strlen($finalContent),
            'message_length' => strlen($message)
        ]);

        // Get all images associated with the article
        $allMedia = $article->media()->where('mime_type', 'like', 'image/%')->orderBy('created_at', 'asc')->get();
        
        Log::info('Retrieved article media for Facebook', [
            'article_id' => $article->id,
            'total_media_count' => $article->media()->count(),
            'image_media_count' => $allMedia->count(),
            'media_items' => $allMedia->map(function($media) {
                return [
                    'id' => $media->id,
                    'file_path' => $media->file_path,
                    'mime_type' => $media->mime_type,
                    'size' => $media->size
                ];
            })->toArray()
        ]);
        
        if ($allMedia->isNotEmpty()) {
            // Post with multiple images - cover image first, then inline images
            Log::info('Attempting to post with images to Facebook', [
                'article_id' => $article->id,
                'image_count' => $allMedia->count()
            ]);
            $this->postMultipleImagesToFacebook($pageId, $pageAccessToken, $message, $allMedia, $article->id);
        } else {
            Log::info('No suitable image found, posting text only', [
                'article_id' => $article->id,
                'media_count' => $allMedia->count()
            ]);
            // Post text only
            $this->postTextToFacebook($pageId, $pageAccessToken, $message, $article->id);
        }
    }

    protected function postMultipleImagesToFacebook($pageId, $accessToken, $message, $mediaCollection, $articleId)
    {
        try {
            Log::info('Attempting to post multiple images to Facebook', [
                'article_id' => $articleId,
                'total_media_count' => $mediaCollection->count(),
                'image_media_count' => $mediaCollection->where('mime_type', 'like', 'image/%')->count()
            ]);

            // If we have only one image, use the single photo endpoint
            if ($mediaCollection->count() === 1) {
                return $this->postSingleImageToFacebook($pageId, $accessToken, $message, $mediaCollection->first(), $articleId);
            }

            // For multiple images, we need to first upload them to an unpublished post
            $attachedImages = [];
            $uploadData = [
                'message' => $message,
                'access_token' => $accessToken,
                'published' => false, // Create unpublished post first
            ];
            
            // Attach all images
            foreach ($mediaCollection as $index => $media) {
                $imagePath = Storage::disk('public')->path($media->file_path);
                
                Log::info("Checking image file", [
                    'article_id' => $articleId,
                    'file_path' => $media->file_path,
                    'full_path' => $imagePath,
                    'file_exists' => file_exists($imagePath)
                ]);
                
                if (file_exists($imagePath)) {
                    $imageName = basename($imagePath);
                    
                    // Create a temporary file with proper extension
                    $tempFile = tempnam(sys_get_temp_dir(), 'fb_image_' . $index . '_');
                    $extension = pathinfo($imagePath, PATHINFO_EXTENSION) ?: 'jpg';
                    $tempFileWithExt = $tempFile . '.' . $extension;
                    rename($tempFile, $tempFileWithExt);
                    
                    // Copy image to temp file
                    copy($imagePath, $tempFileWithExt);
                    
                    // Facebook API expects 'source[0]', 'source[1]', etc. for multiple photos
                    $uploadData["source[$index]"] = fopen($tempFileWithExt, 'r');
                    $attachedImages[] = [
                        'name' => $imageName,
                        'temp_file' => $tempFileWithExt
                    ];
                    
                    Log::info("Attached image for Facebook upload: {$imageName}", [
                        'article_id' => $articleId,
                        'temp_file' => $tempFileWithExt,
                        'file_size' => filesize($tempFileWithExt)
                    ]);
                } else {
                    Log::warning("Image file not found: {$media->file_path}", [
                        'article_id' => $articleId,
                        'expected_path' => $imagePath
                    ]);
                }
            }
            
            if (!empty($attachedImages)) {
                Log::info('Sending request to Facebook API', [
                    'article_id' => $articleId,
                    'endpoint' => "https://graph.facebook.com/{$pageId}/photos",
                    'images_count' => count($attachedImages),
                    'data_keys' => array_keys($uploadData)
                ]);

                // Post to Facebook with multiple photos
                $response = Http::asMultipart()->post("https://graph.facebook.com/{$pageId}/photos", $uploadData);
                
                // Clean up temp files
                foreach ($attachedImages as $index => $imageInfo) {
                    if (isset($uploadData["source[$index]"])) {
                        fclose($uploadData["source[$index]"]);
                    }
                    if (isset($imageInfo['temp_file']) && file_exists($imageInfo['temp_file'])) {
                        unlink($imageInfo['temp_file']);
                    }
                }
                
                Log::info('Facebook API response received', [
                    'article_id' => $articleId,
                    'status' => $response->status(),
                    'successful' => $response->successful(),
                    'response_body' => $response->body()
                ]);
                
                if ($response->successful()) {
                    Log::info('Article posted to Facebook with multiple images', [
                        'article_id' => $articleId,
                        'images_count' => count($attachedImages),
                        'images' => array_column($attachedImages, 'name')
                    ]);
                } else {
                    Log::error('Failed to post to Facebook with multiple images', [
                        'article_id' => $articleId,
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                    
                    // Try fallback: post first image only
                    if ($mediaCollection->isNotEmpty()) {
                        Log::info('Trying fallback: posting first image only', ['article_id' => $articleId]);
                        $this->postSingleImageToFacebook($pageId, $accessToken, $message, $mediaCollection->first(), $articleId);
                    } else {
                        // Final fallback to text-only post
                        $this->postTextToFacebook($pageId, $accessToken, $message, $articleId);
                    }
                }
            } else {
                Log::warning('No valid images found for Facebook post', ['article_id' => $articleId]);
                // Fallback to text-only
                $this->postTextToFacebook($pageId, $accessToken, $message, $articleId);
            }
            
        } catch (\Exception $e) {
            Log::error('Error posting multiple images to Facebook', [
                'article_id' => $articleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Fallback to text-only post
            $this->postTextToFacebook($pageId, $accessToken, $message, $articleId);
        }
    }

    protected function postSingleImageToFacebook($pageId, $accessToken, $message, $media, $articleId)
    {
        try {
            $imagePath = Storage::disk('public')->path($media->file_path);
            
            Log::info("Posting single image to Facebook", [
                'article_id' => $articleId,
                'file_path' => $media->file_path,
                'full_path' => $imagePath,
                'file_exists' => file_exists($imagePath)
            ]);
            
            if (file_exists($imagePath)) {
                $imageName = basename($imagePath);
                
                // Create a temporary file with proper extension
                $tempFile = tempnam(sys_get_temp_dir(), 'fb_image_');
                $extension = pathinfo($imagePath, PATHINFO_EXTENSION) ?: 'jpg';
                $tempFileWithExt = $tempFile . '.' . $extension;
                rename($tempFile, $tempFileWithExt);
                
                // Copy image to temp file
                copy($imagePath, $tempFileWithExt);
                
                $uploadData = [
                    'message' => $message,
                    'source' => fopen($tempFileWithExt, 'r'),
                    'access_token' => $accessToken,
                ];
                
                Log::info('Sending single image to Facebook API', [
                    'article_id' => $articleId,
                    'image_name' => $imageName,
                    'temp_file' => $tempFileWithExt,
                    'file_size' => filesize($tempFileWithExt)
                ]);
                
                $response = Http::asMultipart()->post("https://graph.facebook.com/{$pageId}/photos", $uploadData);
                
                // Clean up temp file
                fclose($uploadData['source']);
                unlink($tempFileWithExt);
                
                Log::info('Facebook single image API response', [
                    'article_id' => $articleId,
                    'status' => $response->status(),
                    'successful' => $response->successful(),
                    'response_body' => $response->body()
                ]);
                
                if ($response->successful()) {
                    Log::info('Article posted to Facebook with single image', [
                        'article_id' => $articleId,
                        'image' => $imageName
                    ]);
                } else {
                    Log::error('Failed to post single image to Facebook', [
                        'article_id' => $articleId,
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                    // Fallback to text-only post
                    $this->postTextToFacebook($pageId, $accessToken, $message, $articleId);
                }
            } else {
                Log::warning("Single image file not found: {$media->file_path}", [
                    'article_id' => $articleId,
                    'expected_path' => $imagePath
                ]);
                // Fallback to text-only post
                $this->postTextToFacebook($pageId, $accessToken, $message, $articleId);
            }
            
        } catch (\Exception $e) {
            Log::error('Error posting single image to Facebook', [
                'article_id' => $articleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Fallback to text-only post
            $this->postTextToFacebook($pageId, $accessToken, $message, $articleId);
        }
    }

    protected function postTextToFacebook($pageId, $accessToken, $message, $articleId)
    {
        try {
            Log::info('Posting text-only to Facebook', ['article_id' => $articleId]);
            
            $response = Http::post("https://graph.facebook.com/{$pageId}/feed", [
                'message' => $message,
                'access_token' => $accessToken,
            ]);
            
            Log::info('Facebook text post response', [
                'article_id' => $articleId,
                'status' => $response->status(),
                'successful' => $response->successful(),
                'response' => $response->body()
            ]);
            
            if ($response->successful()) {
                Log::info('Article posted to Facebook as text-only', ['article_id' => $articleId]);
            } else {
                Log::error('Failed to post text to Facebook', [
                    'article_id' => $articleId,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error posting text to Facebook', [
                'article_id' => $articleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function getActivityData(Request $request)
    {
        try {
            $limit = $request->input('limit', 50);
            $filter = $request->input('filter', 'all');
            $search = $request->input('search', '');

            $activities = [];

            // 1. Article creation/publishing activities
            $articleQuery = \App\Models\Article::with(['user', 'user.profile'])
                ->where('status', 'published')
                ->whereNotNull('published_at')
                ->orderBy('published_at', 'desc')
                ->limit(20);

            if ($filter === 'publishing' || $filter === 'all') {
                $articles = $articleQuery->get();
                foreach ($articles as $article) {
                    $activities[] = [
                        'id' => 'article_' . $article->id,
                        'user_name' => $article->user->profile->name ?? $article->user->name ?? 'Unknown User',
                        'type' => 'create',
                        'description' => 'published article "' . $article->title . '"',
                        'timestamp' => $this->getTimeAgo($article->published_at),
                        'details' => 'Article ID: ' . $article->id,
                        'created_at' => $article->published_at
                    ];
                }
            }

            // 2. Article reactions (likes, hearts, etc.)
            if ($filter === 'user' || $filter === 'all') {
                $reactions = \App\Models\ArticleReaction::with(['user', 'user.profile', 'article'])
                    ->orderBy('created_at', 'desc')
                    ->limit(15)
                    ->get();

                foreach ($reactions as $reaction) {
                    if ($reaction->user) {
                        $activities[] = [
                            'id' => 'reaction_' . $reaction->id,
                            'user_name' => $reaction->user->profile->name ?? $reaction->user->name ?? 'Unknown User',
                            'type' => 'comment',
                            'description' => 'reacted with ' . $reaction->reaction_type . ' to "' . ($reaction->article->title ?? 'an article') . '"',
                            'timestamp' => $this->getTimeAgo($reaction->created_at),
                            'details' => null,
                            'created_at' => $reaction->created_at
                        ];
                    }
                }
            }

            // 3. Chat messages
            if ($filter === 'user' || $filter === 'all') {
                $chatMessages = \App\Models\ChatMessage::with(['user', 'user.profile', 'groupChat'])
                    ->orderBy('created_at', 'desc')
                    ->limit(15)
                    ->get();

                foreach ($chatMessages as $message) {
                    if ($message->user) {
                        $activities[] = [
                            'id' => 'chat_' . $message->id,
                            'user_name' => $message->user->profile->name ?? $message->user->name ?? 'Unknown User',
                            'type' => 'comment',
                            'description' => 'sent a message in "' . ($message->groupChat->name ?? 'a group chat') . '"',
                            'timestamp' => $this->getTimeAgo($message->created_at),
                            'details' => null,
                            'created_at' => $message->created_at
                        ];
                    }
                }
            }

            // 4. Content reviews
            if ($filter === 'submission' || $filter === 'all') {
                $reviews = \App\Models\ReviewContent::with(['user', 'user.profile', 'group'])
                    ->orderBy('uploaded_at', 'desc')
                    ->limit(10)
                    ->get();

                foreach ($reviews as $review) {
                    $statusAction = $review->status === 'approved' ? 'approved' : ($review->status === 'rejected' ? 'rejected' : 'submitted');
                    $activities[] = [
                        'id' => 'review_' . $review->id,
                        'user_name' => $review->user->profile->name ?? $review->user->name ?? 'Unknown',
                        'type' => 'update',
                        'description' => 'submitted content in "' . ($review->group->name ?? 'a group') . '"',
                        'timestamp' => \Carbon\Carbon::parse($review->uploaded_at)->format('M d, Y H:i'),
                        'details' => null,
                        'created_at' => $review->uploaded_at
                    ];
                }
            }

            // 5. Applications
            if ($filter === 'user' || $filter === 'all') {
                $applications = \App\Models\Applicant::orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();

                foreach ($applications as $application) {
                    $activities[] = [
                        'id' => 'application_' . $application->id,
                        'user_name' => $application->full_name,
                        'type' => 'create',
                        'description' => 'submitted a collaborator application',
                        'timestamp' => $this->getTimeAgo($application->created_at),
                        'details' => 'Status: ' . ($application->status ?? 'pending'),
                        'created_at' => $application->created_at
                    ];
                }
            }

            // 6. Topic creation (Forum)
            if ($filter === 'publishing' || $filter === 'all') {
                $topics = \App\Models\Topic::with(['user', 'user.profile'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();

                foreach ($topics as $topic) {
                    $activities[] = [
                        'id' => 'topic_' . $topic->id,
                        'user_name' => $topic->user->profile->name ?? $topic->user->name ?? 'Unknown User',
                        'type' => 'create',
                        'description' => 'created forum topic "' . $topic->title . '"',
                        'timestamp' => $this->getTimeAgo($topic->created_at),
                        'details' => null,
                        'created_at' => $topic->created_at
                    ];
                }
            }

            // 7. Comments (Forum)
            if ($filter === 'user' || $filter === 'all') {
                $comments = \App\Models\Comment::with(['user', 'user.profile', 'topic'])
                    ->orderBy('created_at', 'desc')
                    ->limit(15)
                    ->get();

                foreach ($comments as $comment) {
                    $activities[] = [
                        'id' => 'comment_' . $comment->id,
                        'user_name' => $comment->user->profile->name ?? $comment->user->name ?? 'Unknown User',
                        'type' => 'comment',
                        'description' => 'commented on "' . ($comment->topic->title ?? 'a topic') . '"',
                        'timestamp' => $this->getTimeAgo($comment->created_at),
                        'details' => null,
                        'created_at' => $comment->created_at
                    ];
                }
            }

            // 8. Collaborator Tasks
            if ($filter === 'collaborator' || $filter === 'all') {
                $pendingGroupChats = \App\Models\GroupChat::with(['scrumBoard.leadReviewer'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();

                foreach ($pendingGroupChats as $chat) {
                    $deadline = $chat->scrumBoard ? $chat->scrumBoard->deadline : null;
                    $deadlineText = $deadline ? \Carbon\Carbon::parse($deadline)->format('M d, Y') : 'No deadline set';
                    $lead = $chat->scrumBoard ? $chat->scrumBoard->leadReviewer : null;
                    $leadName = $lead ? ($lead->profile->name ?? $lead->name ?? 'Unknown Lead') : 'No lead assigned';
                    $taskTitle = $chat->scrumBoard ? $chat->scrumBoard->title : $chat->name;
                    $status = $chat->track ?? 'pending';
                    $statusLabel = ucfirst($status); // Pending, Review, Approved
                    $activities[] = [
                        'id' => 'task_' . $chat->id,
                        'user_name' => $statusLabel . ': ' . $taskTitle,
                        'type' => $status, // Use status as type for color
                        'description' => ' - Lead Reviewer: ' . $leadName . ' - Deadline: ' . $deadlineText,
                        'timestamp' => 'Created at: ' . \Carbon\Carbon::parse($chat->created_at)->format('M d, Y H:i'),
                        'details' => 'Group Chat ID: ' . $chat->id,
                        'created_at' => $chat->created_at
                    ];
                }
            }

            // 9. System Events (Branding and Module History)
            if ($filter === 'system' || $filter === 'all') {
                // Branding history
                $brandingHistory = \DB::table('branding_history')
                    ->join('users', 'branding_history.user_id', '=', 'users.id')
                    ->select('branding_history.*', 'users.name as user_name')
                    ->orderBy('branding_history.created_at', 'desc')
                    ->limit(5)
                    ->get();

                foreach ($brandingHistory as $history) {
                    $activities[] = [
                        'id' => 'branding_' . $history->id,
                        'user_name' => $history->user_name,
                        'type' => 'update',
                        'description' => 'updated branding settings (' . $history->action . ')',
                        'timestamp' => $this->getTimeAgo($history->created_at),
                        'details' => 'Branding History ID: ' . $history->id,
                        'created_at' => $history->created_at
                    ];
                }

                // Profile module history
                $moduleHistory = \App\Models\ProfileModuleHistory::with(['profile.user', 'module'])
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get();

                foreach ($moduleHistory as $history) {
                    $profileName = $history->profile->user->name ?? 'Unknown';
                    $moduleName = $history->module->display_name ?? $history->module->name ?? 'Unknown Module';
                    $activities[] = [
                        'id' => 'module_' . $history->id,
                        'user_name' => $profileName,
                        'type' => 'create',
                        'description' => $history->action . ' module: ' . $moduleName,
                        'timestamp' => $this->getTimeAgo($history->created_at),
                        'details' => 'Module History ID: ' . $history->id,
                        'created_at' => $history->created_at
                    ];
                }
            }

            // Sort all activities by timestamp (most recent first)
            usort($activities, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });

            // Apply search filter
            if (!empty($search)) {
                $activities = array_filter($activities, function($activity) use ($search) {
                    return stripos($activity['user_name'], $search) !== false ||
                           stripos($activity['description'], $search) !== false;
                });
            }

            // Limit results
            $activities = array_slice($activities, 0, $limit);

            return response()->json([
                'success' => true,
                'data' => $activities,
                'total' => count($activities)
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching activity data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch activity data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getTimeAgo($timestamp)
    {
        $past = \Carbon\Carbon::parse($timestamp);
        return $past->format('M d, Y H:i');
    }

    /**
     * Get published articles for media management
     */
    public function getMediaArticles(Request $request)
    {
        try {
            $user = $request->user();
            $genre = $request->input('genre', 'all');
            $search = $request->input('search', '');
            $isFeatured = $request->input('featured');
            
            $query = Article::with(['media', 'user', 'featured'])
                ->where('user_id', $user->id);
            
            // Filter by genre
            if ($genre !== 'all') {
                $query->where('genre', $genre);
            }
            
            // Filter by featured status
            if ($isFeatured !== null) {
                if ($isFeatured === 'true') {
                    $query->whereHas('featured');
                } else {
                    $query->whereDoesntHave('featured');
                }
            }
            
            // Search functionality
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('content', 'like', "%{$search}%");
                });
            }
            
            $articles = $query->orderBy('created_at', 'desc')->paginate(12);
            
            return ArticleResource::collection($articles);
        } catch (\Exception $e) {
            Log::error('Error fetching media articles: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch articles'], 500);
        }
    }

    /**
     * Update article (edit)
     */
    public function update(Request $request, Article $article)
    {
        try {
            // Check if user owns the article
            if ($article->user_id !== $request->user()->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'genre' => 'required|in:articles,opinions,sports,editorial,artworks,creative',
                'status' => 'required|in:draft,published,archived',
            ]);

            $article->update($validated);

            // If publishing from draft, set published_at
            if ($validated['status'] === 'published' && !$article->published_at) {
                $article->update(['published_at' => now()]);
            }

            broadcast(new \App\Events\ActivityUpdated());

            return new ArticleResource($article->load('media'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error updating article: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update article'], 500);
        }
    }

    /**
     * Archive/Unarchive article
     */
    public function toggleArchive(Request $request, Article $article)
    {
        try {
            // Check if user owns the article
            if ($article->user_id !== $request->user()->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $newStatus = $article->status === 'archived' ? 'published' : 'archived';
            
            $article->update(['status' => $newStatus]);

            // If archiving, remove from featured table
            if ($newStatus === 'archived') {
                Featured::unfeatureArticle($article->id);
            }

            return response()->json([
                'message' => $newStatus === 'archived' ? 'Article archived' : 'Article unarchived',
                'status' => $newStatus
            ]);
        } catch (\Exception $e) {
            Log::error('Error toggling archive: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update article status'], 500);
        }
    }

    /**
     * Toggle featured status
     */
    public function toggleFeatured(Request $request, Article $article)
    {
        Log::info('toggleFeatured called', [
            'article_id' => $article->id,
            'user_id' => $request->user()->id,
            'article_user_id' => $article->user_id,
            'article_status' => $article->status
        ]);
        
        try {
            // Check if user owns the article
            if ($article->user_id !== $request->user()->id) {
                Log::warning('Unauthorized attempt to feature article', [
                    'article_id' => $article->id,
                    'article_owner' => $article->user_id,
                    'request_user' => $request->user()->id
                ]);
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Only published articles can be featured
            if ($article->status !== 'published') {
                Log::warning('Attempt to feature unpublished article', [
                    'article_id' => $article->id,
                    'status' => $article->status
                ]);
                return response()->json(['message' => 'Only published articles can be featured'], 422);
            }

            $isCurrentlyFeatured = Featured::isFeatured($article->id);
            Log::info('Current featured status', ['article_id' => $article->id, 'is_featured' => $isCurrentlyFeatured]);
            
            if ($isCurrentlyFeatured) {
                // Unfeature the article
                Featured::unfeatureArticle($article->id);
                $message = 'Article unfeatured';
                $isFeatured = false;
                $featuredAt = null;
            } else {
                // Feature the article
                $featured = Featured::featureArticle($article->id);
                $message = 'Article featured';
                $isFeatured = true;
                $featuredAt = $featured->featured_at;
            }

            Log::info('Featured status updated successfully', [
                'article_id' => $article->id,
                'new_status' => $isFeatured,
                'featured_at' => $featuredAt
            ]);

            return response()->json([
                'message' => $message,
                'is_featured' => $isFeatured,
                'featured_at' => $featuredAt
            ]);
        } catch (\Exception $e) {
            Log::error('Error toggling featured: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update featured status'], 500);
        }
    }

    /**
     * Get featured articles
     */
    public function getFeaturedArticles(Request $request)
    {
        try {
            $articles = Article::with(['media', 'user', 'featured'])
                ->where('status', 'published')
                ->where('status', '!=', 'archived') // Exclude archived articles
                ->whereHas('featured')
                ->orderByDesc(
                    Article::select('featured_at')
                        ->from('featured')
                        ->whereColumn('featured.article_id', 'articles.id')
                        ->limit(1)
                )
                ->paginate(10);

            return ArticleResource::collection($articles);
        } catch (\Exception $e) {
            Log::error('Error fetching featured articles: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch featured articles'], 500);
        }
    }

    /**
     * Get public featured articles
     */
    public function publicFeaturedArticles(Request $request)
    {
        try {
            $articles = Article::with(['media', 'user'])
                ->where('status', 'published')
                ->where('status', '!=', 'archived') // Exclude archived articles
                ->whereHas('featured')
                ->orderByDesc(
                    Article::select('featured_at')
                        ->from('featured')
                        ->whereColumn('featured.article_id', 'articles.id')
                        ->limit(1)
                )
                ->paginate(15);

            return ArticleResource::collection($articles);
        } catch (\Exception $e) {
            Log::error('Error fetching public featured articles: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch featured articles'], 500);
        }
    }

    /**
     * Get graph data for dashboard charts
     */
    public function getGraphData(Request $request)
    {
        try {
            $user = $request->user();
            
            // Get submissions for the last 8 days (current day + 7 previous)
            $startDate = now()->subDays(7)->startOfDay();
            $endDate = now()->endOfDay();
            
            // Get all dates in the range
            $dateRange = [];
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                $dateRange[] = $currentDate->format('Y-m-d');
                $currentDate->addDay();
            }
            
            // Content submissions over time (review_content and review_images)
            $contentSubmissions = DB::table('review_content')
                ->join('group_chats', 'review_content.group_id', '=', 'group_chats.id')
                ->join('group_chat_members', 'group_chats.id', '=', 'group_chat_members.group_chat_id')
                ->where('group_chat_members.user_id', $user->id)
                ->where('review_content.uploaded_at', '>=', $startDate)
                ->where('review_content.uploaded_at', '<=', $endDate)
                ->selectRaw('DATE(review_content.uploaded_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->get()
                ->keyBy('date');
                
            $imageSubmissions = DB::table('review_images')
                ->join('group_chats', 'review_images.group_id', '=', 'group_chats.id')
                ->join('group_chat_members', 'group_chats.id', '=', 'group_chat_members.group_chat_id')
                ->where('group_chat_members.user_id', $user->id)
                ->where('review_images.uploaded_at', '>=', $startDate)
                ->where('review_images.uploaded_at', '<=', $endDate)
                ->selectRaw('DATE(review_images.uploaded_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->get()
                ->keyBy('date');
                
            // Combine submissions for each date in the range
            $submissionsData = collect($dateRange)->map(function($date) use ($contentSubmissions, $imageSubmissions) {
                $contentCount = $contentSubmissions->get($date)->count ?? 0;
                $imageCount = $imageSubmissions->get($date)->count ?? 0;
                $totalCount = $contentCount + $imageCount;
                
                return [
                    'date' => $date,
                    'count' => $totalCount
                ];
            })->filter(function($item) {
                // Keep all dates, even with zero counts, for proper time series
                return true;
            });
            
            // Group Chat Status Distribution (pie chart) - ALL projects
            $allGroupChats = \App\Models\GroupChat::all();
            
            $groupChatStatusData = [
                'approved' => 0,
                'in_review' => 0,
                'pending' => 0,
            ];
            
            foreach ($allGroupChats as $groupChat) {
                $track = $groupChat->track ?? 'pending';
                
                if ($track === 'approved') {
                    $groupChatStatusData['approved']++;
                } elseif ($track === 'review') {
                    $groupChatStatusData['in_review']++;
                } else {
                    $groupChatStatusData['pending']++;
                }
            }
            
            // Article Publications - Most Viewed by Genre (bar chart)
            $articlePublications = \App\Models\Article::where('status', 'published')
                ->whereNotNull('genre')
                ->selectRaw('genre, COUNT(*) as article_count, SUM(COALESCE(article_metrics.visits, 0)) as total_views')
                ->leftJoin('article_metrics', 'articles.id', '=', 'article_metrics.article_id')
                ->groupBy('genre')
                ->orderBy('total_views', 'desc')
                ->get()
                ->map(function($item) {
                    return [
                        'genre' => $item->genre,
                        'count' => $item->total_views,
                        'article_count' => $item->article_count
                    ];
                });
            
            return response()->json([
                'content_submissions' => $submissionsData,
                'article_publications' => $articlePublications,
                'group_chat_status' => $groupChatStatusData,
                'activity_status' => [],
            ]);
        } catch (\Exception $e) {
            Log::error('Graph data error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user() ? $request->user()->id : 'guest'
            ]);
            
            return response()->json([
                'error' => 'Failed to load graph data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard statistics for the authenticated user
     */
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

    /**
     * Get activity details for modal
     */
    public function getActivityDetails(Request $request)
    {
        try {
            $id = $request->input('id');

            if (strpos($id, 'task_') === 0) {
                $chat_id = substr($id, 5);
                $chat = \App\Models\GroupChat::with(['members.profile', 'scrumBoard'])->find($chat_id);

                if (!$chat) {
                    return response()->json(['success' => false, 'message' => 'Group chat not found']);
                }

                $members = $chat->members->map(function($member) {
                    return $member->profile->name ?? $member->name ?? 'Unknown';
                })->toArray();

                // Versions: count of submitted content (review_content + review_images)
                $versions = \App\Models\ReviewContent::where('group_id', $chat_id)->count() +
                           \App\Models\ReviewImage::where('group_id', $chat_id)->count();

                $status = $chat->track ?? 'pending';

                return response()->json([
                    'success' => true,
                    'data' => [
                        'members' => $members,
                        'versions' => $versions,
                        'status' => $status
                    ]
                ]);
            } elseif (strpos($id, 'article_') === 0) {
                $article_id = substr($id, 8);
                $article = \App\Models\Article::with(['user.profile'])->find($article_id);

                if (!$article) {
                    return response()->json(['success' => false, 'message' => 'Article not found']);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'title' => $article->title,
                        'author' => $article->user->profile->name ?? $article->user->name ?? 'Unknown',
                        'genre' => $article->genre,
                        'published_at' => \Carbon\Carbon::parse($article->published_at)->format('M d, Y H:i')
                    ]
                ]);
            } elseif (strpos($id, 'review_') === 0) {
                $review_id = substr($id, 7);
                $review = \App\Models\ReviewContent::with(['user.profile', 'group'])->find($review_id);

                if (!$review) {
                    return response()->json(['success' => false, 'message' => 'Review not found']);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'user' => $review->user->profile->name ?? $review->user->name ?? 'Unknown',
                        'status' => $review->status,
                        'submitted_at' => \Carbon\Carbon::parse($review->uploaded_at)->format('M d, Y H:i')
                    ]
                ]);
            } elseif (strpos($id, 'topic_') === 0) {
                $topic_id = substr($id, 6);
                $topic = \App\Models\Topic::with(['user.profile'])->find($topic_id);

                if (!$topic) {
                    return response()->json(['success' => false, 'message' => 'Topic not found']);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'user' => $topic->user->profile->name ?? $topic->user->name ?? 'Unknown',
                        'created_at' => \Carbon\Carbon::parse($topic->created_at)->format('M d, Y H:i')
                    ]
                ]);
            } elseif (strpos($id, 'branding_') === 0) {
                $history_id = substr($id, 9);
                $history = \DB::table('branding_history')->find($history_id);

                if (!$history) {
                    return response()->json(['success' => false, 'message' => 'Branding history not found']);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'action' => $history->action,
                        'changes' => json_decode($history->changes, true)
                    ]
                ]);
            } elseif (strpos($id, 'module_') === 0) {
                $history_id = substr($id, 7);
                $history = \App\Models\ProfileModuleHistory::with(['profile.user', 'module'])->find($history_id);

                if (!$history) {
                    return response()->json(['success' => false, 'message' => 'Module history not found']);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'action' => $history->action,
                        'module_name' => $history->module->display_name ?? $history->module->name ?? 'Unknown',
                        'user' => $history->profile->user->name ?? 'Unknown'
                    ]
                ]);
            }

            return response()->json(['success' => true, 'data' => []]);

        } catch (\Exception $e) {
            Log::error('Error fetching activity details: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch details'], 500);
        }
    }
}