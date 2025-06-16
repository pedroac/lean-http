<?php

declare(strict_types=1);

namespace Pac\LeanHttp;

use Pac\LeanHttp\Message;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Represents an HTTP request.
 * This class implements the RequestInterface and extends the Message class.
 * It provides methods to access and modify the request method, URI, headers, and body.
 * The request target can also be set and retrieved.
 */
class Request extends Message implements RequestInterface
{
    /**
     * Constructor to initialize the request with method, URI, body, headers, request target, and protocol version.
     * @param string $method
     * The HTTP method (e.g., 'GET', 'POST').
     * @param UriInterface $uri
     * The URI of the request.
     * @param StreamInterface $body
     * The body of the request.
     * @param array $headers
     * The headers of the request.
     * @param string $requestTarget
     * The request target (default is '/').
     * @param ?string $protocolVersion
     * The HTTP protocol version (default is null).
     */
    public function __construct(
        private string $method,
        private UriInterface $uri,
        StreamInterface $body,
        array $headers = [],
        private string $requestTarget = '/',
        ?string $protocolVersion = null
    ) {
        parent::__construct($body, $headers, $protocolVersion);
    }

    /**
     * Summary of getRequestTarget
     * @return string
     */
	public function getRequestTarget(): string 
    {
        if ($this->requestTarget !== null && $this->requestTarget !== '') {
            return $this->requestTarget;
        }
        $target = $this->uri->getPath();
        if ($target === '') {
            $target = '/';
        }
        $query = $this->uri->getQuery();
        if ($query !== '') {
            $target .= '?' . $query;
        }
        return $target;
    }

    /**
     * Set the request target.
     * The request target is the part of the request that identifies the resource being requested.
     * It can be a path, a path with a query string, or an absolute URI.
     * @param string $requestTarget
     * The request target to set.
     * It should be a valid string representing the request target.
     * If it is an absolute URI, it should include the scheme, host, and path.
     * @return self
     * Returns a new instance of the Request with the updated request target.
     */
	public function withRequestTarget(string $requestTarget): self 
    {
        $new = clone $this;
        $new->requestTarget = $requestTarget;
        return $new;
    }

    /**
     * Get the HTTP method of the request.
     * The HTTP method indicates the action to be performed on the resource identified by the request URI.
     * Common methods include 'GET', 'POST', 'PUT', 'DELETE', etc.
     * @return string
     * Returns the HTTP method as a string.
     * The method is typically in uppercase, but it can be in any case as long as it is a valid HTTP method.
     */
	public function getMethod(): string 
    {
        return $this->method;
    }

    /**
     * Set the HTTP method of the request.
     * The HTTP method indicates the action to be performed on the resource identified by the request URI.
     * Common methods include 'GET', 'POST', 'PUT', 'DELETE', etc.
     * This method returns a new instance of the Request with the updated method.
     * @param string $method
     * The HTTP method to set.
     * It should be a valid HTTP method string, typically in uppercase.
     * Common methods include 'GET', 'POST', 'PUT', 'DELETE', etc.
     * @return Request
     * Returns a new instance of the Request with the updated method.
     * The new instance will have the same URI, body, headers, and protocol version as the original request,
     * but with the specified method.
     * @throws \InvalidArgumentException
     * Throws an exception if the method is not a valid HTTP method.
     * Valid HTTP methods consist of alphanumeric characters and some special characters.
     * Examples of valid methods include 'GET', 'POST', 'PUT', 'DELETE', 'PATCH', etc.
     */
	public function withMethod(string $method): self 
    {
        if (!preg_match('/^[!#$%&\'*+\-.^_`|~0-9a-zA-Z]+$/', $method)) {
            throw new \InvalidArgumentException("Invalid HTTP method: $method");
        }
        $new = clone $this;
        $new->method = $method;
        return $new;
    }

    /**
     * Get the URI of the request.
     * The URI represents the resource being requested.
     * It includes the scheme, host, port, path, query string, and fragment.
     * @return UriInterface
     * Returns the URI as an instance of UriInterface.
     * The URI can be used to access various components like scheme, host, path, query, etc.
     */
	public function getUri(): UriInterface 
    {
        return $this->uri;
    }

    /**
     * Set the URI of the request.
     * The URI represents the resource being requested.
     * It includes the scheme, host, port, path, query string, and fragment.
     * This method returns a new instance of the Request with the updated URI.
     * @param UriInterface $uri
     * The URI to set for the request.
     * It should be an instance of UriInterface representing a valid URI.
     * @param bool $preserveHost
     * If true, the 'Host' header will be preserved if it exists in the original request.
     * If false, the 'Host' header will be updated to match the host from the new URI.
     * @return self
     * Returns a new instance of the Request with the updated URI and headers.
     */
	public function withUri(UriInterface $uri, bool $preserveHost=true): self 
    {
        $new = clone $this;
        $new->uri = $uri;
        $host = $uri->getHost();
        if (!$preserveHost || (!$new->hasHeader('host') && $host !== '')) {
            $new = $new->withHeader('host', $host);
        }
        return $new;
    }
}
