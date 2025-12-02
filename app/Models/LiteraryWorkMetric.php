<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiteraryWorkMetric extends Model
{
    use HasFactory;

    protected $table = 'literary_work_metrics';

    protected $fillable = [
        'literary_work_id',
        'visits',
        'like_count',
        'heart_count',
        'sad_count',
        'wow_count',
    ];

    /**
     * Get the literary work this metric belongs to
     */
    public function literaryWork()
    {
        return $this->belongsTo(LiteraryWork::class);
    }

    /**
     * Get total reactions count
     */
    public function getTotalReactionsAttribute()
    {
        return $this->like_count + $this->heart_count + $this->sad_count + $this->wow_count;
    }

    /**
     * Increment visit count
     */
    public function incrementVisits()
    {
        $this->increment('visits');
        return $this;
    }

    /**
     * Increment reaction count by type
     */
    public function incrementReaction($type)
    {
        $column = "{$type}_count";
        if (in_array($type, ['like', 'heart', 'sad', 'wow'])) {
            $this->increment($column);
        }
        return $this;
    }
}
