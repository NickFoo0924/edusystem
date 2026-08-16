<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The verifiable credential itself (EduSystem.md 1C).
 *
 * credential_id is the human-readable public identifier, format
 * LS-{YEAR}-{8 CHAR BASE32}, and it is unique across the whole system because
 * the CredentialAuthority Singleton is its sole issuer.
 *
 * integrity_hash stores SHA-256(student_id|course_id|score|issued_at|credential_id);
 * the public verification page recomputes it to prove the row was not tampered with.
 *
 * Exactly one of course_id / learning_path_id is set: a course certificate or a
 * pathway certificate. That rule is enforced by the CredentialAuthority, since a
 * database CHECK constraint would not be portable across MariaDB and MySQL.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('learning_path_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('certificate_template_id')->constrained();
            $table->string('credential_id')->unique();
            $table->double('final_score');
            $table->string('integrity_hash');
            $table->string('pdf_path');
            $table->dateTime('issued_at');
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->string('revocation_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
