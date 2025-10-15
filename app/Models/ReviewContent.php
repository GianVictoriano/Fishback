<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewContent extends Model
{
    use HasFactory;

    protected $table = 'review_content';

    protected $fillable = [
        'file',
        'group_id',
        'user_id',
        'current_reviewer_id',
        'review_stage',
        'status',
        'uploaded_at',
        'no_of_approval',
        'is_folio_submission',
        'folio_id',
    ];

    public $timestamps = false;

    protected $casts = [
        'uploaded_at' => 'datetime',
        'is_folio_submission' => 'boolean',
    ];

    public function group()
    {
        return $this->belongsTo(GroupChat::class, 'group_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function currentReviewer()
    {
        return $this->belongsTo(User::class, 'current_reviewer_id');
    }
}
