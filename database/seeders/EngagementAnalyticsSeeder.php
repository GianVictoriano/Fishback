<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EngagementAnalyticsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Clear existing data
        DB::table('article_metrics')->truncate();
        DB::table('articles')->truncate();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $userId = 1; // Adjust if needed based on your users

        // Create 7 recent articles (last 7 days)
        $recentArticles = [
            [
                'title' => 'The Future of AI in Content Creation',
                'content' => 'Exploring how artificial intelligence is transforming the way we create and consume content...',
                'genre' => 'Technology',
                'published_at' => Carbon::now()->subDays(0)->setHour(10)->setMinute(0),
                'visits' => 45,
                'reactions' => ['like' => 12, 'heart' => 8, 'sad' => 2, 'wow' => 5],
                'peak_hours' => [10, 11, 12, 13, 14],
            ],
            [
                'title' => 'Understanding React Hooks',
                'content' => 'A comprehensive guide to React Hooks and how they simplify state management...',
                'genre' => 'Technology',
                'published_at' => Carbon::now()->subDays(1)->setHour(14)->setMinute(30),
                'visits' => 38,
                'reactions' => ['like' => 15, 'heart' => 6, 'sad' => 1, 'wow' => 8],
                'peak_hours' => [14, 15, 16, 17, 18],
            ],
            [
                'title' => 'Digital Marketing Trends 2025',
                'content' => 'The top digital marketing strategies that will dominate in 2025...',
                'genre' => 'Marketing',
                'published_at' => Carbon::now()->subDays(2)->setHour(9)->setMinute(15),
                'visits' => 52,
                'reactions' => ['like' => 18, 'heart' => 10, 'sad' => 3, 'wow' => 12],
                'peak_hours' => [9, 10, 11, 12],
            ],
            [
                'title' => 'Sustainable Living Tips',
                'content' => 'Practical ways to reduce your carbon footprint and live more sustainably...',
                'genre' => 'Lifestyle',
                'published_at' => Carbon::now()->subDays(3)->setHour(16)->setMinute(45),
                'visits' => 35,
                'reactions' => ['like' => 10, 'heart' => 12, 'sad' => 2, 'wow' => 6],
                'peak_hours' => [16, 17, 18, 19, 20],
            ],
            [
                'title' => 'Web Performance Optimization',
                'content' => 'Best practices for optimizing your website for speed and performance...',
                'genre' => 'Technology',
                'published_at' => Carbon::now()->subDays(4)->setHour(11)->setMinute(20),
                'visits' => 48,
                'reactions' => ['like' => 16, 'heart' => 9, 'sad' => 2, 'wow' => 10],
                'peak_hours' => [11, 12, 13, 14, 15],
            ],
            [
                'title' => 'Social Media Strategy Guide',
                'content' => 'How to build an effective social media strategy for your brand...',
                'genre' => 'Marketing',
                'published_at' => Carbon::now()->subDays(5)->setHour(13)->setMinute(0),
                'visits' => 42,
                'reactions' => ['like' => 14, 'heart' => 11, 'sad' => 1, 'wow' => 9],
                'peak_hours' => [13, 14, 15, 16, 17],
            ],
            [
                'title' => 'Mental Health in the Digital Age',
                'content' => 'Navigating mental wellness while staying connected online...',
                'genre' => 'Lifestyle',
                'published_at' => Carbon::now()->subDays(6)->setHour(10)->setMinute(30),
                'visits' => 55,
                'reactions' => ['like' => 20, 'heart' => 15, 'sad' => 5, 'wow' => 8],
                'peak_hours' => [10, 11, 12, 13, 14],
            ],
        ];

        // Create older articles for decay analysis
        $olderArticles = [
            [
                'title' => 'Introduction to Machine Learning',
                'content' => 'Getting started with machine learning concepts and applications...',
                'genre' => 'Technology',
                'published_at' => Carbon::now()->subDays(17)->setHour(8)->setMinute(0),
                'visits' => 120,
                'reactions' => ['like' => 35, 'heart' => 28, 'sad' => 5, 'wow' => 22],
            ],
            [
                'title' => 'Brand Building Essentials',
                'content' => 'Core principles for building a strong and recognizable brand...',
                'genre' => 'Marketing',
                'published_at' => Carbon::now()->subDays(22)->setHour(12)->setMinute(0),
                'visits' => 95,
                'reactions' => ['like' => 28, 'heart' => 22, 'sad' => 4, 'wow' => 18],
            ],
            [
                'title' => 'Fitness Goals for Beginners',
                'content' => 'Setting and achieving realistic fitness goals as a beginner...',
                'genre' => 'Lifestyle',
                'published_at' => Carbon::now()->subDays(27)->setHour(15)->setMinute(0),
                'visits' => 78,
                'reactions' => ['like' => 22, 'heart' => 18, 'sad' => 3, 'wow' => 14],
            ],
            [
                'title' => 'Advanced CSS Techniques',
                'content' => 'Master advanced CSS for modern web design...',
                'genre' => 'Technology',
                'published_at' => Carbon::now()->subDays(38)->setHour(9)->setMinute(0),
                'visits' => 65,
                'reactions' => ['like' => 18, 'heart' => 14, 'sad' => 2, 'wow' => 11],
            ],
            [
                'title' => 'Email Marketing Best Practices',
                'content' => 'How to create effective email campaigns that convert...',
                'genre' => 'Marketing',
                'published_at' => Carbon::now()->subDays(48)->setHour(14)->setMinute(0),
                'visits' => 52,
                'reactions' => ['like' => 15, 'heart' => 12, 'sad' => 2, 'wow' => 9],
            ],
        ];

        // Merge all articles
        $allArticles = array_merge($recentArticles, $olderArticles);

        // Insert articles and metrics
        foreach ($allArticles as $index => $articleData) {
            $article = DB::table('articles')->insertGetId([
                'user_id' => $userId,
                'title' => $articleData['title'],
                'content' => $articleData['content'],
                'status' => 'published',
                'published_at' => $articleData['published_at'],
                'created_at' => $articleData['published_at'],
                'updated_at' => $articleData['published_at'],
                'genre' => $articleData['genre'],
            ]);

            // Generate visitor data
            $visitorData = $this->generateVisitorData(
                $articleData['published_at'],
                $articleData['visits'],
                $articleData['peak_hours'] ?? [8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20]
            );

            // Insert metrics
            DB::table('article_metrics')->insert([
                'article_id' => $article,
                'visits' => $articleData['visits'],
                'like_count' => $articleData['reactions']['like'],
                'heart_count' => $articleData['reactions']['heart'],
                'sad_count' => $articleData['reactions']['sad'],
                'wow_count' => $articleData['reactions']['wow'],
                'visitor_ips_with_dates' => json_encode($visitorData),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✅ Engagement Analytics sample data seeded successfully!');
        $this->command->info('📊 Created 12 articles with realistic engagement metrics');
        $this->command->info('📈 Data includes visitor tracking for Reader Behavior analysis');
    }

    /**
     * Generate realistic visitor data with timestamps
     */
    private function generateVisitorData($publishedAt, $visitCount, $peakHours)
    {
        $visitors = [];
        $ipBase = '192.168.';
        $ipCounter = 1;

        for ($i = 0; $i < $visitCount; $i++) {
            // Distribute visits across peak hours
            $randomHour = $peakHours[array_rand($peakHours)];
            $randomMinute = rand(0, 59);
            
            $timestamp = (clone $publishedAt)
                ->setHour($randomHour)
                ->setMinute($randomMinute)
                ->setSecond(rand(0, 59));

            $visitors[] = [
                'ip' => $ipBase . rand(1, 254) . '.' . rand(1, 254),
                'date' => $timestamp->format('Y-m-d'),
                'timestamp' => $timestamp->toIso8601String(),
            ];

            $ipCounter++;
        }

        return $visitors;
    }
}
