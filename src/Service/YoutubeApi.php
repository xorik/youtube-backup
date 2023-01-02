<?php

declare(strict_types=1);

namespace xorik\YtUpload\Service;

use Google\Client;
use Google\Http\MediaFileUpload;
use Google\Service\YouTube;
use Google\Service\YouTube\PlaylistItem;
use Google\Service\YouTube\PlaylistItemSnippet;
use Google\Service\YouTube\ResourceId;
use Google\Service\YouTube\Video;
use Google\Service\YouTube\VideoSnippet;
use Google\Service\YouTube\VideoStatus;
use Psr\Http\Message\RequestInterface;
use xorik\YtUpload\Model\PrivacyStatus;
use xorik\YtUpload\Model\VideoDetails;

class YoutubeApi
{
    private const CHUNK_SIZE = 10 * 1024 * 1024;

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
            'scopes' => [YouTube::YOUTUBE],
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

        // Set private until processing is over
        $status = new VideoStatus();
        $status->setPrivacyStatus(PrivacyStatus::PRIVATE->value);
        $status->setLicense($details->youtubeLicense ? 'youtube' : 'creativeCommon');

        $video = new Video();
        $video->setSnippet($snippet);
        $video->setStatus($status);

        $this->client->setDefer(true);

        /** @var RequestInterface $request */
        $request = $youtube->videos->insert('status,snippet', $video);
        $this->client->setDefer(false);

        return $request;
    }

    public function uploadVideo(
        array $token,
        string $path,
        RequestInterface $insertRequest,
        callable $progressCallback,
        ?string $resumeUrl = null,
    ): string {
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

        /** @var Video|false $status */
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

        return $status->getId();
    }

    public function hasProcessingCompleted(array $token, string $videoId): bool
    {
        $this->client->setAccessToken($token);
        $youtube = new YouTube($this->client);

        $videos = $youtube->videos->listVideos('processingDetails,snippet,status', ['id' => $videoId])->getItems();
        if (\count($videos) === 0) {
            throw new \RuntimeException('Video is not found: ' . $videoId);
        }

        $processingStatus = $videos[0]->getProcessingDetails()->getProcessingStatus();
        $hdProcessingFinished = $videos[0]->getSnippet()->getThumbnails()->getMaxres() !== null;

        if ($processingStatus === 'succeeded' && $hdProcessingFinished) {
            return true;
        }

        return false;
    }

    public function updatePrivacyStatus(array $token, string $videoId, PrivacyStatus $privacyStatus): void
    {
        $this->client->setAccessToken($token);
        $youtube = new YouTube($this->client);

        $video = new Video();
        $video->setId($videoId);

        $videoStatus = new VideoStatus();
        $videoStatus->setPrivacyStatus($privacyStatus->value);
        $video->setStatus($videoStatus);

        $youtube->videos->update('status', $video);
    }

    public function updateThumbnail(array $token, string $videoId, string $thumbnailPath): void
    {
        $this->client->setAccessToken($token);
        $youtube = new YouTube($this->client);

        $this->client->setDefer(true);
        /** @var RequestInterface $request */
        $request = $youtube->thumbnails->set($videoId);
        $this->client->setDefer(false);

        $media = new MediaFileUpload(
            $this->client,
            $request,
            mime_content_type($thumbnailPath),
            file_get_contents($thumbnailPath)
        );
        $this->client->execute($media->getRequest());
    }

    public function addToPlaylist(array $token, string $videoId, string $playlistId): void
    {
        $this->client->setAccessToken($token);
        $youtube = new YouTube($this->client);

        $playlistItem = new PlaylistItem();
        $snippet = new PlaylistItemSnippet();
        $snippet->setPlaylistId($playlistId);

        $resourceId = new ResourceId();
        $resourceId->setKind('youtube#video');
        $resourceId->setVideoId($videoId);
        $snippet->setResourceId($resourceId);

        $playlistItem->setSnippet($snippet);
        $youtube->playlistItems->insert('snippet', $playlistItem);
    }
}
