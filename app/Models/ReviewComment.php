<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewComment extends Model
{
    use HasFactory;

    protected $table = 'review_comments';

    protected $fillable = [
        'review_content_id',
        'review_image_id',
        'user_id',
        'comment',
        'start_index',
        'end_index',
        'highlighted_text',
    ];

    public function reviewContent()
    {
        return $this->belongsTo(ReviewContent::class);
    }

    public function reviewImage()
    {
        return $this->belongsTo(ReviewImage::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
