<?php

namespace App\Services\GoogleMeet;

use Carbon\Carbon;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\ConferenceData;
use Google\Service\Calendar\CreateConferenceRequest;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventAttendee;
use Google\Service\Calendar\EventDateTime;
use RuntimeException;

class GoogleMeetService
{
    public function createMeetLink(array $payload): array
    {
        $refreshToken = (string) config('services.google.refresh_token', '');

        if ($refreshToken === '') {
            throw new RuntimeException('Missing GOOGLE_REFRESH_TOKEN in environment.');
        }

        $client = $this->buildClient();
        $accessToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);

        if (isset($accessToken['error'])) {
            throw new RuntimeException((string) ($accessToken['error_description'] ?? $accessToken['error']));
        }

        $calendarService = new Calendar($client);

        $start = Carbon::parse((string) $payload['start_at'], (string) $payload['timezone']);
        $end = $start->copy()->addMinutes((int) $payload['duration_minutes']);

        $event = new Event([
            'summary' => (string) $payload['title'],
            'description' => $payload['description'] ?? null,
            'visibility' => 'public',
            'start' => new EventDateTime([
                'dateTime' => $start->toRfc3339String(),
                'timeZone' => (string) $payload['timezone'],
            ]),
            'end' => new EventDateTime([
                'dateTime' => $end->toRfc3339String(),
                'timeZone' => (string) $payload['timezone'],
            ]),
            'attendees' => collect($payload['attendees'] ?? [])
                ->map(fn (array $attendee) => new EventAttendee(['email' => $attendee['email']]))
                ->values()
                ->all(),
            'conferenceData' => new ConferenceData([
                'createRequest' => new CreateConferenceRequest([
                    'requestId' => 'meet-'.uniqid('', true),
                ]),
            ]),
        ]);

        $calendarId = (string) config('services.google.calendar_id', 'primary');
        $createdEvent = $calendarService->events->insert($calendarId, $event, [
            'conferenceDataVersion' => 1,
            'sendUpdates' => 'all',
        ]);

        return [
            'event_id' => $createdEvent->getId(),
            'calendar_link' => $createdEvent->getHtmlLink(),
            'meet_link' => $createdEvent->getHangoutLink(),
        ];
    }

    private function buildClient(): Client
    {
        $client = new Client();
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setRedirectUri((string) config('services.google.redirect_uri'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(true);
        $client->setScopes([
            Calendar::CALENDAR,
        ]);

        return $client;
    }
}
