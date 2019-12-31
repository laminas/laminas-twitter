<?php

/**
 * @see       https://github.com/laminas/laminas-twitter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-twitter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-twitter/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\Twitter;

use Laminas\Http\Response as HttpResponse;
use Laminas\Json\Exception\ExceptionInterface as JsonException;
use Laminas\Json\Json;

/**
 * Representation of a response from Twitter.
 *
 * Provides:
 *
 * - method for testing if we have a successful call
 * - method for retrieving errors, if any
 * - method for retrieving the raw JSON
 * - method for retrieving the decoded response
 * - proxying to elements of the decoded response via property overloading
 * - method for retrieving a RateLimit instance with derived rate-limit headers
 */
class Response
{
    /**
     * Empty body content that should not result in response population.
     */
    private $emptyBodyContent = [
        null,
        '',
    ];

    /**
     * @var HttpResponse
     */
    private $httpResponse;

    /**
     * @var array|\stdClass
     */
    private $jsonBody;

    /**
     * @var RateLimit
     */
    private $rateLimit;

    /**
     * @var string
     */
    private $rawBody;

    /**
     * Constructor
     *
     * Assigns the HttpResponse to a property, as well as the body
     * representation. It then attempts to decode the body as JSON.
     *
     * @param  null|HttpResponse $httpResponse
     * @throws Exception\DomainException if unable to decode JSON response
     */
    public function __construct(HttpResponse $httpResponse = null)
    {
        $this->httpResponse = $httpResponse;

        if ($httpResponse
            && ! in_array($httpResponse->getBody(), $this->emptyBodyContent, true)
        ) {
            $this->populate($httpResponse);
        }
    }

    /**
     * Property overloading to JSON elements
     *
     * If a named property exists within the JSON response returned,
     * proxies to it. Otherwise, returns null.
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        if (null === $this->jsonBody) {
            return null;
        }
        if (! isset($this->jsonBody->{$name})) {
            return null;
        }
        return $this->jsonBody->{$name};
    }

    /**
     * Was the request successful?
     */
    public function isSuccess() : bool
    {
        return $this->httpResponse->isSuccess();
    }

    /**
     * Did an error occur in the request?
     */
    public function isError() : bool
    {
        return ! $this->httpResponse->isSuccess();
    }

    /**
     * Retrieve the errors.
     *
     * Twitter _should_ return a standard error object, which contains an
     * "errors" property pointing to an array of errors. This method will
     * return that array if present, and raise an exception if not detected.
     *
     * If the response was successful, an empty array is returned.
     *
     * @throws Exception\DomainException if unable to detect structure of error response
     */
    public function getErrors() : array
    {
        if (! $this->isError()) {
            return [];
        }
        if (null === $this->jsonBody
            || ! isset($this->jsonBody->errors)
        ) {
            throw new Exception\DomainException(
                'Either no JSON response received, or JSON error response is malformed; cannot return errors'
            );
        }
        return $this->jsonBody->errors;
    }

    /**
     * Retrieve the raw response body
     */
    public function getRawResponse() : string
    {
        return $this->rawBody;
    }

    /**
     * Retun the decoded response body
     *
     * @return array|\stdClass
     */
    public function toValue()
    {
        return $this->jsonBody;
    }

    /**
     * Retun the RateLimit object associated with the response.
     */
    public function getRateLimit() : RateLimit
    {
        return $this->rateLimit;
    }

    /**
     * Populates the object with info. This can possibly called from the
     * constructor, or it can be called later.
     *
     * @throws Exception\DomainException if an error occurs parsing the response.
     */
    private function populate(HttpResponse $httpResponse = null) : void
    {
        $this->httpResponse = $httpResponse;
        $this->rawBody = $httpResponse->getBody();
        $this->rateLimit = new RateLimit($this->httpResponse->getHeaders());

        try {
            $jsonBody = Json::decode($this->rawBody, Json::TYPE_OBJECT);
            $this->jsonBody = $jsonBody;
        } catch (JsonException $e) {
            throw new Exception\DomainException(sprintf(
                'Unable to decode response from twitter: %s',
                $e->getMessage()
            ), 0, $e);
        }
    }
}
