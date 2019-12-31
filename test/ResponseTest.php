<?php

/**
 * @see       https://github.com/laminas/laminas-twitter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-twitter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-twitter/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Twitter;

use Laminas\Http\Header\HeaderInterface;
use Laminas\Http\Headers;
use Laminas\Http\Response as HttpResponse;
use Laminas\Twitter\RateLimit;
use Laminas\Twitter\Response;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class ResponseTest extends TestCase
{
    public function testPopulateAddsRateLimitBasedOnHttpResponseHeaders()
    {
        $phpunit = $this;

        $headers = $this->prophesize(Headers::class);
        $headers->has('x-rate-limit-limit')->willReturn(true);
        $headers->get('x-rate-limit-limit')->will(function () use ($phpunit) {
            $header = $phpunit->prophesize(HeaderInterface::class);
            $header->getFieldValue()->willReturn(3600);
            return $header->reveal();
        });
        $headers->has('x-rate-limit-remaining')->willReturn(true);
        $headers->get('x-rate-limit-remaining')->will(function () use ($phpunit) {
            $header = $phpunit->prophesize(HeaderInterface::class);
            $header->getFieldValue()->willReturn(237);
            return $header->reveal();
        });
        $headers->has('x-rate-limit-reset')->willReturn(true);
        $headers->get('x-rate-limit-reset')->will(function () use ($phpunit) {
            $header = $phpunit->prophesize(HeaderInterface::class);
            $header->getFieldValue()->willReturn(4200);
            return $header->reveal();
        });

        $httpResponse = $this->prophesize(HttpResponse::class);
        $httpResponse->getHeaders()->will([$headers, 'reveal']);
        $httpResponse->getBody()->willReturn('{}');

        $response = new Response($httpResponse->reveal());
        $this->assertAttributeInstanceOf(RateLimit::class, 'rateLimit', $response);

        $r = new ReflectionProperty($response, 'rateLimit');
        $r->setAccessible(true);
        $rateLimit = $r->getValue($response);

        $this->assertSame(3600, $rateLimit->limit);
        $this->assertSame(237, $rateLimit->remaining);
        $this->assertSame(4200, $rateLimit->reset);
    }
}
