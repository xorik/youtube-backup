<?php

namespace xorik\YtUpload\Model;

enum PrivacyStatus: string
{
    case PUBLIC = 'public';
    case PRIVATE = 'private';
    case UNLISTED = 'unlisted';

    public static function values()
    {
        return [
            self::PUBLIC->value,
            self::PRIVATE->value,
            self::UNLISTED->value,
        ];
    }
}
