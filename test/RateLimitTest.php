<?php

/**
 * @see       https://github.com/laminas/laminas-twitter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-twitter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-twitter/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Twitter;

use Laminas\Http\Header\HeaderInterface;
use Laminas\Http\Headers;
use Laminas\Twitter\RateLimit;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

final class RateLimitTest extends TestCase
{
    use ProphecyTrait;

    public function testInstantiatingWithNoArgumentLeavesAllPropertiesNull()
    {
        $rateLimit = new RateLimit();
        $this->assertNull($rateLimit->limit);
        $this->assertNull($rateLimit->remaining);
        $this->assertNull($rateLimit->reset);
    }

    public function headersProvider()
    {
        return [
            'limit-only'     => [5000, null, null],
            'remaining-only' => [null, 271, null],
            'reset-only'     => [null, null, 3600],
            'all-values'     => [5000, 271, 3600],
        ];
    }

    /**
     * @dataProvider headersProvider
     */
    public function testConstructorUsesHeadersToSetProperties($limit, $remaining, $reset)
    {
        $phpunit = $this;
        $headers = $this->prophesize(Headers::class);

        if (! $limit) {
            $headers->has('x-rate-limit-limit')->willReturn(false);
            $limit = 0;
        } else {
            $headers->has('x-rate-limit-limit')->willReturn(true);
            $headers->get('x-rate-limit-limit')->will(function () use ($limit, $phpunit) {
                $header = $phpunit->prophesize(HeaderInterface::class);
                $header->getFieldValue()->willReturn($limit);
                return $header->reveal();
            });
        }

        if (! $remaining) {
            $headers->has('x-rate-limit-remaining')->willReturn(false);
            $remaining = 0;
        } else {
            $headers->has('x-rate-limit-remaining')->willReturn(true);
            $headers->get('x-rate-limit-remaining')->will(function () use ($remaining, $phpunit) {
                $header = $phpunit->prophesize(HeaderInterface::class);
                $header->getFieldValue()->willReturn($remaining);
                return $header->reveal();
            });
        }

        if (! $reset) {
            $headers->has('x-rate-limit-reset')->willReturn(false);
            $reset = 0;
        } else {
            $headers->has('x-rate-limit-reset')->willReturn(true);
            $headers->get('x-rate-limit-reset')->will(function () use ($reset, $phpunit) {
                $header = $phpunit->prophesize(HeaderInterface::class);
                $header->getFieldValue()->willReturn($reset);
                return $header->reveal();
            });
        }

        $rateLimit = new RateLimit($headers->reveal());

        $this->assertSame($limit, $rateLimit->limit);
        $this->assertSame($remaining, $rateLimit->remaining);
        $this->assertSame($reset, $rateLimit->reset);
    }
}
