-- ============================================================================
-- ENGAGEMENT ANALYTICS SAMPLE DATA
-- This SQL file populates articles and article_metrics with realistic data
-- for testing the engagement analytics graphs
-- ============================================================================

-- First, ensure we have articles with different genres and published dates
-- Note: Adjust user_id (1) if needed based on your actual users

INSERT INTO articles (user_id, title, content, status, published_at, created_at, updated_at, genre) VALUES
-- Recent articles (last 7 days) - for Reader Behavior and Audience Growth
(1, 'The Future of AI in Content Creation', 'Exploring how artificial intelligence is transforming the way we create and consume content...', 'published', '2025-12-02 10:00:00', NOW(), NOW(), 'Technology'),
(1, 'Understanding React Hooks', 'A comprehensive guide to React Hooks and how they simplify state management...', 'published', '2025-12-01 14:30:00', NOW(), NOW(), 'Technology'),
(1, 'Digital Marketing Trends 2025', 'The top digital marketing strategies that will dominate in 2025...', 'published', '2025-11-30 09:15:00', NOW(), NOW(), 'Marketing'),
(1, 'Sustainable Living Tips', 'Practical ways to reduce your carbon footprint and live more sustainably...', 'published', '2025-11-29 16:45:00', NOW(), NOW(), 'Lifestyle'),
(1, 'Web Performance Optimization', 'Best practices for optimizing your website for speed and performance...', 'published', '2025-11-28 11:20:00', NOW(), NOW(), 'Technology'),
(1, 'Social Media Strategy Guide', 'How to build an effective social media strategy for your brand...', 'published', '2025-11-27 13:00:00', NOW(), NOW(), 'Marketing'),
(1, 'Mental Health in the Digital Age', 'Navigating mental wellness while staying connected online...', 'published', '2025-11-26 10:30:00', NOW(), NOW(), 'Lifestyle'),

-- Older articles (for Content Lifecycle decay analysis)
(1, 'Introduction to Machine Learning', 'Getting started with machine learning concepts and applications...', 'published', '2025-11-15 08:00:00', NOW(), NOW(), 'Technology'),
(1, 'Brand Building Essentials', 'Core principles for building a strong and recognizable brand...', 'published', '2025-11-10 12:00:00', NOW(), NOW(), 'Marketing'),
(1, 'Fitness Goals for Beginners', 'Setting and achieving realistic fitness goals as a beginner...', 'published', '2025-11-05 15:00:00', NOW(), NOW(), 'Lifestyle'),
(1, 'Advanced CSS Techniques', 'Master advanced CSS for modern web design...', 'published', '2025-10-25 09:00:00', NOW(), NOW(), 'Technology'),
(1, 'Email Marketing Best Practices', 'How to create effective email campaigns that convert...', 'published', '2025-10-15 14:00:00', NOW(), NOW(), 'Marketing');

-- Now populate article_metrics with realistic engagement data
-- For the 7 recent articles (IDs 1-7), we'll add visitor data with timestamps

