<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class JobEmailExtractor
{
    public function analyze(string $emailText): ?array
    {
        $systemPrompt = <<<PROMPT
You are an assistant that reads emails and determines whether they are genuinely
about the reader's own job application or interview process — NOT general job
board alerts, newsletters, or marketing emails that merely mention jobs.

Respond with ONLY a JSON object in this exact shape:
{
  "is_job_related": true or false,
  "company": "company name, or null if unknown",
  "role": "job title, or null if unknown",
  "email_type": "interview_invite, application_confirmation, offer, rejection, assessment_request, or other",
  "event_datetime": "ISO 8601 datetime if a specific date/time is mentioned, otherwise null",
  "location_type": "online, onsite, or unspecified",
  "location_detail": "address, meeting link, or platform name, or null",
  "summary": "one short sentence summarizing the email"
}

If the email is not genuinely about the reader's own application or interview,
set is_job_related to false and leave the other fields null.
PROMPT;

        $response = Http::withToken(config('services.groq.api_key'))
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => 'llama-3.3-70b-versatile',
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0, // deterministic — we want consistent judgments, not creativity
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $emailText],
                ],
            ]);

        if ($response->failed()) {
            return null;
        }

        // Quirk worth knowing: the API response is JSON, but the actual field
        // holding OUR data ("content") is itself a JSON-formatted STRING, not
        // a ready-made object. So we have to decode it a second time, ourselves.
        $rawContent = $response->json('choices.0.message.content');
        $data = json_decode($rawContent, true);

        // If the model didn't return valid JSON despite our instructions, fail gracefully
        if (! is_array($data)) {
            return null;
        }

        return $data;
    }
}