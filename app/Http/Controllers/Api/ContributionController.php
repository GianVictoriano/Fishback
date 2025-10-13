<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contribution;
use Illuminate\Http\Request;
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
        ]);

        // Prepare base data
        $data = [
            'user_id' => auth()->id(),
            'title'    => $validated['title'],
            'category' => $validated['category'],
            'status'   => 'pending',
        ];

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
        $contributions = Contribution::with('media')
            ->latest()
            ->paginate(10);
            
        return response()->json($contributions);
    }

    public function show(Contribution $contribution)
    {
        $this->authorize('view', $contribution);
        return response()->json($contribution->load('media', 'user'));
    }

    public function updateStatus(Request $request, Contribution $contribution)
    {
        $this->authorize('update', $contribution);
        
        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected',
            'admin_notes' => 'nullable|string',
        ]);

        $contribution->update([
            'status' => $validated['status'],
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        // TODO: Notify user about status change

        return response()->json([
            'message' => 'Status updated successfully',
            'data' => $contribution
        ]);
    }
}
