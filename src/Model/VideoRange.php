<?php

declare(strict_types=1);

namespace xorik\YtUpload\Model;

class VideoRange
{
    public function __construct(
        readonly public VideoTimestamp $start,
        readonly public VideoTimestamp $end,
    ) {
    }

    public function __toString(): string
    {
        return $this->start->timestamp . '-' . $this->end->timestamp;
    }
}