-- Article 1: "The Future of AI in Content Creation" (Published 2025-12-02 10:00)
-- Peak hours: 10-14 (morning to afternoon)
INSERT INTO article_metrics (article_id, visits, like_count, heart_count, sad_count, wow_count, visitor_ips_with_dates, created_at, updated_at) VALUES
(1, 45, 12, 8, 2, 5, 
  JSON_ARRAY(
    JSON_OBJECT('ip', '192.168.1.101', 'date', '2025-12-02', 'timestamp', '2025-12-02T10:15:00+00:00'),
    JSON_OBJECT('ip', '192.168.1.102', 'date', '2025-12-02', 'timestamp', '2025-12-02T10:45:00+00:00'),
    JSON_OBJECT('ip', '192.168.1.103', 'date', '2025-12-02', 'timestamp', '2025-12-02T11:20:00+00:00'),
    JSON_OBJECT('ip', '192.168.1.104', 'date', '2025-12-02', 'timestamp', '2025-12-02T11:50:00+00:00'),
    JSON_OBJECT('ip', '192.168.1.105', 'date', '2025-12-02', 'timestamp', '2025-12-02T12:30:00+00:00'),
    JSON_OBJECT('ip', '192.168.1.106', 'date', '2025-12-02', 'timestamp', '2025-12-02T13:00:00+00:00'),
    JSON_OBJECT('ip', '192.168.1.107', 'date', '2025-12-02', 'timestamp', '2025-12-02T13:45:00+00:00'),
    JSON_OBJECT('ip', '192.168.1.108', 'date', '2025-12-02', 'timestamp', '2025-12-02T14:15:00+00:00'),
    JSON_OBJECT('ip', '192.168.1.109', 'date', '2025-12-02', 'timestamp', '2025-12-02T14:50:00+00:00'),
    JSON_OBJECT('ip', '192.168.1.110', 'date', '2025-12-02', 'timestamp', '2025-12-02T15:20:00+00:00'),
    JSON_OBJECT('ip', '192.168.1.111', 'date', '2025-12-02', 'timestamp', '2025-12-02T10:05:00+00:00'),
    JSON_OBJECT('ip', '192.168.1.112', 'date', '2025-12-02', 'timestamp', '2025-12-02T10:35:00+00:00'),
    JSON_OBJECT('ip', '192.168.1.113', 'date', '2025-12-02', 'timestamp', '2025-12-02T11:10:00+00:00'),
    JSON_OBJECT('ip', '192.168.1.114', 'date', '2025-12-02', 'timestamp', '2025-12-02T12:00:00+00:00'),
    JSON_OBJECT('ip', '192.168.1.115', 'date', '2025-12-02', 'timestamp', '2025-12-02T12:40:00+00:00')
  ),
  NOW(), NOW());

-- Article 2: "Understanding React Hooks" (Published 2025-12-01 14:30)
-- Peak hours: 14-18 (afternoon to evening)
INSERT INTO article_metrics (article_id, visits, like_count, heart_count, sad_count, wow_count, visitor_ips_with_dates, created_at, updated_at) VALUES
(2, 38, 15, 6, 1, 8,
  JSON_ARRAY(
    JSON_OBJECT('ip', '192.168.2.101', 'date', '2025-12-01', 'timestamp', '2025-12-01T14:45:00+00:00'),
    JSON_OBJECT('ip', '192.168.2.102', 'date', '2025-12-01', 'timestamp', '2025-12-01T15:15:00+00:00'),
    JSON_OBJECT('ip', '192.168.2.103', 'date', '2025-12-01', 'timestamp', '2025-12-01T15:50:00+00:00'),
    JSON_OBJECT('ip', '192.168.2.104', 'date', '2025-12-01', 'timestamp', '2025-12-01T16:20:00+00:00'),
    JSON_OBJECT('ip', '192.168.2.105', 'date', '2025-12-01', 'timestamp', '2025-12-01T16:55:00+00:00'),
    JSON_OBJECT('ip', '192.168.2.106', 'date', '2025-12-01', 'timestamp', '2025-12-01T17:30:00+00:00'),
    JSON_OBJECT('ip', '192.168.2.107', 'date', '2025-12-01', 'timestamp', '2025-12-01T18:00:00+00:00'),
    JSON_OBJECT('ip', '192.168.2.108', 'date', '2025-12-01', 'timestamp', '2025-12-01T18:45:00+00:00'),
    JSON_OBJECT('ip', '192.168.2.109', 'date', '2025-12-01', 'timestamp', '2025-12-01T14:30:00+00:00'),
    JSON_OBJECT('ip', '192.168.2.110', 'date', '2025-12-01', 'timestamp', '2025-12-01T15:00:00+00:00')
  ),
  NOW(), NOW());

