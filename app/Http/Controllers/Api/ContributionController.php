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
            'category' => 'required|in:artwork,literature,story,coverage',
            'files.*' => 'file|max:5120', // 5MB max per file
        ]);

        // Create the contribution
        $contribution = new Contribution([
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'content' => $validated['content'],
            'category' => $validated['category'],
            'status' => 'pending',
        ]);

        $contribution->save();

        // Handle file uploads
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('contributions/' . $contribution->id, 'public');
                
                $contribution->media()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        return response()->json([
            'message' => 'Contribution submitted successfully!',
            'data' => $contribution->load('media')
        ], 201);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Contribution::with('media');
        
        if (!$user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        $contributions = $query->latest()->paginate(10);
        
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
