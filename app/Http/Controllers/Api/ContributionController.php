<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contribution;
use App\Notifications\CoverageRequestApprovedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContributionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'required|in:artwork,fiction,poetry,essay,story,coverage',
            'files' => $request->category === 'artwork' ? 'required|array|min:1' : 'nullable|array',
            'files.*' => 'file|max:5120', // 5MB per file
            // Coverage-specific fields
            'event_date' => $request->category === 'coverage' ? 'required|date' : 'nullable|date',
            'event_location' => $request->category === 'coverage' ? 'required|string|max:255' : 'nullable|string|max:255',
            'num_journalists' => $request->category === 'coverage' ? 'required|integer|min:1' : 'nullable|integer|min:1',
        ]);

        // Prepare base data
        $data = [
            'user_id' => auth()->id(),
            'title'    => $validated['title'],
            'category' => $validated['category'],
            'status'   => 'pending',
        ];

        // Add coverage-specific fields if category is coverage
        if ($validated['category'] === 'coverage') {
            $data['event_date'] = $validated['event_date'];
            $data['event_location'] = $validated['event_location'];
            $data['num_journalists'] = $validated['num_journalists'];
        }

        // Handle according to category/type
        if ($validated['category'] === 'artwork') {
            // For artwork, we'll store the content as a description
            $data['content'] = $validated['content'] ?: 'Artwork submission';
            $data['content_file_path'] = null;
        } else {
            // For text-based submissions, store content in a file and keep a reference
            $txtFileName = Str::slug($validated['title']) . '-' . time() . '.txt';
            $contentPath = 'contributions/text/' . $txtFileName;
            Storage::disk('public')->put($contentPath, $validated['content']);
            $data['content'] = $validated['content']; // Store content in the database
            $data['content_file_path'] = $contentPath; // Also store file path for reference
        }

        $contribution = Contribution::create($data);

        // Handle file uploads
        if ($request->hasFile('files')) {
            $uploadedFiles = [];
            
            foreach ($request->file('files') as $file) {
                $path = $file->store('contributions/' . $contribution->id, 'public');
                $uploadedFiles[] = [
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getMimeType(),
                    'size'      => $file->getSize(),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            
            // Save all files to the database
            $contribution->media()->insert($uploadedFiles);
            
            // If artwork, save the first file as the main file
            if ($validated['category'] === 'artwork' && !empty($uploadedFiles)) {
                $contribution->update(['file_path' => $uploadedFiles[0]['file_path']]);
            }
        }

        return response()->json([
            'message' => 'Contribution submitted successfully!',
            'data' => $contribution->load('media')
        ], 201);
    }

    public function index(Request $request)
    {
        $contributions = Contribution::with(['media', 'user'])
            ->latest()
            ->paginate(10);
            
        return response()->json($contributions);
    }

    public function show(Contribution $contribution)
    {
        return response()->json($contribution->load('media', 'user'));
    }

    public function updateStatus(Request $request, Contribution $contribution)
    {
        // Only allow collaborators/admins to update status
        // You can add role checking here if needed
        // if (!auth()->user()->profile || auth()->user()->profile->role !== 'collaborator') {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }
        
        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected',
            'admin_notes' => 'nullable|string',
        ]);

        $contribution->update([
            'status' => $validated['status'],
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        // Send email notification if the request is approved and it's a coverage request
        if ($validated['status'] === 'approved' && $contribution->category === 'coverage') {
            try {
                $contribution->user->notify(new CoverageRequestApprovedNotification($contribution));
            } catch (\Exception $e) {
                Log::error('Failed to send approval notification: ' . $e->getMessage());
                // Continue execution even if email fails
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Status updated successfully',
            'data' => $contribution->load('user')
        ]);
    }
}
