<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReviewContent;
use App\Models\ReviewImage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DocumentApprovalController extends Controller
{
    /**
     * Get the approval workflow for a document using existing tables
     */
    public function getWorkflow(Request $request): JsonResponse
    {
        $request->validate([
            'document_type' => 'required|in:ReviewContent,ReviewImage',
            'group_id' => 'required|integer',
        ]);

        $documentType = $request->input('document_type');
        $groupId = $request->input('group_id');

        // Get all versions of documents in this group
        $documents = [];
        if ($documentType === 'ReviewContent') {
            $documents = ReviewContent::where('group_id', $groupId)
                ->with(['user', 'currentReviewer'])
                ->orderBy('version')
                ->orderBy('uploaded_at')
                ->get();
        } else {
            $documents = ReviewImage::where('group_id', $groupId)
                ->with(['user', 'currentReviewer'])
                ->orderBy('version')
                ->orderBy('uploaded_at')
                ->get();
        }

        if ($documents->isEmpty()) {
            return response()->json(['error' => 'No documents found in this group'], 404);
        }

        // Build the workflow
        $workflow = [];
        $firstDocument = $documents->first();

        // 1. Initial submission
        $workflow[] = [
            'stage' => 'initial_submission',
            'stage_label' => 'Initial Submission',
            'action' => 'submitted',
            'action_label' => 'Submitted',
            'user' => [
                'id' => $firstDocument->user->id,
                'name' => $firstDocument->user->name,
                'email' => $firstDocument->user->email,
            ],
            'action_at' => $firstDocument->uploaded_at->toISOString(),
            'comments' => null,
            'version' => $firstDocument->version,
        ];

        // 2. Reviews (versions 2 and above)
        $reviewDocuments = $documents->where('version', '>', 1);
        foreach ($reviewDocuments as $doc) {
            $action = 'submitted';
            $actionLabel = 'Revision Submitted';
            $stage = 'revision';
            $stageLabel = 'Revision';

            // If this version is approved, it was the final approval
            if ($doc->status === 'approved') {
                $action = 'final_approved';
                $actionLabel = 'Final Approval';
                $stage = 'final_review';
                $stageLabel = 'Final Review';
            }

            $workflow[] = [
                'stage' => $stage,
                'stage_label' => $stageLabel,
                'action' => $action,
                'action_label' => $actionLabel,
                'user' => [
                    'id' => $doc->user->id,
                    'name' => $doc->user->name,
                    'email' => $doc->user->email,
                ],
                'action_at' => $doc->uploaded_at->toISOString(),
                'comments' => null, // Could be added later if comments are stored
                'version' => $doc->version,
            ];

            // If this was reviewed by someone else, show the reviewer
            if ($doc->current_reviewer_id && $doc->current_reviewer_id !== $doc->user_id) {
                $reviewer = $doc->currentReviewer;
                if ($reviewer) {
                    $workflow[] = [
                        'stage' => 'review',
                        'stage_label' => 'Review',
                        'action' => 'approved',
                        'action_label' => 'Reviewed',
                        'user' => [
                            'id' => $reviewer->id,
                            'name' => $reviewer->name,
                            'email' => $reviewer->email,
                        ],
                        'action_at' => $doc->uploaded_at->toISOString(), // Approximate timing
                        'comments' => null,
                        'version' => $doc->version,
                    ];
                }
            }
        }

        // Group information
        $group = $firstDocument->group;

        return response()->json([
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'document_type' => $documentType,
            ],
            'workflow' => collect($workflow)->sortBy('action_at')->values(),
            'total_versions' => $documents->max('version'),
            'final_status' => $documents->last()->status,
        ]);
    }
}
