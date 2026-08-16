<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail. Every security-relevant action writes one row here: who did it,
 * to what, from which IP and with which browser (EduSystem.md 1A).
 *
 * user_id is nullable because a failed login has no authenticated actor, and it
 * uses nullOnDelete so the trail survives the deletion of the account it names.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('target_type')->nullable();
            $table->integer('target_id')->nullable();
            $table->string('ip_address');
            $table->string('user_agent');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
