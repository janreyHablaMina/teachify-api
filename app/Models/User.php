<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'fullname',
        'email',
        'password',
        'role',
        'plan',
        'quiz_generation_limit',
        'quizzes_used',
        'max_questions_per_quiz',
        // Advanced Profile
        'display_name',
        'bio',
        'school',
        'subjects',
        'teaching_level',
        'profile_photo_path',
        // AI Preferences
        'ai_default_difficulty',
        'ai_default_question_type',
        'ai_language',
        'ai_tone',
        'ai_generate_explanations',
        'ai_include_rationale',
        // Notifications
        'notify_email',
        'notify_quiz_completed',
        'notify_student_submission',
        'notify_weekly_summary',
        // UI
        'ui_theme',
        'ui_accent_color',
        'ui_density',
        'two_factor_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'quiz_generation_limit' => 'integer',
            'quizzes_used' => 'integer',
            'max_questions_per_quiz' => 'integer',
            'subjects' => 'array',
            'ai_generate_explanations' => 'boolean',
            'ai_include_rationale' => 'boolean',
            'notify_email' => 'boolean',
            'notify_quiz_completed' => 'boolean',
            'notify_student_submission' => 'boolean',
            'notify_weekly_summary' => 'boolean',
            'two_factor_enabled' => 'boolean',
        ];
    }

    public function summaries()
    {
        return $this->hasMany(Summary::class);
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }

    public function classrooms()
    {
        return $this->hasMany(Classroom::class);
    }

    public function enrolledClassrooms()
    {
        return $this->belongsToMany(Classroom::class, 'classroom_student')
            ->withPivot('status')
            ->withTimestamps();
    }
}
