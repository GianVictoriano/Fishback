<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleReaction extends Model
{
    protected $fillable = [
        'user_id',
        'article_id',
        'ip_address',
        'reaction_type',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
