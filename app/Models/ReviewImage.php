<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'file',
        'group_id',
        'user_id',
        'current_reviewer_id',
        'review_stage',
        'status',
        'no_of_approval',
        'uploaded_at',
        'is_folio_submission',
        'folio_id',
    ];

    protected $casts = [
        'is_folio_submission' => 'boolean',
        'uploaded_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function currentReviewer()
    {
        return $this->belongsTo(User::class, 'current_reviewer_id');
    }

    public function group()
    {
        return $this->belongsTo(GroupChat::class, 'group_id');
    }
}
