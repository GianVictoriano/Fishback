<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'display_name'];

    /**
     * The profiles that belong to the module.
     */
    public function profiles()
    {
        return $this->belongsToMany(Profile::class);
    }
}
