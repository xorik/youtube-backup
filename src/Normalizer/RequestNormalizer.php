<?php

declare(strict_types=1);

namespace xorik\YtUpload\Normalizer;

use GuzzleHttp\Psr7\Request;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class RequestNormalizer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * @param Request $object
     */
    public function normalize($object, $format = null, array $context = []): array
    {
        return [
            'method' => $object->getMethod(),
            'uri' => (string) $object->getUri(),
            'headers' => $object->getHeaders(),
            'body' => (string) $object->getBody(),
        ];
    }

    public function denormalize($data, $class, $format = null, array $context = []): Request
    {
        return new Request(
            $data['method'],
            $data['uri'],
            $data['headers'],
            $data['body']
        );
    }

    public function supportsNormalization($data, $format = null): bool
    {
        return $data instanceof Request;
    }

    public function supportsDenormalization($data, $type, $format = null): bool
    {
        return $type === Request::class;
    }
}
