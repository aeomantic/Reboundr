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
            // Google's ID for this user — lets us find existing users on repeat logins
            $table->string('google_id')->nullable()->unique();

            // Short-lived token (expires in ~1 hour) — used to make Gmail API calls right now
            $table->text('google_token')->nullable();

            // Long-lived token — used to get a fresh access token without asking the user to reconnect
            $table->text('google_refresh_token')->nullable();

            // When the access token expires — so we know when to refresh it
            $table->timestamp('google_token_expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'google_token', 'google_refresh_token', 'google_token_expires_at']);
        });
    }
};
