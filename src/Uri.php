<?php

declare(strict_types=1);

namespace Pac\LeanHttp;

use Pac\LeanHttp\Uri\UriBuilder;
use Pac\LeanHttp\Uri\UriNormalizer;
use Pac\LeanHttp\Uri\UriValidator;
use Psr\Http\Message\UriInterface;

/**
 * Value object representing a normalized URL.
 *
 * It guarantees all components are normalized, and instances return normalized URI string representations.
 *
 * @link http://tools.ietf.org/html/rfc3986 (the URI specification)
 * @link https://url.spec.whatwg.org/
 */
class Uri implements UriInterface
{
    /**
     * The scheme component or an empty string.
     */
    private string $scheme = '';

    /**
     * Lazy initialized authority.
     */
    private ?string $authority = null;

    /**
     * The user component or an empty string.
     */
    private string $user = '';

    /**
     * The password component or NULL.
     */
    private ?string $password = null;

    /**
     * The host (name, IPv4 or IPv6) or an empty string.
     */
    private string $host = '';

    /**
     * The port number or NULL.
     */
    private ?int $port = null;

    /**
     * The path component or an empty string.
     * @var string
     */
    private string $path = '';

    /**
     * The query component or NULL.
     */
    private ?string $query = null;

    /**
     * The query parameters as an associative array or NULL.
     * This is lazy initialized when the query is set.
     * @var array<int|string, mixed>|null
     */
    private ?array $params = null;

    /**
     * The fragment component or NULL.
     */
    private ?string $fragment = null;

    /**
     * Lazy initialized string representation.
     */
    private ?string $string = null;

    /**
     * Constructor to initialize the URI from a string.
     *
     * Parses and normalizes the URI string according to RFC 3986 and WHATWG URL standards.
     * All components (scheme, host, path, query, fragment) are automatically normalized.
     *
     * @param string $string The URL string to normalize (e.g., 'http://example.com/path?query#fragment')
     * @throws \InvalidArgumentException If the URL is invalid or cannot be parsed
     * @see \Pac\LeanHttp\Uri\UriNormalizer
     * @see https://tools.ietf.org/html/rfc3986
     * @see https://url.spec.whatwg.org/
     */
    public function __construct(string $string)
    {
        $parts = parse_url($string);
        if ($parts === false) {
            throw new \InvalidArgumentException("Invalid URL: $string.");
        }
        $normalizer = UriNormalizer::getDefault();
        $this->scheme = isset($parts['scheme'])
            ? $normalizer->normalizeScheme($parts['scheme'])
            : '';
        $this->host = isset($parts['host'])
            ? $normalizer->normalizeHost($parts['host'])
            : '';
        $this->user = $parts['user'] ?? '';
        $this->password = $parts['pass'] ?? null;
        $this->port = $parts['port'] ?? null;
        $this->path = isset($parts['path']) ? $normalizer->normalizePath($parts['path']) : '';
        $this->query = isset($parts['query']) ? $normalizer->normalizeQuery($parts['query']) : null;
        $this->fragment = isset($parts['fragment']) ? $normalizer->normalizeEncode($parts['fragment']) : null;
    }

    /**
     * Return the string representation of the normalized URL.
     *
     * The string is lazily computed and cached for performance.
     *
     * @return string The normalized URI string
     */
    public function __toString(): string
    {
        if ($this->string === null) {
            $this->string = (new UriBuilder(
                $this->scheme,
                $this->host,
                str_starts_with($this->path, '/') ? $this->path : "/$this->path",
                $this->query,
                $this->fragment,
                (UriNormalizer::getDefault())->normalizePort($this->port, $this->scheme),
                $this->user,
                $this->password
            ))->buildRaw();
        }

        return $this->string;
    }

    /**
     * Check if this URI is equal to another value.
     *
     * @param mixed $other The value to compare (UriInterface, string, or stringable object)
     * @return bool True if the URIs are equal after normalization
     */
    public function equals($other): bool
    {
        if ($other instanceof self) {
            return $other->string === $this->string;
        }
        if (is_string($other) || method_exists($other, '__toString')) {
            return (string)(new self((string)$other)) === $this->string;
        }

        return false;
    }

