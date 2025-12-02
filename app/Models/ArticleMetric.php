<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticleMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'visits',
        'visitor_ips',
        'visitor_ips_with_dates',
        'like_count',
        'heart_count',
        'sad_count',
        'wow_count',
    ];

    protected $casts = [
        'visitor_ips' => 'array',
        'visitor_ips_with_dates' => 'array',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
