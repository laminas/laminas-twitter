<?php

/**
 * @see       https://github.com/laminas/laminas-twitter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-twitter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-twitter/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Twitter;

use Laminas\Twitter\Video;
use PHPUnit\Framework\TestCase;

class VideoTest extends TestCase
{
    public function testCanBeInstantiatedWithNoMediaTypeAndUsesSaneDefaults()
    {
        $video = new Video(__FILE__);
        $this->assertAttributeEquals(__FILE__, 'imageFilename', $video);
        $this->assertAttributeEquals('video/mp4', 'mediaType', $video);
    }

    public function testCanBeInstantiatedWithFilenameAndMediaType()
    {
        $video = new Video(__FILE__, 'text/plain');
        $this->assertAttributeEquals(__FILE__, 'imageFilename', $video);
        $this->assertAttributeEquals('text/plain', 'mediaType', $video);
    }
}
