<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

    const STATUS_ACTIVE = 'active';
    const STATUS_REPORTED = 'reported';
    const STATUS_DELETED = 'deleted';

    protected $fillable = [
        'topic_id',
        'user_id',
        'body',
        'secret',
        'status',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
