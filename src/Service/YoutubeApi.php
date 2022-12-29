<?php

declare(strict_types=1);

namespace xorik\YtUpload\Service;

use Google\Client;
use Google\Service\YouTube;

class YoutubeApi
{
    private Client $client;

    public function __construct(
        string $clientId,
        string $clientSecret,
        string $redirectUrl,
    ) {
        $this->client = new Client([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUrl,
            'scopes' => [YouTube::YOUTUBE_UPLOAD],
            'access_type' => 'offline',
        ]);
    }

    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    public function auth(string $code): array
    {
        return $this->client->fetchAccessTokenWithAuthCode($code);
    }

    public function refreshToken(array $token): ?array
    {
        $this->client->setAccessToken($token);

        if (!$this->client->isAccessTokenExpired()) {
            return null;
        }

        return $this->client->refreshToken($this->client->getRefreshToken());
    }
}
