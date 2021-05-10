<?php

namespace Laminas\Twitter;

/**
 * Twitter Video Uploader
 */
class Video extends Media
{
    public function __construct(
        string $imageUrl,
        string $mediaType = 'video/mp4',
        bool $forDirectMessage = false,
        bool $shared = false
    ) {
        parent::__construct($imageUrl, $mediaType, $forDirectMessage, $shared);
    }
}
