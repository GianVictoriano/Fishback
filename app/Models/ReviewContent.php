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
        'status',
        'uploaded_at',
        'no_of_approval',
    ];

    public $timestamps = false;

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function group()
    {
        return $this->belongsTo(GroupChat::class, 'group_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
