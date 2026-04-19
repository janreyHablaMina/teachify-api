<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherNotification extends Model
{
    protected $fillable = [
        'user_id',
        'source_key',
        'title',
        'message',
        'category',
        'event_type',
        'severity',
        'occurred_at',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

