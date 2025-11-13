<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportantNote extends Model
{
    use HasFactory;

    protected $table = 'important_notes';

    protected $fillable = [
        'group_chat_id',
        'user_id',
        'content',
        'is_active',
        'version',
        'versionable_type',
        'versionable_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'version' => 'decimal:1',
    ];

    public function groupChat()
    {
        return $this->belongsTo(GroupChat::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function versionable()
    {
        return $this->morphTo();
    }
}
