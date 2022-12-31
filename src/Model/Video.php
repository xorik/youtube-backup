<?php

declare(strict_types=1);

namespace xorik\YtUpload\Model;

use Symfony\Component\Uid\Uuid;

class Video
{
    public function __construct(
        readonly public Uuid $id,
        readonly public string $sourceUrl,
        readonly public VideoDetails $videoDetails,
        readonly public ?VideoRange $range,
        readonly public VideoState $state = VideoState::QUEUED,
        readonly public ?string $downloadedPath = null,
        readonly public ?UploadState $uploadState = null,
        readonly public ?string $videoId = null,
        readonly public ?string $errorDetails = null,
    ) {
    }

    public function download(string $downloadedPath): self
    {
        return new self(
            $this->id,
            $this->sourceUrl,
            $this->videoDetails,
            $this->range,
            VideoState::DOWNLOADING,
            $downloadedPath,
        );
    }

    public function downloaded(): self
    {
        return new self(
            $this->id,
            $this->sourceUrl,
            $this->videoDetails,
            $this->range,
            VideoState::DOWNLOADED,
            $this->downloadedPath,
        );
    }

    public function uploading(UploadState $uploadState): self
    {
        return new self(
            $this->id,
            $this->sourceUrl,
            $this->videoDetails,
            $this->range,
            VideoState::UPLOADING,
            $this->downloadedPath,
            $uploadState,
        );
    }

    public function uploaded(string $videoId): self
    {
        return new self(
            $this->id,
            $this->sourceUrl,
            $this->videoDetails,
            $this->range,
            VideoState::UPLOADED,
            null,
            null,
            $videoId,
        );
    }

    public function prepared()
    {
        return new self(
            $this->id,
            $this->sourceUrl,
            $this->videoDetails,
            $this->range,
            VideoState::PREPARED,
            null,
            null,
            $this->videoId,
        );
    }

    public function publish(): self
    {
        return new self(
            $this->id,
            $this->sourceUrl,
            $this->videoDetails,
            $this->range,
            VideoState::PUBLISHED,
            null,
            null,
            $this->videoId,
        );
    }
}
