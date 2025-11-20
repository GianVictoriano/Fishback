<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\GroupChat;

class ScrumBoard extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'category',
        'deadline',
        'lead_reviewer_id',
        'created_by',
    ];

    /**
     * Get the group chat associated with the scrum board.
     */
    public function groupChat()
    {
        return $this->hasOne(GroupChat::class);
    }

    /**
     * Get the lead reviewer for the scrum board.
     */
    public function leadReviewer()
    {
        return $this->belongsTo(User::class, 'lead_reviewer_id');
    }

    /**
     * Get all of the members for the scrum board.
     */
    public function members()
    {
        return $this->hasManyThrough(
            User::class,
            GroupChat::class,
            'scrum_board_id', // Foreign key on the group_chats table...
            'id', // Foreign key on the users table...
            'id', // Local key on the scrum_boards table...
            'id' // Local key on the group_chats table...
        )->join('group_chat_members', 'group_chat_members.group_chat_id', '=', 'group_chats.id')
         ->where('group_chat_members.user_id', '=', 'users.id');
    }
}
