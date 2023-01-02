<?php

declare(strict_types=1);

namespace xorik\YtUpload\Model;

class VideoTimestamp
{
    public function __construct(public readonly string $timestamp)
    {
        if (!preg_match('/^\d+:\d{2}:\d{2}$/', $timestamp)) {
            throw new \InvalidArgumentException('Invalid timestamp format');
        }
    }

    public function toSeconds(): int
    {
        [$hour, $minute, $second] = explode(':', $this->timestamp);

        return (int) $hour * 3600 + (int) $minute * 60 + (int) $second;
    }
}
