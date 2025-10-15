<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Activity extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'date',
        'created_by',
        'description',
        'location',
        'required_members',
        'status',
    ];

    protected $casts = [
        'date' => 'datetime',
    ];

    /**
     * Get the user who created the activity.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all members of the activity.
     */
    public function members()
    {
        return $this->belongsToMany(User::class, 'activity_members')
            ->withPivot('status', 'notes')
            ->withTimestamps();
    }

    /**
     * Get all activity members (pivot records).
     */
    public function activityMembers()
    {
        return $this->hasMany(ActivityMember::class);
    }
}
