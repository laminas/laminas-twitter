<?php

/**
 * @see       https://github.com/laminas/laminas-twitter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-twitter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-twitter/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Twitter;

use Closure;
use Laminas\Twitter\Image;
use Laminas\Twitter\Media;
use PHPUnit\Framework\TestCase;

final class ImageTest extends TestCase
{
    public function testCanBeInstantiatedWithNoMediaTypeAndUsesSaneDefaults()
    {
        $image = new Image(__FILE__);

        $imageMediaType = Closure::bind(function () {
            return $this->mediaType;
        }, $image, Media::class)();
        $this->assertSame('image/jpeg', $imageMediaType);

        $imageFilename = Closure::bind(function () {
            return $this->imageFilename;
        }, $image, Media::class)();
        $this->assertSame(__FILE__, $imageFilename);
    }

    public function testCanBeInstantiatedWithFilenameAndMediaType()
    {
        $image = new Image(__FILE__, 'text/plain');

        $imageMediaType = Closure::bind(function () {
            return $this->mediaType;
        }, $image, Media::class)();
        $this->assertSame('text/plain', $imageMediaType);

        $imageFilename = Closure::bind(function () {
            return $this->imageFilename;
        }, $image, Media::class)();
        $this->assertSame(__FILE__, $imageFilename);
    }
}
