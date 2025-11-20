<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Content Analyzer Service
 * Analyzes article content for similarity matching using TF-IDF
 */
class ContentAnalyzer
{
    // Common stop words to filter out
    private static $stopWords = [
        'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
        'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'been',
        'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
        'could', 'should', 'may', 'might', 'must', 'can', 'this', 'that',
        'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they',
        'what', 'which', 'who', 'when', 'where', 'why', 'how', 'all', 'each',
        'every', 'both', 'few', 'more', 'most', 'other', 'some', 'such',
        'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too',
        'very', 'just', 'about', 'into', 'through', 'during', 'before',
        'after', 'above', 'below', 'between', 'under', 'again', 'further',
        'then', 'once'
    ];

    /**
     * Extract keywords from article content
     */
    public static function extractKeywords($content, $limit = 20)
    {
        // Strip HTML tags
        $text = strip_tags($content);
        
        // Convert to lowercase
        $text = strtolower($text);
        
        // Remove special characters, keep only letters and spaces
        $text = preg_replace('/[^a-z\s]/', ' ', $text);
        
        // Split into words
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // Filter out stop words and short words
        $words = array_filter($words, function($word) {
            return strlen($word) > 3 && !in_array($word, self::$stopWords);
        });
        
        // Apply basic stemming
        $words = array_map([self::class, 'stemWord'], $words);
        
        // Count word frequencies
        $wordCounts = array_count_values($words);
        
        // Sort by frequency
        arsort($wordCounts);
        
        // Return top keywords
        return array_slice($wordCounts, 0, $limit, true);
    }

    /**
     * Basic word stemming (removes common suffixes)
     */
    private static function stemWord($word)
    {
        $suffixes = ['ing', 'ed', 'er', 'est', 'ly', 'ion', 'tion', 'ness', 'ment', 'ity', 'ies', 'es', 's'];
        
        foreach ($suffixes as $suffix) {
            if (strlen($word) > strlen($suffix) + 3 && substr($word, -strlen($suffix)) === $suffix) {
                return substr($word, 0, -strlen($suffix));
            }
        }
        
        return $word;
    }

    /**
     * Calculate TF-IDF for an article
     * TF = Term Frequency (how often word appears in this article)
     * IDF = Inverse Document Frequency (how rare the word is across all articles)
     */
    public static function calculateTFIDF($articleId)
    {
        $cacheKey = "article_tfidf_{$articleId}";
        
        return Cache::remember($cacheKey, 3600, function() use ($articleId) {
            $article = Article::find($articleId);
            if (!$article) return [];

            // Get keywords for this article
            $keywords = self::extractKeywords($article->content);
            
            // Get total number of articles
            $totalArticles = Article::where('status', 'published')->count();
            
            $tfidf = [];
            
            foreach ($keywords as $word => $frequency) {
                // TF: frequency in this article
                $tf = $frequency;
                
                // IDF: log(total articles / articles containing this word)
                $articlesWithWord = Article::where('status', 'published')
                    ->where(function($query) use ($word) {
                        $query->where('title', 'LIKE', "%{$word}%")
                              ->orWhere('content', 'LIKE', "%{$word}%");
                    })
                    ->count();
                
                $idf = $articlesWithWord > 0 
                    ? log($totalArticles / $articlesWithWord) 
                    : 0;
                
                // TF-IDF score
                $tfidf[$word] = $tf * $idf;
            }
            
            // Normalize scores
            $maxScore = max($tfidf) ?: 1;
            foreach ($tfidf as $word => $score) {
                $tfidf[$word] = $score / $maxScore;
            }
            
            return $tfidf;
        });
    }

    /**
     * Calculate cosine similarity between two articles
     */
    public static function calculateSimilarity($articleId1, $articleId2)
    {
        $tfidf1 = self::calculateTFIDF($articleId1);
        $tfidf2 = self::calculateTFIDF($articleId2);
        
        if (empty($tfidf1) || empty($tfidf2)) return 0;
        
        // Get all unique words from both articles
        $allWords = array_unique(array_merge(array_keys($tfidf1), array_keys($tfidf2)));
        
        // Create vectors
        $vector1 = [];
        $vector2 = [];
        
        foreach ($allWords as $word) {
            $vector1[] = $tfidf1[$word] ?? 0;
            $vector2[] = $tfidf2[$word] ?? 0;
        }
        
        // Calculate cosine similarity
        return self::cosineSimilarity($vector1, $vector2);
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    private static function cosineSimilarity($vector1, $vector2)
    {
        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;
        
        for ($i = 0; $i < count($vector1); $i++) {
            $dotProduct += $vector1[$i] * $vector2[$i];
            $magnitude1 += $vector1[$i] * $vector1[$i];
            $magnitude2 += $vector2[$i] * $vector2[$i];
        }
        
        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);
        
        if ($magnitude1 == 0 || $magnitude2 == 0) return 0;
        
        return $dotProduct / ($magnitude1 * $magnitude2);
    }

    /**
     * Find similar articles based on content
     */
    public static function findSimilarArticles($articleId, $limit = 10)
    {
        $article = Article::find($articleId);
        if (!$article) return collect([]);

        // Get all published articles except the current one
        $articles = Article::where('status', 'published')
            ->where('id', '!=', $articleId)
            ->get();

        $similarities = [];

        foreach ($articles as $otherArticle) {
            // Calculate content similarity
            $contentSimilarity = self::calculateSimilarity($articleId, $otherArticle->id);
            
            // Boost score if same genre
            $genreBoost = ($article->genre === $otherArticle->genre) ? 0.3 : 0;
            
            // Combined score
            $score = $contentSimilarity + $genreBoost;
            
            $similarities[] = [
                'article' => $otherArticle,
                'score' => $score,
                'content_similarity' => $contentSimilarity,
                'genre_match' => $article->genre === $otherArticle->genre
            ];
        }

        // Sort by score
        usort($similarities, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // Return top articles
        return collect(array_slice($similarities, 0, $limit))
            ->pluck('article');
    }

    /**
     * Get user's content preferences based on interaction history
     */
    public static function getUserContentPreferences($userId)
    {
        $preferences = \App\Models\UserPreference::where('user_id', $userId)
            ->where('interaction_weight', '>', 2) // Only significant interactions
            ->with('article')
            ->get();

        $contentProfile = [];

        foreach ($preferences as $pref) {
            if (!$pref->article) continue;
            
            $keywords = self::extractKeywords($pref->article->content, 10);
            
            foreach ($keywords as $word => $frequency) {
                if (!isset($contentProfile[$word])) {
                    $contentProfile[$word] = 0;
                }
                // Weight by interaction strength
                $contentProfile[$word] += $frequency * $pref->interaction_weight;
            }
        }

        // Normalize
        if (!empty($contentProfile)) {
            $maxWeight = max($contentProfile);
            foreach ($contentProfile as $word => $weight) {
                $contentProfile[$word] = $weight / $maxWeight;
            }
        }

        return $contentProfile;
    }

    /**
     * Score an article based on user's content preferences
     */
    public static function scoreArticleForUser($articleId, $userId)
    {
        $userProfile = self::getUserContentPreferences($userId);
        if (empty($userProfile)) return 0;

        $articleKeywords = self::extractKeywords(
            Article::find($articleId)->content ?? '', 
            20
        );

        $score = 0;
        $matches = 0;

        foreach ($articleKeywords as $word => $frequency) {
            if (isset($userProfile[$word])) {
                $score += $userProfile[$word] * $frequency;
                $matches++;
            }
        }

        // Normalize by number of matches
        return $matches > 0 ? $score / $matches : 0;
    }
}
