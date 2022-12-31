<?php

declare(strict_types=1);

namespace xorik\YtUpload\Model;

enum VideoState: string
{
    case QUEUED = 'queued';
    case DOWNLOADING = 'downloading';
    case DOWNLOADED = 'downloaded';
    case UPLOADING = 'uploading';
    case UPLOADED = 'uploaded';
    case PREPARED = 'prepared'; // Set thumbnail & playlist
    case PUBLISHED = 'published';
    case ERROR = 'error';
}
