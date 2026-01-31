<?php

declare(strict_types=1);

namespace Pac\LeanHttp\Uri;

/**
 * URI parser: parses URI strings into their components.
 * This class is used to parse URIs into their components such as scheme, host, port, path, query, and fragment.
 * It provides methods to extract these components from a URI string.
 */
class UriParser
{
    /**
     * Constructor to initialize the URI parser.
     */
    public function __construct()
    {
    }

    /**
     * Parses a URI string into its components.
     * This method uses PHP's `parse_url` function to extract the components of the URI.
     * It returns an associative array containing the components of the URI.
     * If the URI is invalid, it throws an `InvalidArgumentException`.
     * @param string $uri
     * The URI string to parse (e.g., 'http://example.com/path?query#fragment').
     * The URI should be a valid absolute URI.
     * If the URI is relative, it will be resolved against the base URI provided in the constructor.
     * @return array<string, int|string|null>
     * Returns an associative array with the components of the URI.
     * The array may contain keys like 'scheme', 'host', 'port', 'path', 'query', and 'fragment'.
     * If the URI is invalid, it throws an `InvalidArgumentException`.
     */
    public function parse(string $uri): array
    {
        $parsed = $this->parseComponent($uri);

        return is_array($parsed) ? $parsed : [];
    }

    /**
     * Parses a specific component of a URI.
     * This method uses PHP's `parse_url` function to extract a specific component of the URI.
     * It can return the scheme, user info, host, port, path, query, or fragment of the URI.
     * If the component type is not specified, it returns all components.
     * If the URI is invalid, it throws an `InvalidArgumentException`.
     * @param string $uri
     * The URI string to parse (e.g., 'http://example.com/path?query#fragment').
     * The URI should be a valid absolute URI.
     * If the URI is relative, it will be resolved against the base URI provided in the constructor.
     * @param int $componentType
     * The type of component to parse.
     * This can be one of the constants defined in `PHP_URL_*`, such as `PHP_URL_SCHEME`, `PHP_URL_HOST`, etc.
     * If set to -1, it will return all components as an associative array.
     * @throws \InvalidArgumentException
     * If the URI is invalid or cannot be parsed, an `InvalidArgumentException` is thrown.
     * @return array<string, int|string|null>|string|int|null
     * Returns the parsed component of the URI.
     * If `$componentType` is -1, it returns an associative array with all components.
     * If `$componentType` is specified, it returns the specific component as a string, integer, or null.
     */
    private function parseComponent(string $uri, int $componentType = -1): array|string|int|null
    {
        $parsed = parse_url($uri, $componentType);
        if ($parsed === false) {
            throw new \InvalidArgumentException("Invalid URI.");
        }

        return $parsed;
    }

    /**
     * Parses the scheme from a URI.
     * This method extracts the scheme (e.g., 'http', 'https') from the URI.
     * It uses the `parseComponent` method to get the scheme component.
     * The scheme is returned in lowercase.
     * If the scheme is not present, it returns an empty string.
     * @param string $uri
     * The URI string to parse (e.g., 'http://example.com/path?query#fragment').
     * The URI should be a valid absolute URI.
     * @return string
     * Returns the scheme of the URI in lowercase.
     * If the scheme is not present, it returns an empty string.
     */
    public function parseScheme(string $uri): string
    {
        $scheme = $this->parseComponent($uri, PHP_URL_SCHEME);

        return is_string($scheme) ? strtolower($scheme) : '';
    }

    /**
     * Parses the user info from a URI.
     * This method extracts the user info (username and password) from the URI.
     * It uses the `parseComponent` method to get the user and password components.
     * The user info is returned in the format "username:password".
     * If the user info is not present, it returns an empty string.
     * @param string $uri
     * The URI string to parse (e.g., 'user:pass')
     * @return string
     * Returns the user info of the URI in the format "username:password".
     */
    public function parseUserInfo(string $uri): string
    {
        $user = $this->parseComponent($uri, PHP_URL_USER);
        $pass = $this->parseComponent($uri, PHP_URL_PASS);
        if ($user === null) {
            return '';
        }

        $userStr = is_string($user) ? $user : "";
        $passStr = is_string($pass) ? $pass : "";

        return $pass !== null ? "$userStr:$passStr" : $userStr;
    }

    /**
     * Parses the host from a URI.
     * This method extracts the host (e.g., 'example.com') from the URI.
     * It uses the `parseComponent` method to get the host component.
     * The host is returned in lowercase.
     * If the host is not present, it returns an empty string.
     * @param string $uri
     * The URI string to parse (e.g., 'http://example.com/path?query#fragment').
     * The URI should be a valid absolute URI.
     * @return string
     * Returns the host of the URI in lowercase.
     */
    public function parseHost(string $uri): string
    {
        $host = $this->parseComponent($uri, PHP_URL_HOST);

        return is_string($host) ? strtolower($host) : '';
    }

    /**
     * Parses the port from a URI.
     * This method extracts the port number from the URI.
     * It uses the `parseComponent` method to get the port component.
     * @param string $uri
     * The URI string to parse (e.g., 'http://example.com:8080/path?query#fragment').
     * The URI should be a valid absolute URI.
     * Returns the port number as an integer.
     * If the port is not present, it returns null.
     * @return int|null
     */
    public function parsePort(string $uri): ?int
    {
        /** @var int|null $port */
        $port = $this->parseComponent($uri, PHP_URL_PORT);

        return $port;
    }

    /**
     * Parses the path from a URI.
     * This method extracts the path (e.g., '/path/to/resource') from the URI.
     * It uses the `parseComponent` method to get the path component.
     * The path is returned as a string.
     * If the path is not present, it returns an empty string.
     * @param string $uri
     * The URI string to parse (e.g., 'http://example.com/path?query#fragment').
     * The URI should be a valid absolute URI.
     * Returns the path of the URI as a string.
     * If the path is not present, it returns an empty string.
     * @return string
     */
    public function parsePath(string $uri): string
    {
        /** @var string|null $path */
        $path = $this->parseComponent($uri, PHP_URL_PATH);

        return $path ?? '';
    }

    /**
     * Parses the query string from a URI.
     * This method extracts the query string (e.g., 'key=value&key2=value2') from the URI.
     * It uses the `parseComponent` method to get the query component.
     * The query string is returned as a string.
     * If the query string is not present, it returns null.
     * @param string $uri
     * The URI string to parse (e.g., 'http://example.com/path?query=string#fragment').
     * The URI should be a valid absolute URI.
     * Returns the query string of the URI as a string.
     * If the query string is not present, it returns null.
     * @return string|null
     */
    public function parseQuery(string $uri): ?string
    {
        /** @var string|null $query */
        $query = $this->parseComponent($uri, PHP_URL_QUERY);

        return $query;
    }

    /**
     * Parses the fragment from a URI.
     * This method extracts the fragment (e.g., 'section1') from the URI.
     * It uses the `parseComponent` method to get the fragment component.
     * The fragment is returned as a string.
     * If the fragment is not present, it returns null.
     * @param string $uri
     * The URI string to parse (e.g., 'http://example.com/path?query#fragment').
     * Returns the fragment of the URI as a string.
     * If the fragment is not present, it returns null.
     * @return string|null
     */
    public function parseFragment(string $uri): ?string
    {
        /** @var string|null $fragment */
        $fragment = $this->parseComponent($uri, PHP_URL_FRAGMENT);

        return $fragment;
    }
}