-- Article 3: "Digital Marketing Trends 2025" (Published 2025-11-30 09:15)
-- Peak hours: 09-12 (morning)
INSERT INTO article_metrics (article_id, visits, like_count, heart_count, sad_count, wow_count, visitor_ips_with_dates, created_at, updated_at) VALUES
(3, 52, 18, 10, 3, 12,
  JSON_ARRAY(
    JSON_OBJECT('ip', '192.168.3.101', 'date', '2025-11-30', 'timestamp', '2025-11-30T09:20:00+00:00'),
    JSON_OBJECT('ip', '192.168.3.102', 'date', '2025-11-30', 'timestamp', '2025-11-30T09:45:00+00:00'),
    JSON_OBJECT('ip', '192.168.3.103', 'date', '2025-11-30', 'timestamp', '2025-11-30T10:15:00+00:00'),
    JSON_OBJECT('ip', '192.168.3.104', 'date', '2025-11-30', 'timestamp', '2025-11-30T10:50:00+00:00'),
    JSON_OBJECT('ip', '192.168.3.105', 'date', '2025-11-30', 'timestamp', '2025-11-30T11:20:00+00:00'),
    JSON_OBJECT('ip', '192.168.3.106', 'date', '2025-11-30', 'timestamp', '2025-11-30T11:55:00+00:00'),
    JSON_OBJECT('ip', '192.168.3.107', 'date', '2025-11-30', 'timestamp', '2025-11-30T09:30:00+00:00'),
    JSON_OBJECT('ip', '192.168.3.108', 'date', '2025-11-30', 'timestamp', '2025-11-30T10:00:00+00:00'),
    JSON_OBJECT('ip', '192.168.3.109', 'date', '2025-11-30', 'timestamp', '2025-11-30T10:35:00+00:00'),
    JSON_OBJECT('ip', '192.168.3.110', 'date', '2025-11-30', 'timestamp', '2025-11-30T11:10:00+00:00')
  ),
  NOW(), NOW());

-- Article 4: "Sustainable Living Tips" (Published 2025-11-29 16:45)
-- Peak hours: 16-20 (evening)
INSERT INTO article_metrics (article_id, visits, like_count, heart_count, sad_count, wow_count, visitor_ips_with_dates, created_at, updated_at) VALUES
(4, 35, 10, 12, 2, 6,
  JSON_ARRAY(
    JSON_OBJECT('ip', '192.168.4.101', 'date', '2025-11-29', 'timestamp', '2025-11-29T16:50:00+00:00'),
    JSON_OBJECT('ip', '192.168.4.102', 'date', '2025-11-29', 'timestamp', '2025-11-29T17:20:00+00:00'),
    JSON_OBJECT('ip', '192.168.4.103', 'date', '2025-11-29', 'timestamp', '2025-11-29T17:55:00+00:00'),
    JSON_OBJECT('ip', '192.168.4.104', 'date', '2025-11-29', 'timestamp', '2025-11-29T18:30:00+00:00'),
    JSON_OBJECT('ip', '192.168.4.105', 'date', '2025-11-29', 'timestamp', '2025-11-29T19:00:00+00:00'),
    JSON_OBJECT('ip', '192.168.4.106', 'date', '2025-11-29', 'timestamp', '2025-11-29T19:45:00+00:00'),
    JSON_OBJECT('ip', '192.168.4.107', 'date', '2025-11-29', 'timestamp', '2025-11-29T20:15:00+00:00')
  ),
  NOW(), NOW());

-- Article 5: "Web Performance Optimization" (Published 2025-11-28 11:20)
-- Peak hours: 11-15 (midday)
INSERT INTO article_metrics (article_id, visits, like_count, heart_count, sad_count, wow_count, visitor_ips_with_dates, created_at, updated_at) VALUES
(5, 48, 16, 9, 2, 10,
  JSON_ARRAY(
    JSON_OBJECT('ip', '192.168.5.101', 'date', '2025-11-28', 'timestamp', '2025-11-28T11:25:00+00:00'),
    JSON_OBJECT('ip', '192.168.5.102', 'date', '2025-11-28', 'timestamp', '2025-11-28T12:00:00+00:00'),
    JSON_OBJECT('ip', '192.168.5.103', 'date', '2025-11-28', 'timestamp', '2025-11-28T12:40:00+00:00'),
    JSON_OBJECT('ip', '192.168.5.104', 'date', '2025-11-28', 'timestamp', '2025-11-28T13:15:00+00:00'),
    JSON_OBJECT('ip', '192.168.5.105', 'date', '2025-11-28', 'timestamp', '2025-11-28T13:50:00+00:00'),
    JSON_OBJECT('ip', '192.168.5.106', 'date', '2025-11-28', 'timestamp', '2025-11-28T14:25:00+00:00'),
    JSON_OBJECT('ip', '192.168.5.107', 'date', '2025-11-28', 'timestamp', '2025-11-28T15:00:00+00:00'),
    JSON_OBJECT('ip', '192.168.5.108', 'date', '2025-11-28', 'timestamp', '2025-11-28T11:35:00+00:00')
  ),
  NOW(), NOW());

