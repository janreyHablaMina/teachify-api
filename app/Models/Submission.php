<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    protected $fillable = [
        'assignment_id',
        'user_id',
        'score',
        'answers',
        'is_graded',
        'teacher_feedback',
    ];

    protected $casts = [
        'score' => 'float',
        'answers' => 'array',
        'is_graded' => 'boolean',
    ];

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
