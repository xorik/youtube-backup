<?php

declare(strict_types=1);

namespace xorik\YtUpload\Model;

enum VideoState: string
{
    case QUEUED = 'queued';
    case DOWNLOADING = 'downloading';
    case DOWNLOADED = 'downloaded';
    case UPLOADING = 'uploading';
    case PROCESSED = 'uploaded';
    case PUBLISHED = 'published';
    case ERROR = 'error';
}
