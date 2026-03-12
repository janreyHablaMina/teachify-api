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
            $table->enum('plan', ['free', 'pro'])->default('free')->after('role');
            $table->unsignedTinyInteger('quiz_generation_limit')->default(3)->after('plan');
            $table->unsignedTinyInteger('quizzes_used')->default(0)->after('quiz_generation_limit');
            $table->unsignedTinyInteger('max_questions_per_quiz')->default(10)->after('quizzes_used');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'plan',
                'quiz_generation_limit',
                'quizzes_used',
                'max_questions_per_quiz',
            ]);
        });
    }
};
