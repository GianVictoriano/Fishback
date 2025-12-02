<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreativeReaction extends Model
{
    use HasFactory;

    protected $table = 'creative_reactions';

    protected $fillable = [
        'user_id',
        'creative_id',
        'reaction_type',
        'ip_address',
    ];

    // Relationships
    public function creative(): BelongsTo
    {
        return $this->belongsTo(Creative::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