    /**
     * Return the normalized authority component of the URI or an empty string.
     *
     * Format: "user:password@host:port" or "host:port" or "host" or empty string.
     * The authority is lazily computed and cached for performance.
     *
     * @return string The authority component
     */
    public function getAuthority(): string
    {
        if (! isset($this->authority)) {
            $this->authority = (new UriBuilder(
                $this->scheme,
                $this->host,
                $this->path,
                port: $this->port,
                user: $this->user,
                password: $this->password
            )
            )->buildAuthority();
        }

        return $this->authority;
    }

    /**
     * Return the scheme component of the URI or an empty string.
     *
     * @return string The scheme (e.g., 'http', 'https') or empty string
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * Return the user information component of the URI or an empty string.
     *
     * Format: "user:password" or "user" or empty string.
     *
     * @return string The user info component
     */
    public function getUserInfo(): string
    {
        return (UriNormalizer::getDefault())->normalizeUserInfo($this->user, $this->password);
    }


    /**
     * Return the host component of the URI or an empty string.
     *
     * @return string The host (domain name, IPv4, or IPv6) or empty string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Return the port of the URI or null.
     *
     * Returns null if the port is the standard port for the scheme (80 for http, 443 for https).
     *
     * @return int|null The port number (0-65535) or null
     */
    public function getPort(): ?int
    {
        return (UriNormalizer::getDefault())->normalizePort($this->port, $this->scheme);
    }

    /**
     * Return the path component of the URI or an empty string.
     *
     * @return string The path component
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Return the normalized query component of the URI or an empty string.
     *
     * @return string The query string (without leading '?') or empty string
     */
    public function getQuery(): string
    {
        return $this->query ?? '';
    }

    /**
     * Return the query parameters of the URI as an associative array.
     *
     * Query parameters are lazily parsed and cached for performance.
     *
     * @return array<int|string, mixed> Associative array of query parameters
     */
    public function getQueryParams(): array
    {
        if ($this->query === null) {
            return [];
        }
        if ($this->params === null) {
            $this->params = [];
            if ($this->query !== null && $this->query !== '') {
                parse_str($this->query, $this->params);
                $this->params = $this->params ?? [];
            }
        }

        return $this->params;
    }

    /**
     * Return the normalized fragment of the URI string.
     *
     * @return string The fragment (without leading '#') or empty string
     */
    public function getFragment(): string
    {
        return $this->fragment ?? '';
    }

    /**
     * Return an instance with the specified scheme component.
     *
     * @param string $scheme The scheme (e.g., 'http', 'https')
     * @return static A new instance with the specified scheme
     * @throws \InvalidArgumentException If the scheme is invalid
     */
    public function withScheme(string $scheme): self
    {
        $clone = clone $this;
        $clone->scheme = (UriNormalizer::getDefault())->normalizeScheme($scheme);
        // Reset the string representation and authority to force regeneration.
        $clone->string = null;

        return $clone;
    }

    /**
     * Return an instance with the specified user information component.
     *
     * @param string $user The user name
     * @param ?string $password The password or null
     * @return static A new instance with the specified user information
     */
    public function withUserInfo(string $user, ?string $password = null): self
    {
        $clone = clone $this;
        $clone->user = $user;
        $clone->password = $password;
        // Reset the string representation and authority to force regeneration.
        $clone->string = null;
        $clone->authority = null;

        return $clone;
    }

    /**
     * Return an instance with the specified host component.
     *
     * @param string $host The host (domain name, IPv4, or IPv6) or empty string
     * @return static A new instance with the specified host
     * @throws \InvalidArgumentException If the host is invalid
     */
    public function withHost(string $host): self
    {
        $clone = clone $this;
        $clone->host = (UriNormalizer::getDefault())->normalizeHost($host);
        // Reset the string representation and authority to force regeneration.
        $clone->string = null;
        $clone->authority = null;

        return $clone;
    }

