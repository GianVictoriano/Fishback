<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Module;
use App\Models\User;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role',
        'level',
        'position',
        'avatar',
        'name',
        'program',
        'section',
        'description',
        'is_anonymous',
        'anonymous_name',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The modules that belong to the profile.
     */
    public function modules()
    {
        return $this->belongsToMany(Module::class, 'profile_module');
    }
}
