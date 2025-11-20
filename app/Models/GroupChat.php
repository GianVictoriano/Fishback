<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupChat extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'scrum_board_id',
    ];

    /**
     * Get the scrum board that owns the group chat.
     */
    public function scrumBoard()
    {
        return $this->belongsTo(ScrumBoard::class);
    }

    /**
     * The members that belong to the group chat.
     */
    public function members()
    {
        return $this->belongsToMany(User::class, 'group_chat_members', 'group_chat_id', 'user_id');
    }

    /**
     * Get the folio associated with this group chat.
     */
    public function folio()
    {
        return $this->hasOne(Folio::class, 'group_chat_id');
    }
}
