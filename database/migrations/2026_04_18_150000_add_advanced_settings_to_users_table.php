<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Identity & Personalization
            $table->string('display_name')->nullable()->after('fullname');
            $table->text('bio')->nullable()->after('display_name');
            $table->string('school')->nullable()->after('bio');
            $table->json('subjects')->nullable()->after('school');
            $table->string('teaching_level')->nullable()->after('subjects');
            $table->string('profile_photo_path', 2048)->nullable()->after('teaching_level');

            // AI Preferences
            $table->string('ai_default_difficulty')->default('medium')->after('profile_photo_path');
            $table->string('ai_default_question_type')->default('mixed')->after('ai_default_difficulty');
            $table->string('ai_language')->default('English')->after('ai_default_question_type');
            $table->string('ai_tone')->default('Formal')->after('ai_language');
            $table->boolean('ai_generate_explanations')->default(true)->after('ai_tone');
            $table->boolean('ai_include_rationale')->default(true)->after('ai_generate_explanations');

            // Notification Settings
            $table->boolean('notify_email')->default(true)->after('ai_include_rationale');
            $table->boolean('notify_quiz_completed')->default(true)->after('notify_email');
            $table->boolean('notify_student_submission')->default(true)->after('notify_quiz_completed');
            $table->boolean('notify_weekly_summary')->default(true)->after('notify_student_submission');

            // UI Preferences
            $table->string('ui_theme')->default('light')->after('notify_weekly_summary');
            $table->string('ui_accent_color')->default('#0f172a')->after('ui_theme');
            $table->string('ui_density')->default('comfortable')->after('ui_accent_color');

            // Security
            $table->boolean('two_factor_enabled')->default(false)->after('ui_density');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'display_name', 'bio', 'school', 'subjects', 'teaching_level', 'profile_photo_path',
                'ai_default_difficulty', 'ai_default_question_type', 'ai_language', 'ai_tone',
                'ai_generate_explanations', 'ai_include_rationale',
                'notify_email', 'notify_quiz_completed', 'notify_student_submission', 'notify_weekly_summary',
                'ui_theme', 'ui_accent_color', 'ui_density',
                'two_factor_enabled'
            ]);
        });
    }
};
