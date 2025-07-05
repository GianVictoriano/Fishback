<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reply extends Model
{
    use HasFactory;

    protected $fillable = ['guest_name', 'content'];

    // 👇 Add this relationship method
    public function thread()
    {
        return $this->belongsTo(Thread::class);
    }
}