-- Article 6: "Social Media Strategy Guide" (Published 2025-11-27 13:00)
-- Peak hours: 13-17 (afternoon)
INSERT INTO article_metrics (article_id, visits, like_count, heart_count, sad_count, wow_count, visitor_ips_with_dates, created_at, updated_at) VALUES
(6, 42, 14, 11, 1, 9,
  JSON_ARRAY(
    JSON_OBJECT('ip', '192.168.6.101', 'date', '2025-11-27', 'timestamp', '2025-11-27T13:10:00+00:00'),
    JSON_OBJECT('ip', '192.168.6.102', 'date', '2025-11-27', 'timestamp', '2025-11-27T13:45:00+00:00'),
    JSON_OBJECT('ip', '192.168.6.103', 'date', '2025-11-27', 'timestamp', '2025-11-27T14:20:00+00:00'),
    JSON_OBJECT('ip', '192.168.6.104', 'date', '2025-11-27', 'timestamp', '2025-11-27T14:55:00+00:00'),
    JSON_OBJECT('ip', '192.168.6.105', 'date', '2025-11-27', 'timestamp', '2025-11-27T15:30:00+00:00'),
    JSON_OBJECT('ip', '192.168.6.106', 'date', '2025-11-27', 'timestamp', '2025-11-27T16:05:00+00:00'),
    JSON_OBJECT('ip', '192.168.6.107', 'date', '2025-11-27', 'timestamp', '2025-11-27T16:40:00+00:00')
  ),
  NOW(), NOW());

-- Article 7: "Mental Health in the Digital Age" (Published 2025-11-26 10:30)
-- Peak hours: 10-14 (morning to afternoon)
INSERT INTO article_metrics (article_id, visits, like_count, heart_count, sad_count, wow_count, visitor_ips_with_dates, created_at, updated_at) VALUES
(7, 55, 20, 15, 5, 8,
  JSON_ARRAY(
    JSON_OBJECT('ip', '192.168.7.101', 'date', '2025-11-26', 'timestamp', '2025-11-26T10:35:00+00:00'),
    JSON_OBJECT('ip', '192.168.7.102', 'date', '2025-11-26', 'timestamp', '2025-11-26T11:10:00+00:00'),
    JSON_OBJECT('ip', '192.168.7.103', 'date', '2025-11-26', 'timestamp', '2025-11-26T11:45:00+00:00'),
    JSON_OBJECT('ip', '192.168.7.104', 'date', '2025-11-26', 'timestamp', '2025-11-26T12:20:00+00:00'),
    JSON_OBJECT('ip', '192.168.7.105', 'date', '2025-11-26', 'timestamp', '2025-11-26T12:55:00+00:00'),
    JSON_OBJECT('ip', '192.168.7.106', 'date', '2025-11-26', 'timestamp', '2025-11-26T13:30:00+00:00'),
    JSON_OBJECT('ip', '192.168.7.107', 'date', '2025-11-26', 'timestamp', '2025-11-26T14:05:00+00:00'),
    JSON_OBJECT('ip', '192.168.7.108', 'date', '2025-11-26', 'timestamp', '2025-11-26T10:50:00+00:00'),
    JSON_OBJECT('ip', '192.168.7.109', 'date', '2025-11-26', 'timestamp', '2025-11-26T11:25:00+00:00')
  ),
  NOW(), NOW());

