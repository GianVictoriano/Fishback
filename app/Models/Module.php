<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'module_level',
    ];

    /**
     * The users that are assigned to the module.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_module_assignments');
    }
}
