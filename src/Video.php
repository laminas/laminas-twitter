<?php

/**
 * @see       https://github.com/laminas/laminas-twitter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-twitter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-twitter/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\Twitter;

/**
 * Twitter Video Uploader
 *
 * @author Cal Evans <cal@calevans.com>
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
