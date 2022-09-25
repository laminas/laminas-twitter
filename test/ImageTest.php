<?php

declare(strict_types=1);

namespace LaminasTest\Twitter;

use Closure;
use Laminas\Twitter\Image;
use Laminas\Twitter\Media;
use PHPUnit\Framework\TestCase;

final class ImageTest extends TestCase
{
    public function testCanBeInstantiatedWithNoMediaTypeAndUsesSaneDefaults(): void
    {
        $image = new Image(__FILE__);

        $imageMediaType = Closure::bind(fn() => $this->mediaType, $image, Media::class)();
        $this->assertSame('image/jpeg', $imageMediaType);

        $imageFilename = Closure::bind(fn() => $this->imageFilename, $image, Media::class)();
        $this->assertSame(__FILE__, $imageFilename);
    }

    public function testCanBeInstantiatedWithFilenameAndMediaType(): void
    {
        $image = new Image(__FILE__, 'text/plain');

        $imageMediaType = Closure::bind(fn() => $this->mediaType, $image, Media::class)();
        $this->assertSame('text/plain', $imageMediaType);

        $imageFilename = Closure::bind(fn() => $this->imageFilename, $image, Media::class)();
        $this->assertSame(__FILE__, $imageFilename);
    }
}
