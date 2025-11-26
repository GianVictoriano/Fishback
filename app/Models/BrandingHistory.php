<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrandingHistory extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'changes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
