<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Summary extends Model
{
    protected $fillable = ['user_id', 'topic', 'content', 'pdf_path'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
