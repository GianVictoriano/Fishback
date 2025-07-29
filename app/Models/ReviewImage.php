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
        'status',
        'no_of_approval',
        'uploaded_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
