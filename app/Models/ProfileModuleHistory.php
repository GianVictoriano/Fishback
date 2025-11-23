<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileModuleHistory extends Model
{
    protected $table = 'profile_module_history';

    protected $fillable = [
        'profile_id',
        'module_id',
        'action',
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}
