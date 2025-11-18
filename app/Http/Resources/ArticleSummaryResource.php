<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

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
        return [
            'id'            => $this->id,
            'title'         => $this->title,
            'genre'         => $this->genre,
            'published_at'  => $this->published_at,
            // Provide first image path (if any)
            'image_path'    => $this->media->first()->file_path ?? null,
        ];
    }
}
