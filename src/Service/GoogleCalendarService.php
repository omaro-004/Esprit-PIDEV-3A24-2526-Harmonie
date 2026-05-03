<?php

namespace App\Service;

use App\Entity\Evenement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class GoogleCalendarService
{
    private ?\Google_Client $client = null;
    private EntityManagerInterface $em;
    private UrlGeneratorInterface $router;

    public function __construct(EntityManagerInterface $em, UrlGeneratorInterface $router)
    {
        $this->em = $em;
        $this->router = $router;

        if (class_exists(\Google_Client::class)) {
            $this->client = new \Google_Client();
            $clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? 'your_client_id';
            $clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? 'your_client_secret';

            $this->client->setClientId($clientId);
            $this->client->setClientSecret($clientSecret);
            $redirectUri = $this->router->generate('app_google_calendar_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
            $this->client->setRedirectUri($redirectUri);

            $this->client->setAccessType('offline');
            $this->client->setPrompt('consent');

            $this->client->addScope('https://www.googleapis.com/auth/calendar');
            $this->client->addScope('email');
            $this->client->addScope('profile');
        }
    }

    public function getAuthUrl(): string
    {
        if (!$this->client) return '#';
        return $this->client->createAuthUrl();
    }

    public function authenticate(string $code, \App\Entity\User $user): bool
    {
        if (!$this->client) return false;

        $token = $this->client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            return false;
        }

        $encodedToken = json_encode($token);
        $user->setGoogleAccessToken($encodedToken !== false ? $encodedToken : null);

        if (isset($token['refresh_token'])) {
            $user->setGoogleRefreshToken($token['refresh_token']);
        }

        if (isset($token['expires_in'])) {
            $expiresAt = new \DateTime();
            $expiresAt->modify('+' . $token['expires_in'] . ' seconds');
            $user->setGoogleTokenExpiresAt($expiresAt);
        }

        $this->em->persist($user);
        $this->em->flush();

        return true;
    }

    private function autoConfigForUser(?\App\Entity\User $user): bool
    {
        if (!$this->client || !$user || !$user->getGoogleAccessToken()) {
            return false;
        }

        $tokenData = json_decode($user->getGoogleAccessToken(), true);
        if (!$tokenData) return false;

        $this->client->setAccessToken($tokenData);

        if ($this->client->isAccessTokenExpired()) {
            $refreshToken = $user->getGoogleRefreshToken();
            if ($refreshToken) {
                $newTokenData = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
                if (!isset($newTokenData['error'])) {
                    $newTokenData['refresh_token'] = $refreshToken;
                    $encodedNewToken = json_encode($newTokenData);
                    $user->setGoogleAccessToken($encodedNewToken !== false ? $encodedNewToken : null);

                    if (isset($newTokenData['expires_in'])) {
                        $expiresAt = new \DateTime();
                        $expiresAt->modify('+' . $newTokenData['expires_in'] . ' seconds');
                        $user->setGoogleTokenExpiresAt($expiresAt);
                    }
                    $this->em->persist($user);
                    $this->em->flush();
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }
        return true;
    }

    public function syncEventToGoogle(Evenement $event): ?string
    {
        $user = $event->getProprietaire();
        if (!$user || !$this->autoConfigForUser($user)) {
            return null;
        }

        /** @var \Google_Client $client */
        $client = $this->client;
        $service = new \Google_Service_Calendar($client);

        $googleEvent = new \Google_Service_Calendar_Event([
            'summary' => $event->getTitre() ?? 'Sans titre',
            'location' => $event->getLieu() ?? $event->getLieuAdresse() ?? 'Non spécifié',
            'description' => $event->getDescription() ?? '',
            'start' => [
                'dateTime' => $event->getDateDebut() ? $event->getDateDebut()->format('c') : null,
                'timeZone' => 'Europe/Paris',
            ],
            'end' => [
                'dateTime' => $event->getDateFin() ? $event->getDateFin()->format('c') : null,
                'timeZone' => 'Europe/Paris',
            ],
        ]);

        try {
            if ($event->getGoogleEventId()) {
                $eventId = $event->getGoogleEventId();
                $updatedEvent = $service->events->update('primary', $eventId, $googleEvent);
                return $updatedEvent->getId();
            } else {
                $createdEvent = $service->events->insert('primary', $googleEvent);
                $event->setGoogleEventId($createdEvent->getId());
                $this->em->persist($event);
                $this->em->flush();
                return $createdEvent->getId();
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    public function deleteEventFromGoogle(Evenement $event): bool
    {
        $user = $event->getProprietaire();
        if (!$user || !$this->autoConfigForUser($user) || !$event->getGoogleEventId()) {
            return false;
        }

        /** @var \Google_Client $client */
        $client = $this->client;
        $service = new \Google_Service_Calendar($client);
        try {
            $service->events->delete('primary', $event->getGoogleEventId());
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function pullEventsFromGoogle(\App\Entity\User $user): bool
    {
        if (!$this->autoConfigForUser($user)) {
            return false;
        }

        /** @var \Google_Client $client */
        $client = $this->client;
        $service = new \Google_Service_Calendar($client);
        $optParams = [
            'maxResults' => 200,
            'orderBy' => 'startTime',
            'singleEvents' => true,
            'timeMin' => (new \DateTime('-1 month'))->format('c'),
        ];

        try {
            $results = $service->events->listEvents('primary', $optParams);
            $events = $results->getItems();

            $calendrier = $this->em->getRepository(\App\Entity\Calendrier::class)->findOneBy([]);

            foreach ($events as $gEvent) {
                if (!$gEvent->getId() || $gEvent->getStatus() === 'cancelled') {
                    continue;
                }

                $localEvent = $this->em->getRepository(Evenement::class)->findOneBy(['googleEventId' => $gEvent->getId()]);

                if (!$localEvent) {
                    $localEvent = new Evenement();
                    $localEvent->setGoogleEventId($gEvent->getId());
                    $localEvent->setProprietaire($user);
                    if ($calendrier) {
                        $localEvent->setCalendrier($calendrier);
                    }
                }

                $localEvent->setTitre($gEvent->getSummary() ?: 'Sans titre');
                $localEvent->setDescription($gEvent->getDescription() ?: null);
                $localEvent->setLieu($gEvent->getLocation() ?: null);
                $localEvent->setEventType('autre');
                $localEvent->setLieuType('presentiel');

                if ($gEvent->getStart() && $gEvent->getStart()->getDateTime()) {
                    $localEvent->setDateDebut(new \DateTime($gEvent->getStart()->getDateTime()));
                } elseif ($gEvent->getStart() && $gEvent->getStart()->getDate()) {
                    $localEvent->setDateDebut(new \DateTime($gEvent->getStart()->getDate() . ' 00:00:00'));
                }

                if ($gEvent->getEnd() && $gEvent->getEnd()->getDateTime()) {
                    $localEvent->setDateFin(new \DateTime($gEvent->getEnd()->getDateTime()));
                } elseif ($gEvent->getEnd() && $gEvent->getEnd()->getDate()) {
                    $localEvent->setDateFin(new \DateTime($gEvent->getEnd()->getDate() . ' 23:59:59'));
                }

                $this->em->persist($localEvent);
            }
            $this->em->flush();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
