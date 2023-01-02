<?php

declare(strict_types=1);

namespace xorik\YtUpload\Service;

use xorik\YtUpload\Model\VideoTimestamp;

class CliProgressParser
{
    private const PARTIAL_PROGRESS_REGEXP = '/time=(\d\d:\d\d:\d\d)/';
    private const FULL_PROGRESS_REGEXP = '/(\d+\.\d+)%\s+of\s+(~?\d+\.\d+\w+)\s+at\s+([\w\.\/]+)\s+ETA\s+([\d:]+)/';

    public function getProgressForPartialDownload(int $totalLength, string $output): ?float
    {
        // Get current time, convert to seconds and compare with total length
        if (preg_match(self::PARTIAL_PROGRESS_REGEXP, $output, $m) === 0) {
            return null;
        }

        $progress = new VideoTimestamp($m[1]);

        return $progress->toSeconds() / $totalLength * 100;
    }

    public function getProgressForFullDownload(string $output): ?float
    {
        if (preg_match(self::FULL_PROGRESS_REGEXP, $output, $m) > 0) {
            return (float) $m[1];
        }

        return null;
    }
}
