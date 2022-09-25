<?php // phpcs:disable SlevomatCodingStandard.Classes.UnusedPrivateElements.WriteOnlyProperty

namespace Laminas\Twitter;

use Laminas\Http\Headers;

use function property_exists;

/**
 * Representation of the Rate Limit Headers from Twitter.
 */
class RateLimit
{
    private int $limit;

    private int $remaining;

    private int $reset;

    /**
     * Constructor
     */
    public function __construct(?Headers $headers = null)
    {
        if (! $headers) {
            return;
        }

        $this->limit     = $headers->has('x-rate-limit-limit')
            ? (int) $headers->get('x-rate-limit-limit')->getFieldValue()
            : 0;
        $this->remaining = $headers->has('x-rate-limit-remaining')
            ? (int) $headers->get('x-rate-limit-remaining')->getFieldValue()
            : 0;
        $this->reset     = $headers->has('x-rate-limit-reset')
            ? (int) $headers->get('x-rate-limit-reset')->getFieldValue()
            : 0;
    }

    /**
     * Retun the requested property
     *
     * @param string $key
     */
    public function __get($key): ?int
    {
        return $this->$key ?? null;
    }

    /**
     * Is the requested property available?
     *
     * @param string $key
     */
    public function __isset($key): bool
    {
        return property_exists($this, $key);
    }
}
