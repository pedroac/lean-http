<?php

declare(strict_types=1);

namespace Pac\LeanHttp\Uri;

use RuntimeException;

/**
 * URI builder: generates URI strings from its components.
 * This class is used to build URIs from their components.
 */
class UriBuilder
{
    /**
     * Constructor to initialize the URI components.
     * @param string $scheme
     * The URI scheme (e.g., 'http', 'https').
     * @param string $host
     * The URI host (e.g., 'example.com').
     * @param string $path
     * The URI path (e.g., '/path/to/resource').
     * @param string|array<int|string, mixed>|null $query
     * The URI query string or an associative array of query parameters.
     * @param ?string $fragment
     * The URI fragment (e.g., 'section1').
     * @param ?int $port
     * The URI port number (e.g., 80, 443). If null, the default port for the scheme is used.
     * @param string $user
     * The URI user info (e.g., 'username').
     * @param ?string $password
     * The URI password (e.g., 'password'). If null, no password is included.
     */
    public function __construct(
        public string $scheme = '',
        public string $host = '',
        public string $path = '',
        public string|array|null $query = null,
        public ?string $fragment = null,
        public ?int $port = null,
        public string $user = '',
        public ?string $password = null
    ) {
    }

    /**
     * Create a new UriBuilder instance from an associative array of URI parts.
     * The array should contain keys for 'scheme', 'host', 'path', 'query', 'fragment', 'port', 'user', and 'pass'.
     * If a key is not present, a default value will be used (e.g., empty string for 'scheme', 'host', 'path', and 'user').
     * @param array<string, mixed> $parts
     * The array should contain the following keys:
     * - 'scheme': The URI scheme (e.g., 'http', 'https').
     * - 'host': The URI host (e.g., 'example.com').
     * - 'path': The URI path (e.g., '/path/to/resource').
     * - 'query': The URI query string or an associative array of query parameters.
     * - 'fragment': The URI fragment (e.g., 'section1').
     * - 'port': The URI port number (e.g., 80, 443). If null, the default port for the scheme is used.
     * - 'user': The URI user info (e.g., 'username').
     * - 'pass': The URI password (e.g., 'password'). If null, no password is included.
     * @return self
     * Returns a new instance of UriBuilder initialized with the provided parts.
     */
    public static function fromArray(array $parts): self
    {
        return new self(
            $parts['scheme'] ?? '',
            $parts['host'] ?? '',
            $parts['path'] ?? '',
            $parts['query'] ?? null,
            $parts['fragment'] ?? null,
            $parts['port'] ?? null,
            $parts['user'] ?? '',
            $parts['pass'] ?? null
        );
    }

    /**
     * Validate the URI components.
     * This method checks if the URI components are valid according to the URI standards.
     * If any component is invalid, it returns false.
     * @return bool
     * Returns true if all components are valid, false otherwise.
     */
    public function isValid(): bool
    {
        $validator = UriValidator::getDefault();

        return $validator->validateScheme($this->scheme)
            && $validator->validateHost($this->host)
            && ($this->port === null || $validator->validatePort($this->port))
            && $validator->validatePath($this->path)
            && (is_string($this->query) && $validator->validateQuery($this->query))
            && ($this->fragment === null || $validator->validateFragment($this->fragment));
    }

    /**
     * Build the user info part of the URI.
     * This method combines the user and password into a user info string.
     * If the password is not provided, only the user is included.
     * If both user and password are empty, an empty string is returned.
     * @return string
     * Returns the user info string in the format 'user:password'.
     */
    public function buildUserInfo(): string
    {
        return (UriNormalizer::getDefault())
            ->normalizeUserInfo($this->user, $this->password);
    }

    /**
     * Build the authority part of the URI.
     * This method combines the user info, host, and port into an authority string.
     * @return string
     * Returns the authority string in the format 'user:password@host:port'.
     * If the user info is empty, it returns 'host:port' or just 'host' if no port is provided.
     * If the host is empty, it returns an empty string.
     */
    public function buildAuthority(): string
    {
        if (! $this->host) {
            return '';
        }
        $authority = '';
        if ($this->user !== '') {
            $authority = $this->buildUserInfo() . '@';
        }
        $authority .= $this->host;
        if ($this->port !== null) {
            $authority .= ":$this->port";
        }

        return $authority;
    }

    /**
     * Build a string that should represent a URL without validating or sanitation the components.
     * It's useful to avoid repeated validations or sanitations.
     * @return string
     * Returns a raw URL string built from the URI components.
     */
    public function buildRaw(): string
    {
        $string = '';
        if ($this->scheme !== '') {
            $string .= "{$this->scheme}://";
        }
        $string .= $this->buildAuthority();
        if ($this->path !== '') {
            if ($string) {
                $string .= $this->path[0] === '/' ? $this->path : "/{$this->path}";
            } else {
                $string .= $this->path;
            }
        }
        if ($this->query !== null) {
            $string .= '?' . (
                is_array($this->query)
                    ? http_build_query($this->query, '', '&', \PHP_QUERY_RFC3986)
                    : $this->query
            );
        }
        if ($this->fragment !== null) {
            $string .= "#{$this->fragment}";
        }

        return $string;
    }

    /**
     * Build a safe URL from the URI components.
     * If the scheme or host is invalid, it throws a RuntimeException.
     * @return string
     * Returns a safe URL string built from the URI components.
     * @throws \RuntimeException
     * If the scheme or host is invalid, a RuntimeException is thrown.
     */
    public function build(): string
    {
        $validator = UriValidator::getDefault();
        if (
            ! $validator->validateScheme($this->scheme)
            || ! $validator->validateHost($this->host)
        ) {
            throw new \Pac\LeanHttp\Exception\UriException("Can't build a safe URL from an invalid scheme or host.");
        }
        $normalizer = UriNormalizer::getDefault();

        return (new self(
            $this->scheme,
            $this->host,
            $normalizer->normalizePath($this->path),
            is_string($this->query) ? $normalizer->normalizeQuery($this->query) : $this->query,
            $this->fragment !== null ? $normalizer->normalizeEncode($this->fragment) : null,
            $this->port,
            $this->user,
            password: $this->password
        ))->buildRaw();
    }
}
