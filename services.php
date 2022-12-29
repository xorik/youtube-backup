<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use xorik\YtUpload\Normalizer\RequestNormalizer;
use xorik\YtUpload\Service\YoutubeApi;

return static function (ContainerConfigurator $container) {
    $services = $container->services();
    $services->defaults()->autowire();

    $services->load('xorik\\YtUpload\\Service\\', __DIR__ . '/src/Service');
    $services->load('xorik\\YtUpload\\Command\\', __DIR__ . '/src/Command')->public();

    $services
        ->set(CacheItemPoolInterface::class, FilesystemAdapter::class)
        ->args([
            '$directory' => __DIR__ . '/cache',
        ])
    ;

    $services
        ->set(Serializer::class)
        ->args([
            [
                inline_service(RequestNormalizer::class),
                inline_service(BackedEnumNormalizer::class),
                inline_service(ObjectNormalizer::class),
            ],
            [inline_service(JsonEncoder::class)],
        ])
    ;

    $services
        ->set(YoutubeApi::class)
        ->args([
            '$clientId' => env('YT_CLIENT_ID'),
            '$clientSecret' => env('YT_CLIENT_SECRET'),
            '$redirectUrl' => env('YT_REDIRECT_URL'),
        ])
    ;
};
