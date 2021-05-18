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

    public function getCalendar()
    {
        $service = new Google_Service_Calendar($this->client);

        $calendarList = $service->calendarList->listCalendarList();

        $calendars = array();
        while(true) {
            foreach ($calendarList->getItems() as $calendarListEntry) {
                $calendars[] = $calendarListEntry;
            }
            $pageToken = $calendarList->getNextPageToken();
            if ($pageToken) {
                $calendarList = $service->calendarList->listCalendarList(array('pageToken' => $pageToken));
            } else {
                break;
            }
        }

        return $calendars;
    }

    public function createCalendar($name)
    {
        $service = new Google_Service_Calendar($this->client);

        $calendar = new Google_Service_Calendar_Calendar();
        $calendar->setSummary($name);
        $calendar->setTimeZone('Asia/Bangkok');

        $createdCalendar = $service->calendars->insert($calendar);

        return $createdCalendar->getId();
    }

    public function deleteCalendar($id)
    {
        $service = new Google_Service_Calendar($this->client);

        $service->calendars->delete($id);
    }

    public function getEvent($id, $start = null, $end = null)
    {
        $params = array();
        if($start != null){
            $start = new \DateTime($start);
            $params['timeMin'] = $start->format(\DateTime::RFC3339);
        }
        if($end != null){
            $end = new \DateTime($end);
            $params['timeMax'] = $end->format(\DateTime::RFC3339);
        }

        $service = new Google_Service_Calendar($this->client);

        $eventList = $service->events->listEvents($id, $params);

        $events = array();
        while(true) {
            foreach ($eventList->getItems() as $eventListEntry) {
                $events[] = $eventListEntry;
            }
            $pageToken = $eventList->getNextPageToken();
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
                $eventList = $service->events->listEvents($id, $params);
            } else {
                break;
            }
        }

        return $events;
    }

    public function createEvent($calendarId, $name, $location, $description, $start, $end, $attendees)
    {
        $start = new \DateTime($start);
        $end = new \DateTime($end);

        $service = new Google_Service_Calendar($this->client);

        $event = new Google_Service_Calendar_Event(array(
            'summary' => $name,
            'location' => $location,
            'description' => $description,
            'start' => array(
                'dateTime' => $start->format(\DateTime::RFC3339),
                'timeZone' => 'Asia/Bangkok',
            ),
            'end' => array(
                'dateTime' => $end->format(\DateTime::RFC3339),
                'timeZone' => 'Asia/Bangkok',
            ),
            'attendees' => $attendees,
            'reminders' => array(
                'useDefault' => false,
                'overrides' => array(
                    array('method' => 'email', 'minutes' => 24 * 60),
                    array('method' => 'popup', 'minutes' => 10),
                ),
            ),
        ));

        $event = $service->events->insert($calendarId, $event);

        return $event->id;
    }

    public function deleteEvent($calendarId, $id)
    {
        $service = new Google_Service_Calendar($this->client);

        $service->events->delete($calendarId, $id);
    }
}
