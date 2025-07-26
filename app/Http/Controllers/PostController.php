<?php
//facebook api developer mode pa toh
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use App\Models\Post;

class PostController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'summary' => 'required|string',
            'article' => 'required|string',
            'image' => 'nullable|image|max:2048',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('posts', 'public');
        }

        $post = Post::create([
            'summary' => $request->input('summary'),
            'article' => $request->input('article'),
            'image_path' => $imagePath,
        ]);

        $this->postToFacebook($post);

        return response()->json(['message' => 'Post created successfully', 'post' => $post]);
    }

    protected function postToFacebook($post)
    {
        $pageAccessToken = env('FB_PAGE_ACCESS_TOKEN');
        $pageId = env('FB_PAGE_ID');
        $caption = $post->summary;

        if ($post->image_path) {
            $imagePath = Storage::disk('public')->path($post->image_path);
            Http::attach('source', file_get_contents($imagePath), basename($imagePath))
                ->post("https://graph.facebook.com/{$pageId}/photos", [
                    'message' => $caption,
                    'access_token' => $pageAccessToken,
                ]);
        } else {
            Http::post("https://graph.facebook.com/{$pageId}/feed", [
                'message' => $caption,
                'access_token' => $pageAccessToken,
            ]);
        }
    }
}
