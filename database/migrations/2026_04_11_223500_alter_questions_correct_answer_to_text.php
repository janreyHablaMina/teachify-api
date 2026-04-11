<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('questions') || !Schema::hasColumn('questions', 'correct_answer')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE questions ALTER COLUMN correct_answer TYPE TEXT');
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE questions MODIFY correct_answer TEXT NULL');
            return;
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('questions') || !Schema::hasColumn('questions', 'correct_answer')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE questions ALTER COLUMN correct_answer TYPE VARCHAR(255)');
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE questions MODIFY correct_answer VARCHAR(255) NULL');
            return;
        }
    }
};
