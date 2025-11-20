<?php

namespace App\Http\Controllers;

use App\Models\ArticleBookmark;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ArticleBookmarkController extends Controller
{
    /**
     * Get all bookmarks for the authenticated user
     */
    public function index(Request $request)
    {
        $userId = Auth::id();
        
        $bookmarks = ArticleBookmark::where('user_id', $userId)
            ->with('article:id,title,published_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($bookmarks);
    }

    /**
     * Get bookmarks for a specific article
     */
    public function getByArticle(Request $request, $articleId)
    {
        $userId = Auth::id();
        
        $bookmarks = ArticleBookmark::where('user_id', $userId)
            ->where('article_id', $articleId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($bookmarks);
    }

    /**
     * Create a new bookmark
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'article_id' => 'required|exists:articles,id',
            'highlighted_text' => 'required|string',
            'start_offset' => 'nullable|integer',
            'end_offset' => 'nullable|integer',
            'context_before' => 'nullable|string|max:100',
            'context_after' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $bookmark = ArticleBookmark::create([
            'user_id' => Auth::id(),
            'article_id' => $validated['article_id'],
            'highlighted_text' => $validated['highlighted_text'],
            'start_offset' => $validated['start_offset'] ?? null,
            'end_offset' => $validated['end_offset'] ?? null,
            'context_before' => $validated['context_before'] ?? null,
            'context_after' => $validated['context_after'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Bookmark created successfully',
            'bookmark' => $bookmark->load('article:id,title,published_at')
        ], 201);
    }

    /**
     * Update a bookmark (mainly for notes)
     */
    public function update(Request $request, $id)
    {
        $bookmark = ArticleBookmark::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $bookmark->update($validated);

        return response()->json([
            'message' => 'Bookmark updated successfully',
            'bookmark' => $bookmark
        ]);
    }

    /**
     * Delete a bookmark
     */
    public function destroy($id)
    {
        $bookmark = ArticleBookmark::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $bookmark->delete();

        return response()->json([
            'message' => 'Bookmark deleted successfully'
        ]);
    }

    /**
     * Get public bookmarks for a specific user (for viewing other users' profiles)
     */
    public function getUserBookmarks($userId)
    {
        $bookmarks = ArticleBookmark::where('user_id', $userId)
            ->with('article:id,title,published_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($bookmarks);
    }
}
