<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('classroom_student', function (Blueprint $table) {
            $table->string('status')->default('approved')->after('user_id');
        });

        DB::table('classroom_student')
            ->whereNull('status')
            ->update(['status' => 'approved']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classroom_student', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
