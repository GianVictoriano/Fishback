<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Folio extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'theme',
        'lead_organizer_id',
        'is_journalists_only',
        'status',
        'start_date',
        'end_date',
        'group_chat_id',
    ];

    protected $casts = [
        'is_journalists_only' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Get the lead organizer of the folio
     */
    public function leadOrganizer()
    {
        return $this->belongsTo(User::class, 'lead_organizer_id');
    }

    /**
     * Get all members of the folio
     */
    public function members()
    {
        return $this->belongsToMany(User::class, 'folio_members')->withTimestamps();
    }

    /**
     * Get all submissions for this folio
     */
    public function submissions()
    {
        return $this->hasMany(FolioSubmission::class);
    }

    /**
     * Get the group chat associated with this folio
     */
    public function groupChat()
    {
        return $this->belongsTo(GroupChat::class);
    }

    /**
     * Check if a user is a member of this folio
     */
    public function hasMember($userId)
    {
        return $this->members()->where('user_id', $userId)->exists();
    }

    /**
     * Check if a user is the lead organizer
     */
    public function isLeadOrganizer($userId)
    {
        return $this->lead_organizer_id == $userId;
    }

    /**
     * Check if a user can submit to this folio
     */
    public function canUserSubmit($user)
    {
        // If journalists only, check if user is a collaborator
        if ($this->is_journalists_only) {
            return $user->profile && $user->profile->role === 'collaborator';
        }
        
        // Otherwise, anyone can submit
        return true;
    }
}
