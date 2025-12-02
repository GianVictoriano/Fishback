<?php

namespace App\Http\Controllers;

use App\Models\Creative;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CreativeController extends Controller
{
    public function index()
    {
        $creatives = Creative::with(['user', 'reviewer', 'media'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($creatives);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'genre' => 'required|in:artwork,poem,essay',
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

        // Create creative
        $creative = Creative::create([
            'user_id' => Auth::id(),
            'genre' => $request->genre,
            'title' => $request->title,
            'caption' => $request->caption,
        ]);

        // Handle file upload
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('creatives', 'public');

            Media::create([
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'mediable_id' => $creative->id,
                'mediable_type' => Creative::class,
            ]);
        }

        return response()->json([
            'message' => 'Creative work created successfully',
            'creative' => $creative->load(['user', 'media'])
        ], 201);
    }

    public function show(Creative $creative)
    {
        return response()->json($creative->load(['user', 'reviewer', 'media']));
    }

    public function update(Request $request, Creative $creative)
    {
        // Only allow admins or the creative owner to update
        if (Auth::user()->profile?->role !== 'admin' && $creative->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // If user is updating their own draft
        if ($creative->user_id === Auth::id() && $creative->canBeEdited()) {
            $validator = Validator::make($request->all(), [
                'genre' => 'sometimes|in:artwork,poem,essay',
                'title' => 'sometimes|string|max:255',
                'caption' => 'sometimes|string|max:1000',
                'file' => 'sometimes|file|max:10240',
                'status' => 'sometimes|in:draft,pending_review',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $creative->update([
                'genre' => $request->genre ?? $creative->genre,
                'title' => $request->title ?? $creative->title,
                'caption' => $request->caption ?? $creative->caption,
                'status' => $request->status ?? $creative->status,
            ]);

            // Handle file upload if provided
            if ($request->hasFile('file')) {
                // Delete existing media
                foreach ($creative->media as $media) {
                    Storage::disk('public')->delete($media->file_path);
                    $media->delete();
                }

                $file = $request->file('file');
                $path = $file->store('creatives', 'public');

                Media::create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'mediable_id' => $creative->id,
                    'mediable_type' => Creative::class,
                ]);
            }
        } else {
            // Admin updating status
            $validator = Validator::make($request->all(), [
                'status' => 'sometimes|in:draft,pending_review,published,rejected',
                'admin_feedback' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $creative->update([
                'status' => $request->status ?? $creative->status,
                'admin_feedback' => $request->admin_feedback,
                'reviewed_at' => $request->status && in_array($request->status, ['published', 'rejected']) ? now() : $creative->reviewed_at,
                'reviewed_by' => $request->status && in_array($request->status, ['published', 'rejected']) ? Auth::id() : $creative->reviewed_by,
                'published_at' => $request->status === 'published' ? now() : $creative->published_at,
            ]);
        }

        return response()->json([
            'message' => 'Creative work updated successfully',
            'creative' => $creative->load(['user', 'reviewer', 'media'])
        ]);
    }

    public function destroy(Creative $creative)
    {
        // Only allow admins or the creative owner to delete
        if (Auth::user()->profile?->role !== 'admin' && $creative->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete associated media files
        foreach ($creative->media as $media) {
            Storage::disk('public')->delete($media->file_path);
            $media->delete();
        }

        $creative->delete();

        return response()->json(['message' => 'Creative work deleted successfully']);
    }

    // Get creatives for current user
    public function myCreatives()
    {
        $creatives = Creative::where('user_id', Auth::id())
            ->with(['reviewer', 'media'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($creatives);
    }

    // Get published creatives
    public function published()
    {
        // For now, return all creatives (not just published) for testing
        // TODO: Change back to Creative::published() once publishing workflow is implemented
        $creatives = Creative::with(['user', 'media'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        // If no creatives exist, create some test data
        if ($creatives->total() === 0) {
            // Create test creative works
            $testCreatives = [
                [
                    'user_id' => 1, // Assuming user ID 1 exists
                    'genre' => 'artwork',
                    'title' => 'Sunset Dreams',
                    'caption' => 'An abstract representation of dreams under a setting sun, using mixed media techniques.',
                    'status' => 'published',
                    'published_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'user_id' => 1,
                    'genre' => 'poem',
                    'title' => 'Whispers of the Wind',
                    'caption' => 'A poetic reflection on the gentle power of nature and its eternal song.',
                    'status' => 'published',
                    'published_at' => now()->subDays(1),
                    'created_at' => now()->subDays(1),
                    'updated_at' => now()->subDays(1),
                ],
                [
                    'user_id' => 1,
                    'genre' => 'essay',
                    'title' => 'The Art of Creative Expression',
                    'caption' => 'Exploring how creativity shapes our understanding of the world around us.',
                    'status' => 'published',
                    'published_at' => now()->subDays(2),
                    'created_at' => now()->subDays(2),
                    'updated_at' => now()->subDays(2),
                ],
            ];

            foreach ($testCreatives as $creativeData) {
                $creative = Creative::create($creativeData);

                // Create associated media for each creative
                $mediaData = [
                    [
                        'file_path' => 'creatives/test-image-' . $creative->id . '.jpg',
                        'file_name' => 'test-image-' . $creative->id . '.jpg',
                        'file_type' => 'image/jpeg',
                        'size' => 1024000, // 1MB
                        'mediable_id' => $creative->id,
                        'mediable_type' => Creative::class,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                ];

                foreach ($mediaData as $media) {
                    \App\Models\Media::create($media);
                }
            }

            // Re-fetch after creating test data
            $creatives = Creative::with(['user', 'media'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);
        }

        return response()->json($creatives);
    }
}
