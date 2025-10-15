<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get the first media image if available
        $image = null;
        if ($this->media && $this->media->count() > 0) {
            $firstMedia = $this->media->first();
            $image = '/storage/' . str_replace('public/', '', $firstMedia->file_path);
        }

        return array_merge(parent::toArray($request), [
            'image' => $image,
            'author' => [
                'id' => $this->user->id ?? null,
                'name' => $this->user->name ?? 'Unknown',
            ],
            'metrics' => [
                'visits' => $this->metrics->visits ?? 0,
                'like_count' => $this->metrics->like_count ?? 0,
                'heart_count' => $this->metrics->heart_count ?? 0,
                'sad_count' => $this->metrics->sad_count ?? 0,
                'wow_count' => $this->metrics->wow_count ?? 0,
            ],
        ]);
    }
}
