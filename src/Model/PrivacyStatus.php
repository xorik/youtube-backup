<?php

namespace xorik\YtUpload\Model;

enum PrivacyStatus: string
{
    case PUBLIC = 'public';
    case PRIVATE = 'private';
    case UNLISTED = 'unlisted';
}
