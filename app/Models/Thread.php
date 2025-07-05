<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Thread extends Model
{
    use HasFactory;

    protected $fillable = ['guest_name', 'title', 'content'];

    // 👇 Add this relationship method
    public function replies()
    {
        return $this->hasMany(Reply::class);
    }
}
