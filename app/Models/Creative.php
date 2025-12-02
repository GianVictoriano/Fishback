<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Creative extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'genre',
        'title',
        'caption',
        'status',
        'published_at',
        'admin_feedback',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Media relationship (polymorphic)
    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    // Metrics relationship
    public function metrics()
    {
        return $this->hasOne(CreativeMetrics::class);
    }

    // Reactions relationship
    public function reactions()
    {
        return $this->hasMany(CreativeReaction::class);
    }

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopePendingReview($query)
    {
        return $query->where('status', 'pending_review');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeByGenre($query, $genre)
    {
        return $query->where('genre', $genre);
    }

    // Helper methods
    public function isPublished()
    {
        return $this->status === 'published';
    }

    public function canBeEdited()
    {
        return in_array($this->status, ['draft', 'rejected']);
    }
}
