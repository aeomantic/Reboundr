<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobEvent extends Model
{
    protected $fillable = [
        'user_id',
        'gmail_message_id',
        'subject',
        'company',
        'role',
        'email_type',
        'event_datetime',
        'location_type',
        'location_detail',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'event_datetime' => 'datetime',
        ];
    }

    /**
     * Every job event belongs to exactly one user. This lets you write
     * $jobEvent->user to find out whose record it is — and, more usefully,
     * sets up the reverse lookup (a user's list of job events) that the
     * dashboard will use next.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}