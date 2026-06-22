<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Http;

#[Fillable(['name', 'email', 'password', 'google_id', 'google_token', 'google_refresh_token', 'google_token_expires_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'google_token_expires_at' => 'datetime',
        ];
    }

    public function jobEvents(): HasMany
    {
        return $this->hasMany(JobEvent::class);
    }

    public function getValidGoogleToken(): string
    {
        if ($this->google_token_expires_at && $this->google_token_expires_at->subSeconds(60)->isFuture()) {
            return $this->google_token;
        }

        if (! $this->google_refresh_token) {
            throw new \Exception('No refresh token stored — reconnect your Google account at /auth/google.');
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'refresh_token',
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $this->google_refresh_token,
        ]);

        $data = $response->json();

        $this->update([
            'google_token' => $data['access_token'],
            'google_token_expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        return $data['access_token'];
    }
}