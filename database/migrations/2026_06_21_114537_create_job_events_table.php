<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gmail_message_id');
            $table->string('subject')->nullable();
            $table->string('company')->nullable();
            $table->string('role')->nullable();
            $table->string('email_type')->nullable();
            $table->dateTime('event_datetime')->nullable();
            $table->string('location_type')->nullable();
            $table->string('location_detail')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();
    
            // One row per (user, email) — re-running the fetch command updates
            // the existing row instead of creating a duplicate.
            $table->unique(['user_id', 'gmail_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_events');
    }
};
