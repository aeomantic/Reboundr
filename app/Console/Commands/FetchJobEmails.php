<?php

namespace App\Console\Commands;

use App\Models\JobEvent;
use App\Models\User;
use App\Services\JobEmailExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchJobEmails extends Command
{
    protected $signature = 'gmail:fetch-job-emails';
    protected $description = 'Fetch recent Gmail messages, filter for job-related ones, and extract structured details with an LLM';

    protected array $jobKeywords = [
        'interview', 'application', 'applied', 'position', 'role at',
        'job offer', 'offer letter', 'hiring', 'recruiter', 'recruitment',
        'shortlisted', 'assessment', 'screening', 'candidacy', 'vacancy',
        'opportunity at', 'talent acquisition',
    ];

    public function handle()
    {
        $user = User::first();

        if (! $user) {
            $this->error('No user found. Log in via /auth/google first.');
            return;
        }

        $accessToken = $user->getValidGoogleToken();
        $extractor = new JobEmailExtractor();

        $this->info('Fetching recent messages from Gmail...');

        $listResponse = Http::withToken($accessToken)
            ->get('https://gmail.googleapis.com/gmail/v1/users/me/messages', [
                'q' => 'newer_than:30d',
                'maxResults' => 20,
            ]);

        if ($listResponse->failed()) {
            $this->error('Failed to list messages: ' . $listResponse->body());
            return;
        }

        $messages = $listResponse->json('messages') ?? [];

        if (empty($messages)) {
            $this->info('No recent messages found.');
            return;
        }

        $this->info('Found ' . count($messages) . ' recent messages. Checking each one...');

        $results = [];

        foreach ($messages as $message) {
            $metaResponse = Http::withToken($accessToken)
                ->get("https://gmail.googleapis.com/gmail/v1/users/me/messages/{$message['id']}", [
                    'format' => 'metadata',
                ]);

            if ($metaResponse->failed()) {
                continue;
            }

            $meta = $metaResponse->json();
            $headers = collect($meta['payload']['headers'] ?? []);

            $subject = $headers->firstWhere('name', 'Subject')['value'] ?? '(no subject)';
            $from = $headers->firstWhere('name', 'From')['value'] ?? '(unknown sender)';
            $snippet = $meta['snippet'] ?? '';

            $textToCheck = strtolower($subject . ' ' . $snippet);

            $passesKeywordFilter = false;
            foreach ($this->jobKeywords as $keyword) {
                if (str_contains($textToCheck, $keyword)) {
                    $passesKeywordFilter = true;
                    break;
                }
            }

            if (! $passesKeywordFilter) {
                continue;
            }

            $this->line("Keyword match: \"{$subject}\" — checking with AI...");

            $fullResponse = Http::withToken($accessToken)
                ->get("https://gmail.googleapis.com/gmail/v1/users/me/messages/{$message['id']}", [
                    'format' => 'full',
                ]);

            if ($fullResponse->failed()) {
                continue;
            }

            $full = $fullResponse->json();
            $body = $this->extractPlainTextBody($full['payload'] ?? []);
            $textForLlm = $body !== '' ? $body : $snippet;

            $analysis = $extractor->analyze("Subject: {$subject}\nFrom: {$from}\n\n{$textForLlm}");

            if (! $analysis || empty($analysis['is_job_related'])) {
                $this->line('  -> AI judged this as not personally job-related. Skipping.');
                continue;
            }

            // Save or update — keyed on user + Gmail message ID, so re-running
            // this command never creates duplicate rows for the same email.
            $jobEvent = JobEvent::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'gmail_message_id' => $message['id'],
                ],
                [
                    'subject' => $subject,
                    'company' => $analysis['company'] ?? null,
                    'role' => $analysis['role'] ?? null,
                    'email_type' => $analysis['email_type'] ?? null,
                    'event_datetime' => $analysis['event_datetime'] ?? null,
                    'location_type' => $analysis['location_type'] ?? null,
                    'location_detail' => $analysis['location_detail'] ?? null,
                    'summary' => $analysis['summary'] ?? null,
                ]
            );

            $results[] = [
                'subject' => $jobEvent->subject,
                'company' => $jobEvent->company ?? '-',
                'role' => $jobEvent->role ?? '-',
                'type' => $jobEvent->email_type ?? '-',
                'when' => $jobEvent->event_datetime?->format('Y-m-d H:i') ?? '-',
                'where' => $jobEvent->location_type ?? '-',
            ];
        }

        if (empty($results)) {
            $this->info('No genuinely job-related emails found in this batch.');
            return;
        }

        $this->info('Saved ' . count($results) . ' job-related email(s):');
        $this->table(['Subject', 'Company', 'Role', 'Type', 'When', 'Where'], $results);
    }

    protected function extractPlainTextBody(array $payload): string
    {
        if (($payload['mimeType'] ?? '') === 'text/plain' && isset($payload['body']['data'])) {
            return $this->decodeBase64Url($payload['body']['data']);
        }

        if (isset($payload['parts'])) {
            foreach ($payload['parts'] as $part) {
                if (($part['mimeType'] ?? '') === 'text/plain' && isset($part['body']['data'])) {
                    return $this->decodeBase64Url($part['body']['data']);
                }
            }

            foreach ($payload['parts'] as $part) {
                if (isset($part['parts'])) {
                    $nested = $this->extractPlainTextBody($part);
                    if ($nested !== '') {
                        return $nested;
                    }
                }
            }
        }

        return '';
    }

    protected function decodeBase64Url(string $data): string
    {
        $data = str_replace(['-', '_'], ['+', '/'], $data);
        return base64_decode($data) ?: '';
    }
}