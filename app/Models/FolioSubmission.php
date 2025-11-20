<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FolioSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'folio_id',
        'user_id',
        'title',
        'content',
        'type',
        'status',
        'feedback',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Get the folio this submission belongs to
     */
    public function folio()
    {
        return $this->belongsTo(Folio::class);
    }

    /**
     * Get the user who submitted this
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the reviewer
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
