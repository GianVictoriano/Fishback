<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'broadcast_id',
        'user_id',
        'response_status',
        'response_message',
        'responded_at',
        'availability_type',
        'availability_times',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    /**
     * Get the broadcast this recipient belongs to.
     */
    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    /**
     * Get the user who is the recipient.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Accept the broadcast.
     */
    public function accept(?string $message = null): void
    {
        $this->update([
            'response_status' => 'accepted',
            'response_message' => $message,
            'responded_at' => now(),
        ]);

        $this->broadcast->updateResponseCounts();
    }

    /**
     * Decline the broadcast.
     */
    public function decline(?string $message = null): void
    {
        $this->update([
            'response_status' => 'declined',
            'response_message' => $message,
            'responded_at' => now(),
        ]);

        $this->broadcast->updateResponseCounts();
    }
}
