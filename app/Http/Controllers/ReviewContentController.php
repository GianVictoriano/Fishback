<?php

namespace App\Http\Controllers;

use App\Models\ReviewContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class ReviewContentController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:txt,pdf,doc,docx,rtf,ppt,pptx,xls,xlsx|max:51200',
                'group_id' => 'required|integer|exists:group_chats,id',
                'user_id' => 'required|integer|exists:users,id',
                'current_reviewer_id' => 'nullable|integer|exists:users,id',
                'review_stage' => 'sometimes|string|in:initial,peer_review,lead_review,approved',
                'status' => 'sometimes|string',
                'no_of_approval' => 'sometimes|integer',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $path = $request->file('file')->store('review_uploads', 'public');

            $reviewContent = ReviewContent::create([
                'file' => $path,
                'group_id' => $request->input('group_id'),
                'user_id' => $request->input('user_id'),
                'current_reviewer_id' => $request->input('current_reviewer_id'),
                'review_stage' => $request->input('review_stage', 'initial'),
                'status' => $request->input('status', 'pending'),
                'no_of_approval' => $request->input('no_of_approval', 0),
                'uploaded_at' => now(),
                'is_folio_submission' => $request->input('is_folio_submission', false),
                'folio_id' => $request->input('folio_id'),
            ]);

            // Post system message to group chat (draft/sent)
        \App\Models\ChatMessage::create([
            'user_id' => null,
            'message' => 'File "' . basename($reviewContent->file) . '" was uploaded by ' . (auth()->user() ? auth()->user()->name : 'a user') . ' and is awaiting review.',
            'group_chat_id' => $reviewContent->group_id,
            'system' => true,
            'type' => 'sent',
        ]);

        return response()->json($reviewContent, 201);

        } catch (Exception $e) {
            Log::error('Failed to create review content: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'message' => 'An unexpected error occurred on the server.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $query = ReviewContent::query();

            if ($request->has('group_id')) {
                $query->where('group_id', $request->group_id);
            } else {
                $groupIds = $user->groupChats()->pluck('group_chats.id');
                $query->whereIn('group_id', $groupIds);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('review_stage')) {
                $query->where('review_stage', $request->review_stage);
            }

            if ($request->has('current_reviewer_id')) {
                $query->where('current_reviewer_id', $request->current_reviewer_id);
            }

            // Exclude folio submissions - they should only appear in manage folio
            $query->where(function($q) {
                $q->where('is_folio_submission', false)
                  ->orWhereNull('is_folio_submission');
            });

            $reviewContents = $query->with(['user:id,name', 'group:id,name'])->orderByDesc('uploaded_at')->get();

            return response()->json($reviewContents);

        } catch (Exception $e) {
            Log::error('Failed to fetch review content: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch review content.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve review content.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function approve($id)
    {
        try {
            $reviewContent = ReviewContent::findOrFail($id);
            $reviewContent->status = 'approved';
            $reviewContent->no_of_approval += 1;
            $reviewContent->save();

            // Post system message to group chat
            \App\Models\ChatMessage::create([
                'user_id' => null,
                'message' => 'The draft was approved by ' . (auth()->user() ? auth()->user()->name : 'a reviewer') . '.',
                'group_chat_id' => $reviewContent->group_id,
                'system' => true,
                'type' => 'approve',
            ]);

            return response()->json($reviewContent);
        } catch (Exception $e) {
            Log::error("Failed to approve review content for id {$id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to approve content.'], 500);
        }
    }

    /**
     * Update review content (for forwarding).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $reviewContent = ReviewContent::findOrFail($id);
            
            $validated = $request->validate([
                'current_reviewer_id' => 'nullable|integer|exists:users,id',
                'review_stage' => 'nullable|string|in:initial,peer_review,lead_review,approved',
                'status' => 'nullable|string|in:pending,approved,rejected',
            ]);
            
            $reviewContent->update($validated);
            
            return response()->json($reviewContent);
        } catch (Exception $e) {
            Log::error("Failed to update review content for id {$id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to update content.'], 500);
        }
    }

    /**
     * Reject review content.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function reject($id)
    {
        try {
            $reviewContent = ReviewContent::findOrFail($id);
            $reviewContent->status = 'rejected';
            $reviewContent->save();

            // Post system message to group chat
            \App\Models\ChatMessage::create([
                'user_id' => null,
                'message' => 'The draft was rejected by ' . (auth()->user() ? auth()->user()->name : 'a reviewer') . '.',
                'group_chat_id' => $reviewContent->group_id,
                'system' => true,
                'type' => 'reject',
            ]);

            return response()->json($reviewContent);
        } catch (Exception $e) {
            Log::error("Failed to reject review content for id {$id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to reject content.'], 500);
        }
    }

    /**
     * Preview the extracted text content of a review file (txt/pdf/doc/docx).
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function previewText($id)
    {
        try {
            $review = ReviewContent::findOrFail($id);
            $filePath = storage_path('app/public/' . $review->file);
            if (!file_exists($filePath)) {
                return response()->json(['error' => 'File not found.'], 404);
            }
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            if ($ext === 'txt') {
                $text = file_get_contents($filePath);
            } elseif ($ext === 'pdf') {
                try {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($filePath);
                    $text = $pdf->getText();
                } catch (\Exception $e) {
                    return response()->json(['error' => 'Failed to extract text from PDF: ' . $e->getMessage()], 400);
                }
            } elseif (in_array($ext, ['doc', 'docx'])) {
                try {
                    $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
                    $text = '';
                    foreach ($phpWord->getSections() as $section) {
                        $elements = $section->getElements();
                        foreach ($elements as $element) {
                            if (method_exists($element, 'getText')) {
                                $text .= $element->getText() . "\n";
                            }
                        }
                    }
                } catch (\Exception $e) {
                    return response()->json(['error' => 'Failed to extract text from DOC/DOCX: ' . $e->getMessage()], 400);
                }
            } else {
                return response()->json(['error' => 'Preview not supported for this file type.'], 415);
            }
            return response()->json(['text' => $text]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to preview file: ' . $e->getMessage()], 500);
        }
    }
    public function preview($id)
{
    return $this->previewText($id);
}
}


