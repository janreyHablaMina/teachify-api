<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = ['quiz_id', 'type', 'question_text', 'options', 'correct_answer', 'explanation'];

    protected $casts = [
        'options' => 'array',
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }
}
