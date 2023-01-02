<?php

declare(strict_types=1);

namespace xorik\YtUpload\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Uid\Uuid;

class ProgressRepository
{
    private const PROGRESS_CACHE_PREFIX = 'progress-';
    private const PROGRESS_CACHE_TTL = 30 * 60 * 60;

    public function __construct(
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function setProgress(Uuid $videoId, float $value): void
    {
        $item = $this->cache->getItem(self::PROGRESS_CACHE_PREFIX . $videoId);
        $item->set($value);
        $item->expiresAfter(self::PROGRESS_CACHE_TTL);
        $this->cache->save($item);
    }

    public function getProgress(Uuid $videoId): ?float
    {
        $item = $this->cache->getItem(self::PROGRESS_CACHE_PREFIX . $videoId);

        return $item->get();
    }
}
