<?php

namespace App\Http\Controllers;

use App\Models\ReviewContent;
use App\Models\GroupChat;
use App\Models\Topic;
use App\Models\CoverageRequest;
use App\Models\FolioSubmission;
use App\Models\Contribution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics for the authenticated user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatistics(Request $request)
    {
        $user = Auth::user();
        $stats = [];

        // Get user's group chat IDs
        $groupChatIds = $user->groupChats()->pluck('group_chats.id');

        // Pending Reviews (review-content module)
        if ($this->hasModule($user, 'review-content')) {
            $pendingReviews = ReviewContent::whereIn('group_id', $groupChatIds)
                ->where(function($q) {
                    $q->where('is_folio_submission', false)
                      ->orWhereNull('is_folio_submission');
                })
                ->where('status', 'pending')
                ->count();
            
            $stats['pending_reviews'] = $pendingReviews;
        }

        // Group chat statistics (collaborate module)
        if ($this->hasModule($user, 'collaborate')) {
            // Total group chats
            $stats['total_group_chats'] = $groupChatIds->count();
            
            // Active group chats (with recent activity)
            $activeGroupChats = GroupChat::whereIn('id', $groupChatIds)
                ->where('status', 'active')
                ->count();
            $stats['active_group_chats'] = $activeGroupChats;
        }
        
        // Forum moderation (forum module)
        if ($this->hasModule($user, 'forum')) {
            $reportedTopics = Topic::where('status', 'reported')->count();
            $stats['reported_topics'] = $reportedTopics;
        }

        // Coverage Requests (manage-requests module)
        if ($this->hasModule($user, 'requests')) {
            $pendingCoverageRequests = CoverageRequest::where('status', 'pending')->count();
            $totalCoverageRequests = CoverageRequest::count();
            
            $stats['pending_coverage_requests'] = $pendingCoverageRequests;
            $stats['total_coverage_requests'] = $totalCoverageRequests;
        }

        // Folio Submissions (manage-folio module)
        if ($this->hasModule($user, 'folio')) {
            $pendingSubmissions = FolioSubmission::where('status', 'pending')->count();
            $totalSubmissions = FolioSubmission::count();
            $approvedSubmissions = FolioSubmission::where('status', 'approved')->count();
            
            $stats['pending_folio_submissions'] = $pendingSubmissions;
            $stats['total_folio_submissions'] = $totalSubmissions;
            $stats['approved_folio_submissions'] = $approvedSubmissions;
        }

        // User's own contributions
        $userContributions = Contribution::where('user_id', $user->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
        
        $stats['my_contributions'] = [
            'pending' => $userContributions['pending'] ?? 0,
            'approved' => $userContributions['approved'] ?? 0,
            'rejected' => $userContributions['rejected'] ?? 0,
        ];

        // Recent activity
        $recentActivity = $this->getRecentActivity($user, $groupChatIds);
        $stats['recent_activity'] = $recentActivity;

        return response()->json([
            'status' => 'success',
            'data' => $stats
        ]);
    }

    /**
     * Check if user has a specific module
     *
     * @param  \App\Models\User  $user
     * @param  string  $moduleName
     * @return bool
     */
    private function hasModule($user, $moduleName)
    {
        if (!$user->profile) {
            return false;
        }

        return $user->profile->modules()
            ->where('name', $moduleName)
            ->exists();
    }

    /**
     * Get recent activity for the user
     *
     * @param  \App\Models\User  $user
     * @param  \Illuminate\Support\Collection  $groupChatIds
     * @return array
     */
    private function getRecentActivity($user, $groupChatIds)
    {
        $activities = [];

        // Recent review submissions
        $recentReviews = ReviewContent::whereIn('group_id', $groupChatIds)
            ->where(function($q) {
                $q->where('is_folio_submission', false)
                  ->orWhereNull('is_folio_submission');
            })
            ->with(['user:id,name', 'group:id,name'])
            ->orderBy('uploaded_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentReviews as $review) {
            $activities[] = [
                'type' => 'review_submitted',
                'text' => ($review->user->name ?? 'Someone') . ' submitted content for review in ' . ($review->group->name ?? 'a group'),
                'time' => $review->uploaded_at,
                'icon' => 'file-text'
            ];
        }

        // Recent contributions
        $recentContributions = Contribution::with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentContributions as $contribution) {
            $activities[] = [
                'type' => 'contribution_submitted',
                'text' => ($contribution->user->name ?? 'Someone') . ' submitted a ' . $contribution->category,
                'time' => $contribution->created_at,
                'icon' => 'git-pull-request'
            ];
        }

        // Recent folio submissions
        $recentFolioSubmissions = FolioSubmission::with('user:id,name')
            ->orderBy('submitted_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentFolioSubmissions as $submission) {
            $activities[] = [
                'type' => 'folio_submission',
                'text' => ($submission->user->name ?? 'Someone') . ' submitted "' . $submission->title . '" to a folio',
                'time' => $submission->submitted_at,
                'icon' => 'book'
            ];
        }

        // Sort all activities by time and take the most recent 10
        usort($activities, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });

        return array_slice($activities, 0, 10);
    }
}
