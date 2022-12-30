<?php

declare(strict_types=1);

namespace xorik\YtUpload\Service;

use Google\Client;
use Google\Http\MediaFileUpload;
use Google\Service\YouTube;
use Google\Service\YouTube\Video;
use Google\Service\YouTube\VideoSnippet;
use Google\Service\YouTube\VideoStatus;
use Psr\Http\Message\RequestInterface;
use xorik\YtUpload\Model\VideoDetails;

class YoutubeApi
{
    private const CHUNK_SIZE = 1024 * 1024;

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

    public function insertVideo(
        array $token,
        VideoDetails $details,
    ): RequestInterface {
        $this->client->setAccessToken($token);

        $youtube = new YouTube($this->client);

        $snippet = new VideoSnippet();
        $snippet->setChannelId((string) $details->category->value);
        $snippet->setDescription($details->description);
        $snippet->setTitle($details->title);
        $snippet->setTags($details->tags);

        $status = new VideoStatus();
        $status->setPrivacyStatus($details->privacyStatus->value);

        $video = new Video();
        $video->setSnippet($snippet);
        $video->setStatus($status);

        // TODO: set thumbnail & playlist & license

        $this->client->setDefer(true);

        return $youtube->videos->insert('status,snippet', $video);
    }

    public function uploadVideo(
        array $token,
        string $path,
        RequestInterface $insertRequest,
        callable $progressCallback,
        ?string $resumeUrl = null,
    ): void {
        $this->client->setAccessToken($token);

        $media = new MediaFileUpload(
            $this->client,
            $insertRequest,
            'video/*',
            null,
            true,
            self::CHUNK_SIZE,
        );
        $filesize = filesize($path);
        $media->setFileSize($filesize);

        $status = false;
        $handle = fopen($path, 'r');

        if ($resumeUrl !== null) {
            $media->resume($resumeUrl);
            fseek($handle, $media->getProgress());
        }

        while (!$status && !feof($handle)) {
            $chunk = fread($handle, self::CHUNK_SIZE);
            $status = $media->nextChunk($chunk);
            $progressCallback($media->getProgress(), $filesize, $media->getResumeUri());
        }
        fclose($handle);
    }
}
