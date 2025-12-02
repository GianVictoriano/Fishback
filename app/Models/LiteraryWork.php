<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiteraryWork extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'user_id',
        'heyzine_url',
        'status',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    /**
     * Get the user who created this literary work
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the metrics for this literary work
     */
    public function metrics()
    {
        return $this->hasOne(LiteraryWorkMetric::class);
    }

    /**
     * Get all reactions for this literary work
     */
    public function reactions()
    {
        return $this->hasMany(LiteraryWorkReaction::class);
    }

    /**
     * Get all interactions for this literary work
     */
    public function interactions()
    {
        return $this->hasMany(LiteraryWorkInteraction::class);
    }

    /**
     * Check if the work is published
     */
    public function isPublished()
    {
        return $this->status === 'published';
    }

    /**
     * Scope to get only published works
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope to get works by a specific user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get works with a specific status
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
