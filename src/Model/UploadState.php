<?php

declare(strict_types=1);

namespace xorik\YtUpload\Model;

use Psr\Http\Message\RequestInterface;

class UploadState
{
    public function __construct(
        readonly public RequestInterface $request,
        readonly public ?string $resumeUrl = null,
    ) {
    }
}