    /**
     * Return an instance with the specified port component.
     *
     * @param ?int $port The port number (0-65535) or null
     * @return static A new instance with the specified port
     * @throws \InvalidArgumentException If the port is not in the valid range
     */
    public function withPort(?int $port): self
    {
        if ($port !== null && ! (UriValidator::getDefault())->validatePort($port)) {
            throw new \InvalidArgumentException("Invalid URI port: $port.");
        }
        $clone = clone $this;
        $clone->port = $port;
        // Reset the string representation to force regeneration.
        $clone->string = null;

        return $clone;
    }

    /**
     * Return an instance with the specified path component.
     *
     * @param string $path The path component
     * @return static A new instance with the specified path
     */
    public function withPath(string $path): self
    {
        $clone = clone $this;
        $clone->path = (UriNormalizer::getDefault())->normalizePath($path);
        // Reset the string representation to force regeneration.
        $clone->string = null;

        return $clone;
    }

    /**
     * Return an instance with the specified query component.
     *
     * @param string $query The query string (without leading '?')
     * @return static A new instance with the specified query
     */
    public function withQuery(string $query): self
    {
        $clone = clone $this;
        $clone->query = (UriNormalizer::getDefault())->normalizeQuery($query);
        // Reset the string representation and query parameters to force regeneration.
        $clone->string = null;
        $clone->params = null;

        return $clone;
    }

    /**
     * Return an instance with the specified query parameters.
     *
     * @param array<string, mixed> $params Associative array of query parameters
     * @return static A new instance with the specified query parameters
     */
    public function withQueryParams(array $params): self
    {
        $uri = $this->withQuery(
            http_build_query($params, '', '&', \PHP_QUERY_RFC3986)
        );
        $uri->params = $params;

        return $uri;
    }

    /**
     * Return an instance with the specified fragment component.
     *
     * @param string $fragment The fragment (without leading '#')
     * @return static A new instance with the specified fragment
     */
    public function withFragment(string $fragment): self
    {
        $clone = clone $this;
        $clone->fragment = (UriNormalizer::getDefault())->normalizeEncode($fragment);
        // Reset the string representation to force regeneration.
        $clone->string = null;

        return $clone;
    }

    /**
     * Check if the URI is opaque.
     *
     * An opaque URI has a scheme but no authority component and the path doesn't start with '/'.
     *
     * @return bool True if the URI is opaque
     */
    public function isOpaque(): bool
    {
        return $this->scheme
            && ! $this->getAuthority()
            && strpos($this->path, '/') !== 0;
    }

    /**
     * Check if the URI is absolute.
     *
     * An absolute URI has both a scheme and a host component.
     *
     * @return bool True if the URI is absolute
     * @see https://tools.ietf.org/html/rfc3986#section-4.3
     */
    public function isAbsolute(): bool
    {
        return $this->scheme !== '' && $this->host !== '';
    }

    /**
     * Resolve a relative URI against this base URI.
     *
     * Follows RFC 3986 section 5.2 for URI resolution.
     *
     * @param UriInterface $uri The relative URI to resolve
     * @return self A new instance with the resolved URI
     * @throws \InvalidArgumentException If the base URI is not absolute
     * @see https://tools.ietf.org/html/rfc3986#section-5.2
     */
    public function resolve(UriInterface $uri): self
    {
        if (! $this->isAbsolute()) {
            throw new \InvalidArgumentException("Base URI must be absolute.");
        }
        if ($uri->getScheme() !== '') {
            return new self((string)$uri);
        }
        if ($uri->getHost() !== '') {
            return new self((string)$uri->withScheme($this->getScheme()));
        }
        $path = $uri->getPath();
        if (isset($path[1]) && $path[0] === '/') {
            return new self((string)$uri->withScheme($this->getScheme())->withHost($this->getHost())->withPort($this->getPort()));
        }
        $normalizedPath = (UriNormalizer::getDefault())->normalizePath("{$this->getPath()}/$path");

        return new self((string)$uri->withScheme($this->getScheme())->withHost($this->getHost())->withPort($this->getPort())->withPath($normalizedPath));
    }
}
