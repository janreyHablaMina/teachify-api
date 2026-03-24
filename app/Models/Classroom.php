<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'room',
        'schedule',
        'join_code',
        'is_active',
        'invite_expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'invite_expires_at' => 'datetime',
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'classroom_student')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }
}
