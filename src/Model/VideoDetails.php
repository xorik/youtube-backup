<?php

declare(strict_types=1);

namespace xorik\YtUpload\Model;

class VideoDetails
{
    public function __construct(
        readonly public string $title,
        readonly public string $description,
        readonly public array $tags,
        readonly public YoutubeCategory $category,
        readonly public PrivacyStatus $privacyStatus,
    ) {
    }
}
