<?php

namespace App\Services;

use App\Models\Article;
use App\Models\UserPreference;
use App\Models\ArticleReaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class RecommendationEngine
{
    private const INTERACTION_WEIGHTS = [
        'view' => 1.0,
        'like' => 3.0,
        'heart' => 4.0,
        'wow' => 2.5,
        'sad' => 1.5,
        'time_spent' => 0.1, // per second
        'scroll' => 0.02, // per percentage point
    ];

    /**
     * Get personalized recommendations for a user
     */
    public function getRecommendations($userId, $limit = 5)
    {
        if (!$userId) {
            return $this->getFallbackRecommendations($limit);
        }

        $userPreferences = $this->getUserPreferenceProfile($userId);
        $contentBasedRecs = $this->getContentBasedRecommendations($userId, $userPreferences, $limit);
        $collaborativeRecs = $this->getCollaborativeRecommendations($userId, $limit);
        
        // Hybrid approach: combine content-based and collaborative filtering
        $recommendations = $this->combineRecommendations($contentBasedRecs, $collaborativeRecs, $limit);
        
        return $recommendations;
    }

    /**
     * Build user preference profile based on interaction history
     */
    private function getUserPreferenceProfile($userId)
    {
        $preferences = UserPreference::getUserGenrePreferences($userId);
        $recentInteractions = UserPreference::getUserInteractionPatterns($userId, 30);
        
        $profile = [
            'genre_weights' => [],
            'avg_time_spent' => 0,
            'preferred_interaction_types' => [],
            'reading_patterns' => []
        ];

        // Calculate genre preferences
        foreach ($preferences as $pref) {
            $profile['genre_weights'][$pref->genre] = $pref->total_weight;
        }

        // Calculate reading patterns
        if ($recentInteractions->count() > 0) {
            $profile['avg_time_spent'] = $recentInteractions->avg('time_spent');
            $profile['preferred_interaction_types'] = $recentInteractions
                ->groupBy('interaction_type')
                ->map->count()
                ->sortDesc()
                ->keys()
                ->toArray();
        }

        return $profile;
    }

    /**
     * Content-based recommendations using user's genre preferences
     */
    private function getContentBasedRecommendations($userId, $userProfile, $limit)
    {
        $readArticleIds = UserPreference::where('user_id', $userId)
            ->pluck('article_id')
            ->toArray();

        $query = Article::with(['media', 'user', 'metrics'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->whereNotIn('id', $readArticleIds);

        // Score articles based on user's genre preferences
        if (!empty($userProfile['genre_weights'])) {
            $genreWeights = $userProfile['genre_weights'];
            $maxWeight = max($genreWeights);
            
            // Normalize weights and create scoring
            $cases = [];
            foreach ($genreWeights as $genre => $weight) {
                $normalizedWeight = $weight / $maxWeight;
                $cases[] = "WHEN genre = '{$genre}' THEN {$normalizedWeight}";
            }
            
            $caseStatement = 'CASE ' . implode(' ', $cases) . ' ELSE 0.1 END';
            $query->selectRaw("articles.*, ({$caseStatement}) as recommendation_score");
            $query->orderByDesc('recommendation_score');
        }

        return $query->limit($limit * 2)->get(); // Get more for mixing
    }

    /**
     * Collaborative filtering based on similar users
     */
    private function getCollaborativeRecommendations($userId, $limit)
    {
        // Find users with similar preferences
        $userGenres = UserPreference::where('user_id', $userId)
            ->select('genre')
            ->distinct()
            ->pluck('genre')
            ->toArray();

        if (empty($userGenres)) {
            return collect();
        }

        $similarUsers = UserPreference::whereIn('genre', $userGenres)
            ->where('user_id', '!=', $userId)
            ->select('user_id')
            ->distinct()
            ->pluck('user_id')
            ->toArray();

        if (empty($similarUsers)) {
            return collect();
        }

        $readArticleIds = UserPreference::where('user_id', $userId)
            ->pluck('article_id')
            ->toArray();

        // Get articles liked by similar users that current user hasn't read
        $recommendations = Article::with(['media', 'user', 'metrics'])
            ->whereHas('preferences', function($query) use ($similarUsers) {
                $query->whereIn('user_id', $similarUsers)
                      ->whereIn('interaction_type', ['like', 'heart', 'wow']);
            })
            ->whereNotIn('id', $readArticleIds)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->withCount(['preferences as popularity_score' => function($query) use ($similarUsers) {
                $query->whereIn('user_id', $similarUsers)
                      ->whereIn('interaction_type', ['like', 'heart', 'wow']);
            }])
            ->orderByDesc('popularity_score')
            ->limit($limit * 2)
            ->get();

        return $recommendations;
    }

    /**
     * Combine different recommendation strategies
     */
    private function combineRecommendations($contentBased, $collaborative, $limit)
    {
        $combined = collect();
        
        // Interleave recommendations (60% content-based, 40% collaborative)
        $contentCount = ceil($limit * 0.6);
        $collabCount = $limit - $contentCount;
        
        $combined = $combined->merge($contentBased->take($contentCount));
        $combined = $combined->merge($collaborative->take($collabCount));
        
        // Remove duplicates and limit
        return $combined->unique('id')->take($limit);
    }

    /**
     * Fallback recommendations for non-authenticated users
     */
    private function getFallbackRecommendations($limit)
    {
        return Article::with(['media', 'user', 'metrics'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->leftJoin('article_metrics', 'articles.id', '=', 'article_metrics.article_id')
            ->orderByRaw('COALESCE(article_metrics.visits, 0) + COALESCE(article_metrics.like_count, 0) * 2 DESC')
            ->select('articles.*')
            ->limit($limit)
            ->get();
    }

    /**
     * Record user interaction for learning
     */
    public function recordInteraction($userId, $articleId, $interactionType, $additionalData = [])
    {
        \Log::info('🔍 Recording Interaction', [
            'user_id' => $userId,
            'article_id' => $articleId,
            'interaction_type' => $interactionType,
            'additional_data' => $additionalData
        ]);

        $article = Article::find($articleId);
        if (!$article) {
            \Log::warning('❌ Article not found', ['article_id' => $articleId]);
            return;
        }

        $weight = self::INTERACTION_WEIGHTS[$interactionType] ?? 1.0;
        
        // Adjust weight based on additional data
        if ($interactionType === 'time_spent' && isset($additionalData['time_spent'])) {
            $weight = $additionalData['time_spent'] * self::INTERACTION_WEIGHTS['time_spent'];
        }
        
        if ($interactionType === 'scroll' && isset($additionalData['scroll_percentage'])) {
            $weight = $additionalData['scroll_percentage'] * self::INTERACTION_WEIGHTS['scroll'];
        }

        try {
            $preference = UserPreference::create([
                'user_id' => $userId,
                'article_id' => $articleId,
                'genre' => $article->genre,
                'interaction_type' => $interactionType,
                'interaction_weight' => $weight,
                'time_spent' => $additionalData['time_spent'] ?? 0,
                'scroll_percentage' => $additionalData['scroll_percentage'] ?? 0,
                'session_id' => $additionalData['session_id'] ?? null,
            ]);
            
            \Log::info('✅ Interaction recorded successfully', ['preference_id' => $preference->id]);
        } catch (\Exception $e) {
            \Log::error('❌ Failed to record interaction', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
