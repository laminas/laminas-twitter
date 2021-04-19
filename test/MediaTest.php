<?php

/**
 * @see       https://github.com/laminas/laminas-twitter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-twitter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-twitter/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Twitter;

use Laminas\Http\Client as Client;
use Laminas\Http\Response;
use Laminas\Twitter\Exception;
use Laminas\Twitter\Media;
use Laminas\Twitter\Response as TwitterResponse;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use ReflectionProperty;

class MediaTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->client = $this->prophesize(Client::class);
    }

    public function testAllowsPassingImageFilenameAndMediaType()
    {
        $media = new Media(__FILE__, 'text/plain');

        $imageMediaType = \Closure::bind(function () {
            return $this->mediaType;
        }, $media, Media::class)();
        $this->assertSame('text/plain', $imageMediaType);

        $imageFilename = \Closure::bind(function () {
            return $this->imageFilename;
        }, $media, Media::class)();
        $this->assertSame(__FILE__, $imageFilename);
    }

    public function testUploadRaisesExceptionIfNoImageFilenamePresent()
    {
        $media = new Media('', 'text/plain');
        $this->expectException(Exception\InvalidMediaException::class);
        $this->expectExceptionMessage('Failed to open');
        $media->upload($this->client->reveal());
    }

    public function testUploadRaisesExceptionIfNoMediaTypePresent()
    {
        $media = new Media(__FILE__, '');
        $this->expectException(Exception\InvalidMediaException::class);
        $this->expectExceptionMessage('Invalid Media Type');
        $media->upload($this->client->reveal());
    }

    public function testUnsuccessfulUploadInitializationRaisesException()
    {
        $this->client->setUri(Media::UPLOAD_BASE_URI)->shouldBeCalled();
        $this->client->resetParameters()->shouldBeCalled();
        $this->client->setHeaders([
            'Content-type' => 'application/x-www-form-urlencoded'
        ])->shouldBeCalled();
        $this->client->setMethod('POST')->shouldBeCalled();
        $this->client->setParameterPost([
            'command'        => 'INIT',
            'media_category' => 'tweet_image',
            'media_type'     => 'image/png',
            'total_bytes'    => filesize(__FILE__),
        ])->shouldBeCalled();

        $response = $this->prophesize(Response::class);
        $response->getBody()->willReturn('{}');
        $response->getHeaders()->willReturn(null);
        $response->isSuccess()->willReturn(false);

        $this->client->send()->will([$response, 'reveal']);

        $media = new Media(__FILE__, 'image/png');

        $this->expectException(Exception\MediaUploadException::class);
        $this->expectExceptionMessage('Failed to initialize Twitter media upload');
        $media->upload($this->client->reveal());
    }

    public function testAppendUploadRaisesExceptionIfUnableToOpenFile()
    {
        $media = new Media(__FILE__, 'image/png');

        $this->client->setUri(Media::UPLOAD_BASE_URI)->shouldBeCalled();
        $this->client->resetParameters()->shouldBeCalled();
        $this->client->setHeaders([
            'Content-type' => 'application/x-www-form-urlencoded'
        ])->shouldBeCalled();
        $this->client->setMethod('POST')->shouldBeCalled();
        $this->client->setParameterPost([
            'command'        => 'INIT',
            'media_category' => 'tweet_image',
            'media_type'     => 'image/png',
            'total_bytes'    => filesize(__FILE__),
        ])->shouldBeCalled();

        $response = $this->prophesize(Response::class);
        $response->getBody()->willReturn('{"media_id": "XXXX"}');
        $response->getHeaders()->willReturn(null);
        $response->isSuccess()->will(function () use ($media) {
            $r = new ReflectionProperty($media, 'imageFilename');
            $r->setAccessible(true);
            $r->setValue($media, '  This File Does Not Exist  ');
            return true;
        });

        $this->client->send()->will([$response, 'reveal']);

        $this->expectException(Exception\MediaUploadException::class);
        $this->expectExceptionMessage('Failed to open the file in the APPEND');
        $media->upload($this->client->reveal());
    }

    public function testAppendUploadRaisesExceptionIfChunkUploadFails()
    {
        $media = new Media(__FILE__, 'image/png');

        $this->client->setUri(Media::UPLOAD_BASE_URI)->shouldBeCalled();
        $this->client->resetParameters()->shouldBeCalledTimes(2);
        $this->client->setHeaders([
            'Content-type' => 'application/x-www-form-urlencoded'
        ])->shouldBeCalledTimes(2);
        $this->client->setMethod('POST')->shouldBeCalledTimes(2);
        $this->client->setParameterPost([
            'command'        => 'INIT',
            'media_category' => 'tweet_image',
            'media_type'     => 'image/png',
            'total_bytes'    => filesize(__FILE__),
        ])->shouldBeCalled();

        $this->client->setParameterPost(Argument::that(function ($arg) use (&$commands) {
            TestCase::assertIsArray($arg);
            TestCase::assertArrayHasKey('command', $arg);
            TestCase::assertTrue(in_array($arg['command'], ['INIT', 'APPEND']));
            return true;
        }))->shouldBeCalledTimes(2);

        $initResponse = $this->prophesize(Response::class);
        $initResponse->getBody()->willReturn('{"media_id": "XXXX"}');
        $initResponse->getHeaders()->willReturn(null);
        $initResponse->isSuccess()->willReturn(true);

        $appendResponse = $this->prophesize(Response::class);
        $appendResponse->getBody()->willReturn('{}');
        $appendResponse->getHeaders()->willReturn(null);
        $appendResponse->isSuccess()->willReturn(false);
        $appendResponse->getStatusCode()->willReturn(400);
        $appendResponse->getReasonPhrase()->willReturn('Borked');

        $this->client->send()->willReturn(
            $initResponse->reveal(),
            $appendResponse->reveal()
        );

        $this->expectException(Exception\MediaUploadException::class);
        $this->expectExceptionMessage('Failed uploading segment');
        $media->upload($this->client->reveal());
    }

    public function testReturnsFinalizeCommandResponseWhenInitializationAndAppendAreSuccessful()
    {
        $media = new Media(__FILE__, 'image/png');
        $r = new ReflectionProperty($media, 'chunkSize');
        $r->setAccessible(true);
        $r->setValue($media, 4 * filesize(__FILE__));

        $client = $this->client;
        $client->setUri(Media::UPLOAD_BASE_URI)->shouldBeCalled();
        $client->resetParameters()->shouldBeCalled();
        $client->setHeaders([
            'Content-type' => 'application/x-www-form-urlencoded'
        ])->shouldBeCalledTimes(3);
        $client->setMethod('POST')->shouldBeCalledTimes(3);

        $commands = [];
        $client->setParameterPost(Argument::that(function ($arg) use (&$commands) {
            TestCase::assertIsArray($arg);
            TestCase::assertArrayHasKey('command', $arg);
            $commands[] = $arg['command'];
            return true;
        }))->shouldBeCalledTimes(3);

        $initResponse = $this->prophesize(Response::class);
        $initResponse->getBody()->willReturn('{"media_id": "XXXX"}');
        $initResponse->getHeaders()->willReturn(null);
        $initResponse->isSuccess()->willReturn(true);

        $appendResponse = $this->prophesize(Response::class);
        $appendResponse->getBody()->willReturn('{}');
        $appendResponse->getHeaders()->willReturn(null);
        $appendResponse->isSuccess()->willReturn(true);

        $finalizeResponse = $this->prophesize(Response::class);
        $finalizeResponse->getBody()->willReturn('{}');
        $finalizeResponse->getHeaders()->willReturn(null);

        $client->send()->willReturn(
            $initResponse->reveal(),
            $appendResponse->reveal(),
            $finalizeResponse->reveal()
        );

        $response = $media->upload($client->reveal());
        $this->assertInstanceOf(TwitterResponse::class, $response);

        $httpResponse = \Closure::bind(function () {
            return $this->httpResponse;
        }, $response, TwitterResponse::class)();
        $this->assertSame($finalizeResponse->reveal(), $httpResponse);

        $this->assertEquals(['INIT', 'APPEND', 'FINALIZE'], $commands);
    }

    public function testAllowsMarkingMediaAsForDirectMessageButUnshared()
    {
        $media = new Media(__FILE__, 'image/png', true, false);

        $r = new ReflectionProperty($media, 'chunkSize');
        $r->setAccessible(true);
        $r->setValue($media, 4 * filesize(__FILE__));

        $client = $this->client;
        $client->setUri(Media::UPLOAD_BASE_URI)->shouldBeCalled();
        $client->resetParameters()->shouldBeCalled();
        $client->setHeaders([
            'Content-type' => 'application/x-www-form-urlencoded'
        ])->shouldBeCalledTimes(3);
        $client->setMethod('POST')->shouldBeCalledTimes(3);

        $commands = [];
        $client->setParameterPost(Argument::that(function ($arg) use (&$commands) {
            TestCase::assertIsArray($arg);
            TestCase::assertArrayHasKey('command', $arg);
            $commands[] = $arg['command'];

            if ('INIT' === $arg['command']) {
                TestCase::assertArrayHasKey('media_category', $arg);
                TestCase::assertEquals('dm_image', $arg['media_category']);
                TestCase::assertArrayNotHasKey('shared', $arg);
            }
            return true;
        }))->shouldBeCalledTimes(3);

        $initResponse = $this->prophesize(Response::class);
        $initResponse->getBody()->willReturn('{"media_id": "XXXX"}');
        $initResponse->getHeaders()->willReturn(null);
        $initResponse->isSuccess()->willReturn(true);

        $appendResponse = $this->prophesize(Response::class);
        $appendResponse->getBody()->willReturn('{}');
        $appendResponse->getHeaders()->willReturn(null);
        $appendResponse->isSuccess()->willReturn(true);

        $finalizeResponse = $this->prophesize(Response::class);
        $finalizeResponse->getBody()->willReturn('{}');
        $finalizeResponse->getHeaders()->willReturn(null);

        $client->send()->willReturn(
            $initResponse->reveal(),
            $appendResponse->reveal(),
            $finalizeResponse->reveal()
        );

        $response = $media->upload($client->reveal());
        $this->assertInstanceOf(TwitterResponse::class, $response);

        $httpResponse = \Closure::bind(function () {
            return $this->httpResponse;
        }, $response, TwitterResponse::class)();
        $this->assertSame($finalizeResponse->reveal(), $httpResponse);

        $this->assertEquals(['INIT', 'APPEND', 'FINALIZE'], $commands);
    }

    public function testAllowsMarkingMediaForDirectMessageAndShared()
    {
        $media = new Media(__FILE__, 'image/png', true, true);

        $r = new ReflectionProperty($media, 'chunkSize');
        $r->setAccessible(true);
        $r->setValue($media, 4 * filesize(__FILE__));

        $client = $this->client;
        $client->setUri(Media::UPLOAD_BASE_URI)->shouldBeCalled();
        $client->resetParameters()->shouldBeCalled();
        $client->setHeaders([
            'Content-type' => 'application/x-www-form-urlencoded'
        ])->shouldBeCalledTimes(3);
        $client->setMethod('POST')->shouldBeCalledTimes(3);

        $commands = [];
        $client->setParameterPost(Argument::that(function ($arg) use (&$commands) {
            TestCase::assertIsArray($arg);
            TestCase::assertArrayHasKey('command', $arg);
            $commands[] = $arg['command'];

            if ('INIT' === $arg['command']) {
                TestCase::assertArrayHasKey('media_category', $arg);
                TestCase::assertEquals('dm_image', $arg['media_category']);
                TestCase::assertArrayHasKey('shared', $arg);
                TestCase::assertTrue($arg['shared']);
            }
            return true;
        }))->shouldBeCalledTimes(3);

        $initResponse = $this->prophesize(Response::class);
        $initResponse->getBody()->willReturn('{"media_id": "XXXX"}');
        $initResponse->getHeaders()->willReturn(null);
        $initResponse->isSuccess()->willReturn(true);

        $appendResponse = $this->prophesize(Response::class);
        $appendResponse->getBody()->willReturn('{}');
        $appendResponse->getHeaders()->willReturn(null);
        $appendResponse->isSuccess()->willReturn(true);

        $finalizeResponse = $this->prophesize(Response::class);
        $finalizeResponse->getBody()->willReturn('{}');
        $finalizeResponse->getHeaders()->willReturn(null);

        $client->send()->willReturn(
            $initResponse->reveal(),
            $appendResponse->reveal(),
            $finalizeResponse->reveal()
        );

        $response = $media->upload($client->reveal());
        $this->assertInstanceOf(TwitterResponse::class, $response);

        $httpResponse = \Closure::bind(function () {
            return $this->httpResponse;
        }, $response, TwitterResponse::class)();
        $this->assertSame($finalizeResponse->reveal(), $httpResponse);

        $this->assertEquals(['INIT', 'APPEND', 'FINALIZE'], $commands);
    }
}
