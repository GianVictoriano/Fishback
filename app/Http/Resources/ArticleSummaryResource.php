<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Lightweight representation for list views.
 * Only returns the fields that the front-end actually needs to render
 * a card: id, title, genre, published_at and the first media item path.
 */
class ArticleSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        // Build a lightweight excerpt from the content without returning full HTML
        $content = $this->content ?? '';
        $plainText = trim(strip_tags($content));
        $excerpt = Str::limit($plainText, 150);

        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'genre'        => $this->genre,
            'published_at' => $this->published_at,
            'excerpt'      => $excerpt,
            // Provide first image path (if any)
            'image_path'   => $this->media->first()->file_path ?? null,
        ];
    }
}
