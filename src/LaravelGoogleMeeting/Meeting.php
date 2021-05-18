<?php

namespace LaravelGoogleMeeting;

use Exception;
use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Calendar;
use Google_Service_Calendar_Event;

class Meeting
{
    public $client;

    public function __construct($applicationName, $redirectUri, $credentialPath, $tokenPath)
    {
        $client = new Google_Client();

        $client->setApplicationName($applicationName);
        $client->setRedirectUri($redirectUri);

        $client->setScopes(Google_Service_Calendar::CALENDAR);
        $client->setAuthConfig($credentialPath);
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        // Load previously authorized token from a file, if it exists.
        // The file token.json stores the user's access and refresh tokens, and is
        // created automatically when the authorization flow completes for the first
        // time.
        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $client->setAccessToken($accessToken);
        }

        // If there is no previous token or it's expired.
        if ($client->isAccessTokenExpired()) {
            // Refresh the token if possible, else fetch a new one.
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            } else {
                // Request authorization from the user.
                $authUrl = $client->createAuthUrl();
                printf("Open the following link in your browser:\n%s\n", $authUrl);
                print 'Enter verification code: ';
                $authCode = trim(fgets(STDIN));

                // Exchange authorization code for an access token.
                $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
                $client->setAccessToken($accessToken);

                // Check to see if there was an error.
                if (array_key_exists('error', $accessToken)) {
                    throw new Exception(join(', ', $accessToken));
                }
            }
            // Save the token to a file.
            if (!file_exists(dirname($tokenPath))) {
                mkdir(dirname($tokenPath), 0700, true);
            }
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        }
        $this->client = $client;
    }

    public function createMeeting($calendarId, $name, $start, $end, $meetingId)
    {
        $start = new \DateTime($start);
        $end = new \DateTime($end);

        $service = new Google_Service_Calendar($this->client);

        $event = new Google_Service_Calendar_Event(array(
            'summary' => $name,
            'start' => array(
                'dateTime' => $start->format(\DateTime::RFC3339),
                'timeZone' => 'Asia/Bangkok',
            ),
            'end' => array(
                'dateTime' => $end->format(\DateTime::RFC3339),
                'timeZone' => 'Asia/Bangkok',
            ),
        ));

        $event = $service->events->insert($calendarId, $event);

        $conference = new \Google_Service_Calendar_ConferenceData();
        $conferenceRequest = new \Google_Service_Calendar_CreateConferenceRequest();
        $conferenceRequest->setRequestId($meetingId);
        $conference->setCreateRequest($conferenceRequest);
        $event->setConferenceData($conference);

        $event = $service->events->patch($calendarId, $event->id, $event, ['conferenceDataVersion' => 1]);

        return $event;
    }
}
