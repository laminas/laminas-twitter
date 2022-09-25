<?php

declare(strict_types=1);

namespace LaminasTest\Twitter;

use Closure;
use Laminas\Twitter\Media;
use Laminas\Twitter\Video;
use PHPUnit\Framework\TestCase;

final class VideoTest extends TestCase
{
    public function testCanBeInstantiatedWithNoMediaTypeAndUsesSaneDefaults(): void
    {
        $video = new Video(__FILE__);

        $imageMediaType = Closure::bind(fn() => $this->mediaType, $video, Media::class)();
        $this->assertSame('video/mp4', $imageMediaType);

        $imageFilename = Closure::bind(fn() => $this->imageFilename, $video, Media::class)();
        $this->assertSame(__FILE__, $imageFilename);
    }

    public function testCanBeInstantiatedWithFilenameAndMediaType(): void
    {
        $video = new Video(__FILE__, 'text/plain');

        $imageMediaType = Closure::bind(fn() => $this->mediaType, $video, Media::class)();
        $this->assertSame('text/plain', $imageMediaType);

        $imageFilename = Closure::bind(fn() => $this->imageFilename, $video, Media::class)();
        $this->assertSame(__FILE__, $imageFilename);
    }
}