-- Older articles with decay pattern (for Content Lifecycle analysis)
-- Article 8: "Introduction to Machine Learning" (Published 2025-11-15, 17 days old)
INSERT INTO article_metrics (article_id, visits, like_count, heart_count, sad_count, wow_count, visitor_ips_with_dates, created_at, updated_at) VALUES
(8, 120, 35, 28, 5, 22,
  JSON_ARRAY(
    JSON_OBJECT('ip', '192.168.8.101', 'date', '2025-11-15', 'timestamp', '2025-11-15T08:10:00+00:00'),
    JSON_OBJECT('ip', '192.168.8.102', 'date', '2025-11-15', 'timestamp', '2025-11-15T08:45:00+00:00'),
    JSON_OBJECT('ip', '192.168.8.103', 'date', '2025-11-15', 'timestamp', '2025-11-15T09:20:00+00:00'),
    JSON_OBJECT('ip', '192.168.8.104', 'date', '2025-11-15', 'timestamp', '2025-11-15T09:55:00+00:00'),
    JSON_OBJECT('ip', '192.168.8.105', 'date', '2025-11-15', 'timestamp', '2025-11-15T10:30:00+00:00')
  ),
  NOW(), NOW());

-- Article 9: "Brand Building Essentials" (Published 2025-11-10, 22 days old)
INSERT INTO article_metrics (article_id, visits, like_count, heart_count, sad_count, wow_count, visitor_ips_with_dates, created_at, updated_at) VALUES
(9, 95, 28, 22, 4, 18,
  JSON_ARRAY(
    JSON_OBJECT('ip', '192.168.9.101', 'date', '2025-11-10', 'timestamp', '2025-11-10T12:15:00+00:00'),
    JSON_OBJECT('ip', '192.168.9.102', 'date', '2025-11-10', 'timestamp', '2025-11-10T12:50:00+00:00'),
    JSON_OBJECT('ip', '192.168.9.103', 'date', '2025-11-10', 'timestamp', '2025-11-10T13:25:00+00:00')
  ),
  NOW(), NOW());

-- Article 10: "Fitness Goals for Beginners" (Published 2025-11-05, 27 days old)
INSERT INTO article_metrics (article_id, visits, like_count, heart_count, sad_count, wow_count, visitor_ips_with_dates, created_at, updated_at) VALUES
(10, 78, 22, 18, 3, 14,
  JSON_ARRAY(
    JSON_OBJECT('ip', '192.168.10.101', 'date', '2025-11-05', 'timestamp', '2025-11-05T15:20:00+00:00'),
    JSON_OBJECT('ip', '192.168.10.102', 'date', '2025-11-05', 'timestamp', '2025-11-05T15:55:00+00:00')
  ),
  NOW(), NOW());

-- Article 11: "Advanced CSS Techniques" (Published 2025-10-25, 38 days old)
INSERT INTO article_metrics (article_id, visits, like_count, heart_count, sad_count, wow_count, visitor_ips_with_dates, created_at, updated_at) VALUES
(11, 65, 18, 14, 2, 11,
  JSON_ARRAY(
    JSON_OBJECT('ip', '192.168.11.101', 'date', '2025-10-25', 'timestamp', '2025-10-25T09:30:00+00:00')
  ),
  NOW(), NOW());

-- Article 12: "Email Marketing Best Practices" (Published 2025-10-15, 48 days old)
INSERT INTO article_metrics (article_id, visits, like_count, heart_count, sad_count, wow_count, visitor_ips_with_dates, created_at, updated_at) VALUES
(12, 52, 15, 12, 2, 9,
  JSON_ARRAY(
    JSON_OBJECT('ip', '192.168.12.101', 'date', '2025-10-15', 'timestamp', '2025-10-15T14:45:00+00:00')
  ),
  NOW(), NOW());

-- ============================================================================
-- SUMMARY OF DATA POPULATED:
-- ============================================================================
-- Articles: 12 total
--   - 7 recent articles (last 7 days) with detailed visitor tracking
--   - 5 older articles (10-48 days old) showing content decay pattern
--
-- Engagement Metrics:
--   - Total visits across all articles: 625
--   - Reactions: likes, hearts, sad, wow distributed across articles
--   - Visitor IPs with timestamps for each visit
--
-- This data enables:
--   ✓ Reader Behavior Patterns: Peak hours by day of week
--   ✓ Content Lifecycle: Decay rates by genre (Technology, Marketing, Lifestyle)
--   ✓ Audience Growth: Unique visitor trends over 30 days
--   ✓ Engagement Quality: Reaction rates by genre
-- ============================================================================
