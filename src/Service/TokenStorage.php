<?php

declare(strict_types=1);

namespace xorik\YtUpload\Service;

use Psr\Cache\CacheItemPoolInterface;

class TokenStorage
{
    private const TOKEN_CACHE_PATH = 'token';

    public function __construct(
        private YoutubeApi $youtubeApi,
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function save(array $token): void
    {
        $cacheItem = $this->cache->getItem(self::TOKEN_CACHE_PATH);
        $this->cache->save($cacheItem->set($token));
    }

    public function getToken(): array
    {
        $cacheItem = $this->cache->getItem(self::TOKEN_CACHE_PATH);

        if (!$cacheItem->isHit()) {
            throw new \RuntimeException('Please run yt:auth to create a token');
        }

        $token = $cacheItem->get();
        $newToken = $this->youtubeApi->refreshToken($token);

        if ($newToken === null) {
            return $token;
        }

        $this->cache->save($cacheItem->set($newToken));

        return $newToken;
    }
}
