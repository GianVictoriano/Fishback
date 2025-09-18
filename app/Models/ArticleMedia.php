<?php
// app/Models/ArticleMedia.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticleMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'file_path',
        'file_name',
        'file_type',
        'mime_type',
        'size',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'size' => 'integer',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}