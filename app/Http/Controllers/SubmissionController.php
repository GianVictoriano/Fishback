<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SubmissionController extends Controller
{
    public function index()
    {
        $submissions = Submission::with(['user', 'reviewer', 'media'])
            ->orderBy('submitted_at', 'desc')
            ->paginate(15);

        return response()->json($submissions);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'genre' => 'required|in:artwork,literature,photography',
            'title' => 'required|string|max:255',
            'caption' => 'required|string|max:1000',
            'file' => 'required|file|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create submission
        $submission = Submission::create([
            'user_id' => Auth::id(),
            'genre' => $request->genre,
            'title' => $request->title,
            'caption' => $request->caption,
            'submitted_at' => now(),
        ]);

        // Handle file upload
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('submissions', 'public');

            Media::create([
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'mediable_id' => $submission->id,
                'mediable_type' => Submission::class,
            ]);
        }

        return response()->json([
            'message' => 'Submission created successfully',
            'submission' => $submission->load(['user', 'media'])
        ], 201);
    }

    public function show(Submission $submission)
    {
        return response()->json($submission->load(['user', 'reviewer', 'media']));
    }

    public function update(Request $request, Submission $submission)
    {
        // Only allow admins or the submission owner to update
        if (Auth::user()->profile?->role !== 'admin' && $submission->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:pending,approved,rejected,revision_requested',
            'admin_feedback' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $submission->update([
            'status' => $request->status ?? $submission->status,
            'admin_feedback' => $request->admin_feedback,
            'reviewed_at' => $request->status && $request->status !== 'pending' ? now() : $submission->reviewed_at,
            'reviewed_by' => $request->status && $request->status !== 'pending' ? Auth::id() : $submission->reviewed_by,
        ]);

        return response()->json([
            'message' => 'Submission updated successfully',
            'submission' => $submission->load(['user', 'reviewer', 'media'])
        ]);
    }

    public function destroy(Submission $submission)
    {
        // Only allow admins or the submission owner to delete
        if (Auth::user()->profile?->role !== 'admin' && $submission->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete associated media files
        foreach ($submission->media as $media) {
            Storage::disk('public')->delete($media->file_path);
            $media->delete();
        }

        $submission->delete();

        return response()->json(['message' => 'Submission deleted successfully']);
    }

    // Get submissions for current user
    public function mySubmissions()
    {
        $submissions = Submission::where('user_id', Auth::id())
            ->with(['reviewer', 'media'])
            ->orderBy('submitted_at', 'desc')
            ->get();

        return response()->json($submissions);
    }
}
