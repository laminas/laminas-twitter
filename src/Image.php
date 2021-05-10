<?php

namespace Laminas\Twitter;

/**
 * Twitter Image Uploader
 */
class Image extends Media
{
    public function __construct(
        string $imageUrl,
        string $mediaType = 'image/jpeg',
        bool $forDirectMessage = false,
        bool $shared = false
    ) {
        parent::__construct($imageUrl, $mediaType, $forDirectMessage, $shared);
    }
}
