<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EngagementAnalyticsController extends Controller
{
    /**
     * Get reader behavior patterns - peak hours by day
     * Shows when readers are most active
     */
    public function getReaderBehavior(Request $request)
    {
        try {
            $period = $request->input('period', 30);
            $startDate = now()->subDays($period)->startOfDay();

            Log::info('=== Reader Behavior Debug ===');
            Log::info('Period: ' . $period);
            Log::info('Start Date: ' . $startDate->toDateTimeString());

            // Get all article metrics with visitor data
            $metrics = DB::table('article_metrics')
                ->join('articles', 'article_metrics.article_id', '=', 'articles.id')
                ->where('articles.published_at', '>=', $startDate)
                ->whereNotNull('article_metrics.visitor_ips_with_dates')
                ->select('article_metrics.visitor_ips_with_dates', 'articles.genre')
                ->get();

            Log::info('Total metrics records found: ' . $metrics->count());
            Log::info('Raw metrics data: ' . json_encode($metrics->toArray()));

            // Process the JSON array data
            $readerBehavior = [];
            foreach ($metrics as $metric) {
                Log::info('Processing metric with genre: ' . $metric->genre);
                Log::info('Raw visitor_ips_with_dates: ' . $metric->visitor_ips_with_dates);
                
                $visitorData = json_decode($metric->visitor_ips_with_dates, true);
                Log::info('Decoded visitor data: ' . json_encode($visitorData));
                
                if (is_array($visitorData)) {
                    Log::info('Visitor data is array, count: ' . count($visitorData));
                    foreach ($visitorData as $visitor) {
                        Log::info('Processing visitor: ' . json_encode($visitor));
                        if (isset($visitor['timestamp'])) {
                            $timestamp = $visitor['timestamp'];
                            $hour = (int) date('H', strtotime($timestamp));
                            $day = date('l', strtotime($timestamp)); // Full day name
                            
                            Log::info('Extracted - Hour: ' . $hour . ', Day: ' . $day . ', Timestamp: ' . $timestamp);
                            
                            $key = $hour . '-' . $day . '-' . $metric->genre;
                            if (!isset($readerBehavior[$key])) {
                                $readerBehavior[$key] = [
                                    'hour' => $hour,
                                    'day' => $day,
                                    'visits' => 0,
                                    'genre' => $metric->genre
                                ];
                            }
                            $readerBehavior[$key]['visits']++;
                        } else {
                            Log::warning('No timestamp found in visitor data: ' . json_encode($visitor));
                        }
                    }
                } else {
                    Log::warning('Visitor data is not array: ' . gettype($visitorData));
                }
            }

            // Convert to array and sort by visits
            $readerBehavior = array_values($readerBehavior);
            usort($readerBehavior, function($a, $b) {
                return $b['visits'] - $a['visits'];
            });

            Log::info('Final reader behavior data: ' . json_encode($readerBehavior));
            Log::info('Total records in response: ' . count($readerBehavior));

            return response()->json([
                'data' => $readerBehavior,
                'period' => $period,
                'debug' => ['total_records' => count($readerBehavior)]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getReaderBehavior: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Failed to fetch reader behavior data', 'debug' => $e->getMessage()], 500);
        }
    }

    /**
     * Get content lifecycle data - how quickly views decay by genre
     */
    public function getContentLifecycle(Request $request)
    {
        try {
            $period = $request->input('period', 90);
            $startDate = now()->subDays($period)->startOfDay();

            $lifecycle = DB::table('articles')
                ->join('article_metrics', 'articles.id', '=', 'article_metrics.article_id')
                ->where('articles.status', 'published')
                ->where('articles.published_at', '>=', $startDate)
                ->select(
                    'articles.id',
                    'articles.title',
                    'articles.genre',
                    'articles.published_at',
                    'article_metrics.visits',
                    DB::raw('DATEDIFF(NOW(), articles.published_at) as days_since_publish'),
                    DB::raw('(article_metrics.visits / DATEDIFF(NOW(), articles.published_at)) as avg_daily_views')
                )
                ->orderBy('days_since_publish', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'title' => $item->title,
                        'genre' => $item->genre,
                        'published_at' => $item->published_at,
                        'visits' => (int) $item->visits,
                        'days_since_publish' => (int) $item->days_since_publish,
                        'avg_daily_views' => (float) $item->avg_daily_views
                    ];
                })
                ->toArray();

            return response()->json([
                'data' => $lifecycle,
                'period' => $period
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getContentLifecycle: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch content lifecycle data'], 500);
        }
    }

    /**
     * Get audience growth metrics - unique visitors over time
     */
    public function getAudienceGrowth(Request $request)
    {
        try {
            $period = $request->input('period', 30);
            $startDate = now()->subDays($period)->startOfDay();
            $endDate = now()->endOfDay();

            // Get all article metrics with visitor data
            $metrics = DB::table('article_metrics')
                ->join('articles', 'article_metrics.article_id', '=', 'articles.id')
                ->where('articles.published_at', '>=', $startDate)
                ->where('articles.published_at', '<=', $endDate)
                ->whereNotNull('article_metrics.visitor_ips_with_dates')
                ->select('article_metrics.visitor_ips_with_dates', 'articles.genre')
                ->get();

            // Process the JSON array data
            $audienceGrowth = [];
            foreach ($metrics as $metric) {
                $visitorData = json_decode($metric->visitor_ips_with_dates, true);
                
                if (is_array($visitorData)) {
                    foreach ($visitorData as $visitor) {
                        if (isset($visitor['timestamp'])) {
                            $timestamp = $visitor['timestamp'];
                            $date = date('Y-m-d', strtotime($timestamp));
                            $ip = $visitor['ip'];
                            
                            $key = $date . '-' . $metric->genre;
                            if (!isset($audienceGrowth[$key])) {
                                $audienceGrowth[$key] = [
                                    'date' => $date,
                                    'unique_visitors' => 0,
                                    'genre' => $metric->genre,
                                    'total_visits' => 0,
                                    'ips' => []
                                ];
                            }
                            
                            // Count unique IPs
                            if (!in_array($ip, $audienceGrowth[$key]['ips'])) {
                                $audienceGrowth[$key]['ips'][] = $ip;
                                $audienceGrowth[$key]['unique_visitors']++;
                            }
                            $audienceGrowth[$key]['total_visits']++;
                        }
                    }
                }
            }

            // Remove the ips array and convert to array
            $audienceGrowth = array_map(function($item) {
                unset($item['ips']);
                return $item;
            }, array_values($audienceGrowth));

            // Sort by date
            usort($audienceGrowth, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });

            return response()->json([
                'data' => $audienceGrowth,
                'period' => $period
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getAudienceGrowth: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch audience growth data'], 500);
        }
    }

    /**
     * Get engagement quality metrics - reaction rates by genre
     */
    public function getEngagementQuality(Request $request)
    {
        try {
            $period = $request->input('period', 30);
            $startDate = now()->subDays($period)->startOfDay();

            $engagementQuality = DB::table('articles')
                ->join('article_metrics', 'articles.id', '=', 'article_metrics.article_id')
                ->where('articles.status', 'published')
                ->where('articles.published_at', '>=', $startDate)
                ->select(
                    'articles.genre',
                    'articles.title',
                    'article_metrics.visits',
                    DB::raw('(article_metrics.like_count + article_metrics.heart_count + article_metrics.sad_count + article_metrics.wow_count) as total_reactions'),
                    DB::raw('ROUND(((article_metrics.like_count + article_metrics.heart_count + article_metrics.sad_count + article_metrics.wow_count) / article_metrics.visits * 100), 2) as engagement_rate'),
                    'article_metrics.like_count',
                    'article_metrics.heart_count',
                    'article_metrics.sad_count',
                    'article_metrics.wow_count'
                )
                ->where('article_metrics.visits', '>', 0) // Avoid division by zero
                ->orderBy('engagement_rate', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'genre' => $item->genre,
                        'title' => $item->title,
                        'visits' => (int) $item->visits,
                        'total_reactions' => (int) $item->total_reactions,
                        'engagement_rate' => (float) $item->engagement_rate,
                        'like_count' => (int) $item->like_count,
                        'heart_count' => (int) $item->heart_count,
                        'sad_count' => (int) $item->sad_count,
                        'wow_count' => (int) $item->wow_count
                    ];
                })
                ->toArray();

            return response()->json([
                'data' => $engagementQuality,
                'period' => $period
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getEngagementQuality: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch engagement quality data'], 500);
        }
    }
}
