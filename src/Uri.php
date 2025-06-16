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
 * It garantees all the components are normalized, and the instances return normalized URI string representations. 
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
     */
    private ?array $params = null;
    
    /**
     * The fragment componet or NULL.
     */
    private ?string $fragment = null;

    /**
     * Lazy initialized string representation.
     */
    private ?string $string = null;

    /**
     * Constructor to initialize the URI from a string.
     * This constructor parses the provided URL string and initializes the URI components.
     * It uses the UriNormalizer to ensure all components are normalized according to the URI specification.
     * @param string $string
     * The URL string to normalize (e.g., 'http://example.com/path?query#fragment').
     * @throws \InvalidArgumentException
     * If the URL is invalid or cannot be parsed, an exception is thrown.
     * This constructor parses the URL string and initializes the URI components.
     * It uses the UriNormalizer to ensure all components are normalized according to the URI specification.
     * The components include scheme, host, user info, password, port, path, query, and fragment.
     * The scheme and host are normalized to lowercase, the path is normalized to ensure it starts with a slash,
     * the query is normalized to ensure it is properly encoded, and the fragment is normalized to ensure it is encoded.
     * If the URL string cannot be parsed, an `InvalidArgumentException` is thrown.
     * @see \Pac\LeanHttp\Uri\UriNormalizer
     * @see \Pac\LeanHttp\Uri\UriValidator
     * @see \Pac\LeanHttp\Uri\UriParser
     * @see \Psr\Http\Message\UriInterface
     * @see https://tools.ietf.org/html/rfc3986
     * @see https://url.spec.whatwg.org/
     */
    public function __construct(string $string) {
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
     * @return string
     * This method builds the normalized URI string representation using the UriBuilder.
     * It constructs the URI with the scheme, host, path, query, fragment, port, user, and password components.
     * The path is prefixed with a slash if it does not already start with one.
     * If the string representation has already been built, it returns the cached value.
     * @see \Pac\LeanHttp\Uri\UriBuilder::buildRaw()
     * @see \Pac\LeanHttp\Uri\UriBuilder
     */
    public function __toString(): string
    {
        if ($this->string === null) {
            $this->string = (new UriBuilder(
                $this->scheme,
                $this->host,
                !isset($this->path[0]) || $this->path[0] !== '/' ? "/$this->path" : $this->path,
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
     * @param mixed $compared The other value that should be compared.
     * @return bool
     * Returns true if the compared value is an instance of UriInterface and has the same string representation,
     * or if it is a string or a stringable object that, when converted to a string, matches the string representation of this instance.
     * If the compared value is not a UriInterface instance, a string, or a stringable object, it returns false.
     */
    public function equals($other): bool
    {
        if ($other instanceof Uri) {
            return $other->string === $this->string;
        }
        if (is_string($other) || method_exists($other, '__toString')) {
            return (string)(new static($other)) === $this->string;
        }
        return false;
    }

    /**
     * Return the normalized authority component of the URI or an empty string.
     *
     * @return string
     * This method returns the authority in the format "user:password@host:port".
     * If the user and password are not set, it returns "host:port" or just "host" if no port is provided.
     * If the host is not set, it returns an empty string.
     * @see \Pac\LeanHttp\Uri\UriBuilder::buildAuthority()
     */
    public function getAuthority(): string
    {
        if (!isset($this->authority)) {
            $this->authority = (new UriBuilder(
                $this->scheme,
                $this->host,
                $this->path,
                port: $this->port,
                user: $this->user, 
                password: $this->password)
            )->buildAuthority();
        }
        return $this->authority;
    }

    /**
     * Return the scheme component of the URI or an empty string.
     *
     * @return string
     * This method returns the scheme as it was provided, or an empty string if not set.
     * If the scheme is not set, it returns an empty string.
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * Return the user information component of the URI or an empty string.
     *
     * @return string
     * This method returns the user info in the format "user:password".
     * If the password is not set, it returns just the user name.
     * If both user and password are empty, it returns an empty string.
     * @see \Pac\LeanHttp\Uri\UriNormalizer::normalizeUserInfo()
     * @see \Pac\LeanHttp\Uri\UriBuilder::buildUserInfo()
     * @see \Pac\LeanHttp\Uri\UriBuilder::buildAuthority()
     */
    public function getUserInfo(): string
    {
        return (UriNormalizer::getDefault())->normalizeUserInfo($this->user, $this->password);
    }


    /**
     * Return the host component ot the URI or an empty string.
     *
     * @return string
     * This method returns the host as it was provided, or an empty string if not set.
     * If the host is not set, it returns an empty string.
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Return the port of the URI or null.
     * 
     * If the port is the standard used with the current scheme, this method returns null.
     * If no port is present, this method returns null.
     *
     * @return int|null
     * This method returns the port number if it is set and not the standard port for the scheme.
     * If the port is not set or is the standard port for the scheme, it returns null.
     * @throws \InvalidArgumentException
     * If the port is not null and is not a valid port number (0-65535), an exception is thrown.
     * @see \Pac\LeanHttp\Uri\UriValidator::validatePort()
     * @see \Pac\LeanHttp\Uri\UriNormalizer::normalizePort()
     */
    public function getPort(): ?int
    {
        return (UriNormalizer::getDefault())->normalizePort($this->port, $this->scheme);
    }

    /**
     * Return the path component of the URI or an empty string.
     *
     * @return string
     * This method returns the path as it was provided, or an empty string if not set.
     * If the path is not set, it returns an empty string.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Return the normalized query component of the URI or an empty string.
     *
     * @return string
     * This method returns the query string as it was provided, or an empty string if not set.
     * If the query string is not set, it returns an empty string.
     */
    public function getQuery(): string
    {
        return $this->query ?? '';
    }

    /**
     * Return the query parameters of the URI as an associative array.
     *
     * @return array
     * This method parses the query string if it has not been parsed yet.
     */
    public function getQueryParams(): array
    {
        if ($this->query === null) {
            return [];
        }
        if ($this->params === null) {
            parse_str($this->query ?? '', $this->params);
        }
        return $this->params;
    }

    /**
     * Return the normalized fragment of the URI string.
     *
     * @return string
     * This method returns the fragment component of the URI or an empty string if not set.
     */
    public function getFragment(): string
    {
        return $this->fragment ?? '';
    }

    /**
     * Return an instance with the specified scheme component.
     *
     * @param string $scheme
     * @throws \InvalidArgumentException
     * This method throws an exception if the scheme is invalid or not allowed.
     * @return \Psr\Http\Message\UriInterface
     * This method returns a new instance with the specified scheme.
     * The scheme is normalized to lowercase and must conform to the URI specification.
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
     * @param string $user
     * The user name to use for authority or an empty string.
     * @param ?string $password
     * The password associated with $user or null.
     * @return \Psr\Http\Message\UriInterface
     * This method returns a new instance with the specified user information.
     * If the password is not provided, only the user name is included.
     * If both user and password are empty, the user info is set to an empty string.
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
     * @param string $host The host to use with the new instance or an empty string.
     * @throws \InvalidArgumentException
     * This method throws an exception if the host is invalid or not allowed.
     * @return \Psr\Http\Message\UriInterface
     * This method returns a new instance with the specified host.
     * The host is normalized to ensure it conforms to the URI specification.
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
     * @param mixed $port The port to use with the new instance or null.
     * @throws \InvalidArgumentException
     * This method throws an exception if the port is not null and is not a valid port number (0-65535).
     * @return \Psr\Http\Message\UriInterface
     * This method returns a new instance with the specified port.
     * If the port is not null, it must be a valid port number (0-65535).
     * If the port is null, it indicates that no port is specified.
     * If the port is the standard port for the scheme, it will be normalized to null.
     */
    public function withPort(?int $port): self
    {
        if ($port !== null && !(UriValidator::getDefault())->validatePort($port)) {
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
     * @param string $path
     * The path to use with the new instance.
     * @return \Psr\Http\Message\UriInterface
     * This method returns a new instance with the specified path.
     * The path is normalized to ensure it conforms to the URI specification.
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
     * @param string $query
     * The query string to use with the new instance.
     * It can be empty, in which case the query component will be set to an empty string.
     * @return static
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
     * Return an instance with the specified query paramenters.
     *
     * @param array $params
     * The query parameters to use with the new instance.
     * @return \Psr\Http\Message\UriInterface
     * This method returns a new instance with the specified query parameters.
     * The query string is normalized to ensure it conforms to the URI specification.
     * If the parameters are empty, the query component will be set to an empty string.
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
     * Return an instance with the specified fragrament component.
     *
     * @param string $fragment
     * The fragment to use with the new instance.
     * It can be an empty string, in which case the fragment component will be set to an empty string.
     * @throws \InvalidArgumentException
     * This method throws an exception if the fragment is invalid.
     * @return \Psr\Http\Message\UriInterface
     * This method returns a new instance with the specified fragment.
     * The fragment is normalized to ensure it conforms to the URI specification.
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
     * An opaque URI has a scheme but no authority component.
     * @return bool
     * An opaque URI is one where the path does not start with a slash and there is no authority component.
     * This means the URI is not hierarchical and does not have a host, port, or user info.
     */
    public function isOpaque(): bool
    {
        return $this->scheme
            && !$this->getAuthority()
            && strpos($this->path, '/') !== 0;
    }

    /**
     * Check if the URI is absolute.
     * An absolute URI has a scheme and a host component.
     * @return bool
     * Returns true if the URI is absolute, false otherwise.
     * An absolute URI is one that includes a scheme (like 'http', 'https', 'ftp', etc.) and a host (like 'example.com').
     * This means the URI can be resolved independently of any base URI.
     * @see https://tools.ietf.org/html/rfc3986#section-4.3
     * @see https://url.spec.whatwg.org/#absolute-url-string
     */
    public function isAbsolute(): bool
    {
        return $this->scheme !== '' && $this->host !== '';
    }

    /**
     * Resolve a relative URI against this base URI.
     * This method returns a new instance with the resolved URI.
     * The base URI must be absolute, otherwise an exception is thrown.
     * The instance calling this method is considered the base URI, 
     * and the provided URI is the relative URI to resolve against it.
     * @see https://tools.ietf.org/html/rfc3986#section-5.2
     * @param \Psr\Http\Message\UriInterface $uri
     * The relative URI to resolve against this base URI.
     * @throws \InvalidArgumentException
     * If the base URI is not absolute, an exception is thrown.
     * If the relative URI has a scheme, it is returned as is.
     * @return UriInterface
     * Returns a new instance with the resolved URI.
     * If the relative URI has a host, it is resolved with the scheme of the base URI.
     * If the relative URI has no host, it is resolved with the scheme and host of the base URI.
     */
    public function resolve(UriInterface $uri): self
    {
        if (!$this->isAbsolute()) {
            throw new \InvalidArgumentException("Base URI must be absolute.");
        }
        if ($uri->getScheme() !== '') {
            return $uri;
        }
        if ($uri->getHost() !== '') {
            return $uri->withScheme($this->scheme);
        }
        $clone = clone $uri;
        $clone->scheme = $this->scheme;
        $clone->host = $this->host;
        $path = $uri->getPath();
        if (isset($path[1]) && $path[0] === '/') {
            $clone->path = $path;
            return $clone;
        }
        $clone->path = (UriNormalizer::getDefault())->normalizePath("{$this->path}/$path");
        return $clone;
    } 
}
