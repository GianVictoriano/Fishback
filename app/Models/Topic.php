<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    use HasFactory;

    const STATUS_ACTIVE = 'active';
    const STATUS_REPORTED = 'reported';
    const STATUS_DELETED = 'deleted';

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'category',
        'secret',
        'status',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}
