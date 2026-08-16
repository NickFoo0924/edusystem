<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [STRETCH] EduSystem.md Section 3 #7 -- email OTP two-factor authentication.
 *
 * The code is stored hashed, never in clear text, so a database leak does not
 * hand over live second factors. Email only: Section 5 rules out SMS and
 * authenticator apps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->dateTime('expires_at');
            $table->dateTime('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
