<?php

namespace LaminasTest\Twitter;

use Laminas\Http\Header\HeaderInterface;
use Laminas\Http\Headers;
use Laminas\Http\Response as HttpResponse;
use Laminas\Twitter\RateLimit;
use Laminas\Twitter\Response;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use ReflectionProperty;

final class ResponseTest extends TestCase
{
    use ProphecyTrait;

    public function testPopulateAddsRateLimitBasedOnHttpResponseHeaders(): void
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
        $this->assertInstanceOf(RateLimit::class, $response->getRateLimit());

        $reflectionProperty = new ReflectionProperty($response, 'rateLimit');
        $reflectionProperty->setAccessible(true);
        $rateLimit = $reflectionProperty->getValue($response);

        $this->assertSame(3600, $rateLimit->limit);
        $this->assertSame(237, $rateLimit->remaining);
        $this->assertSame(4200, $rateLimit->reset);
    }
}
