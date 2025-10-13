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
        return array_merge(parent::toArray($request), [
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
