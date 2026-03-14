<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    protected $fillable = [
        'classroom_id',
        'quiz_id',
        'deadline_at',
        'is_randomized',
        'anti_cheat_mode',
    ];

    protected $casts = [
        'deadline_at' => 'datetime',
        'is_randomized' => 'boolean',
        'anti_cheat_mode' => 'boolean',
    ];

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }
}
