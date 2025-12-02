<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreativeMetrics extends Model
{
    use HasFactory;

    protected $table = 'creative_metrics';

    protected $fillable = [
        'creative_id',
        'visits',
        'like_count',
        'heart_count',
        'sad_count',
        'wow_count',
    ];

    protected $casts = [
        'visits' => 'integer',
        'like_count' => 'integer',
        'heart_count' => 'integer',
        'sad_count' => 'integer',
        'wow_count' => 'integer',
    ];

    // Relationships
    public function creative(): BelongsTo
    {
        return $this->belongsTo(Creative::class);
    }

    // Helper method to get total reactions
    public function getTotalReactions()
    {
        return $this->like_count + $this->heart_count + $this->sad_count + $this->wow_count;
    }
}
