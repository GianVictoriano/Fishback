<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Broadcast extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'activity_date',
        'activity_location',
        'required_writers',
        'required_photographers',
        'total_required_members',
        'status',
        'sender_id',
        'activity_id',
        'sent_at',
        'total_recipients',
        'accepted_count',
        'declined_count',
        'pending_count',
    ];

    protected $casts = [
        'activity_date' => 'datetime',
        'sent_at' => 'datetime',
        'required_writers' => 'integer',
        'required_photographers' => 'integer',
        'total_required_members' => 'integer',
        'total_recipients' => 'integer',
        'accepted_count' => 'integer',
        'declined_count' => 'integer',
        'pending_count' => 'integer',
    ];

    /**
     * Get the user who sent the broadcast.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get the associated activity (if any).
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * Get the recipients of this broadcast.
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(BroadcastRecipient::class);
    }

    /**
     * Get recipients who have accepted.
     */
    public function acceptedRecipients(): HasMany
    {
        return $this->recipients()->where('response_status', 'accepted');
    }

    /**
     * Get recipients who have declined.
     */
    public function declinedRecipients(): HasMany
    {
        return $this->recipients()->where('response_status', 'declined');
    }

    /**
     * Get recipients who are still pending.
     */
    public function pendingRecipients(): HasMany
    {
        return $this->recipients()->where('response_status', 'pending');
    }

    /**
     * Update the response counts.
     */
    public function updateResponseCounts(): void
    {
        $this->update([
            'accepted_count' => $this->acceptedRecipients()->count(),
            'declined_count' => $this->declinedRecipients()->count(),
            'pending_count' => $this->pendingRecipients()->count(),
        ]);
    }

    /**
     * Mark the broadcast as sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark the broadcast as failed.
     */
    public function markAsFailed(): void
    {
        $this->update([
            'status' => 'failed',
        ]);
    }
}
