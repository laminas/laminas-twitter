<?php

/**
 * @see       https://github.com/laminas/laminas-twitter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-twitter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-twitter/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Twitter;

use Laminas\Twitter\Image;
use PHPUnit\Framework\TestCase;

class ImageTest extends TestCase
{
    public function testCanBeInstantiatedWithNoMediaTypeAndUsesSaneDefaults()
    {
        $image = new Image(__FILE__);
        $this->assertAttributeEquals(__FILE__, 'imageFilename', $image);
        $this->assertAttributeEquals('image/jpeg', 'mediaType', $image);
    }

    public function testCanBeInstantiatedWithFilenameAndMediaType()
    {
        $image = new Image(__FILE__, 'text/plain');
        $this->assertAttributeEquals(__FILE__, 'imageFilename', $image);
        $this->assertAttributeEquals('text/plain', 'mediaType', $image);
    }
}
