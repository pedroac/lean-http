<?php

declare(strict_types=1);

namespace Pac\LeanHttp\Uri;

/**
 * URI validator: validates URI strings against RFC standards.
 * This class is used to validate URIs according to RFC 3986 and other relevant standards.
 * It provides methods to validate absolute URIs, schemes, hosts, ports, paths, queries, and fragments.
 * The class can be configured to be strict about RFC compliance or more lenient.
 */
class UriValidator
{
    /**
     * Reserved characters in URIs as per RFC 3986.
     * These characters are reserved for special purposes and should not be used in certain parts of a URI.
     */
    public const RESERVED = ':/?#[]@!$&\'()*+,;=';

    /**
     * Maximum port number as per RFC 3986.
     * The valid range for port numbers is from 0 to 65535.
     * Ports outside this range are considered invalid.
     */
    public const MAX_PORT = 65535;

    /**
     * Default instance of the UriValidator.
     * This instance is used when no specific configuration is provided.
     * It uses strict RFC validation by default.
     * @var UriValidator|null
     */
    private static ?UriValidator $default = null;

    /**
     * Constructor to initialize the URI validator.
     * @param bool $strictRFC
     * Whether to enforce strict RFC compliance. Defaults to true.
     * If set to false, the validator will be more lenient in its checks.
     */
    public function __construct(
        public readonly bool $strictRFC = true
    ) {
    }

    /**
     * Get the default instance of the UriValidator.
     * This method returns a singleton instance of the UriValidator.
     * If no instance exists, it creates a new one with strict RFC validation enabled.
     * This is useful for cases where a consistent validation strategy is needed across the application.
     * @return UriValidator
     * Returns the default instance of the UriValidator.
     * If the default instance is not set, it creates a new instance with strict RFC validation enabled.
     */
    public static function getDefault(): UriValidator
    {
        self::$default ??= new self();

        return self::$default;
    }

    /**
     * Validate an absolute URI.
     * This method checks if the provided URI is a valid absolute URI according to RFC 3986.
     * An absolute URI must have a scheme and a host, and may include a port, path, query, and fragment.
     * If any component is invalid, it returns false.
     * @param string $uri
     * The URI string to validate (e.g., 'http://example.com/path?query#fragment').
     * @return bool
     * Returns true if the URI is a valid absolute URI, false otherwise.
     * The method checks the following components:
     * - Scheme: Must be a valid URI scheme (e.g., 'http', 'https').
     * - Host: Must be a valid domain name or IP address.
     * - Port: If present, must be a valid integer within the range of 0 to 65535.
     */
    public function validateAbsoluteUri(string $uri): bool
    {
        $components = parse_url($uri);
        if (! $components
            || ! isset($components['scheme'])
            || ! isset($components['host'])
            || ! $this->validateScheme($components['scheme'])
            || ! $this->validateHost($components['host'])
        ) {
            return false;
        }
        if (isset($components['port']) && ! $this->validatePort((int)$components['port'])) {
            return false;
        }
        if (isset($components['path']) && ! $this->validatePath($components['path'])) {
            return false;
        }
        if (isset($components['query']) && ! $this->validateQuery($components['query'])) {
            return false;
        }
        if (isset($components['fragment']) && ! $this->validateFragment($components['fragment'])) {
            return false;
        }

        return true;
    }

    /**
     * Check if a string contains reserved characters.
     * This method checks if the provided string contains any reserved characters as defined by RFC 3986.
     * Reserved characters are those that have special meanings in URIs and should not be used in certain parts of a URI.
     * The reserved characters include ':', '/', '?', '#', '[', ']', '@', '!', '$', '&', '\'', '(', ')', '*', '+', ',', ';', and '='.
     * If the string contains any of these characters, it returns true; otherwise, it returns false.
     * @param string $string
     * The string to check for reserved characters.
     * @return bool
     * Returns true if the string contains reserved characters, false otherwise.
     * If the string contains any reserved characters, it will return true; otherwise, it will return false.
     */
    public function hasReservedChar(string $string): bool
    {
        return strcspn($string, self::RESERVED) !== strlen($string);
    }

    /**
     * Validate the scheme of a URI.
     * This method checks if the provided scheme is a valid URI scheme according to RFC 3986.
     * A valid scheme must start with an alphabetic character, followed by alphanumeric characters or '+' or '-' or '.'.
     * If the scheme is valid, it returns true; otherwise, it returns false.
     * @param string $scheme
     * The scheme to validate (e.g. 'http', 'https').
     * @return bool
     * Returns true if the scheme is valid, false otherwise.
     * It checks if the scheme can be used in a valid URL by constructing a test URL with 'a' as the host.
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-3.1
     */
    public function validateScheme(string $scheme): bool
    {
        return preg_match('/^[a-z][a-z0-9+\-.]*$/i', $scheme) === 1;
    }

    /**
     * Validate the host of a URI.
     * This method checks if the provided host is a valid domain name or IP address.
     * A valid host can be an IPv4 address, an IPv6 address enclosed in square brackets, or a domain name.
     * If the host is a valid IPv4 address, it returns true.
     * If the host is an IPv6 address, it checks if it is enclosed in square brackets and validates it.
     * If the host is a domain name, it checks if it is a valid hostname.
     * If the host is valid, it returns true; otherwise, it returns false.
     * @param string $host
     * The host to validate.
     * @return bool
     * Returns true if the host is valid, false otherwise.
     * The method checks if the host is a valid IPv4 address, an IPv6 address enclosed in square brackets, or a valid domain name.
     */
    public function validateHost(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return true;
        }
        if ($host[0] === '[' && str_ends_with($host, ']')) {
            $ipv6 = substr($host, 1, -1);

            return filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        }

        return filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    /**
     * Validate an IPv4 address.
     * This method checks if the provided string is a valid IPv4 address.
     * The method does not check if the IPv4 address is reachable or exists; it only checks the format.
     * @param string $ipv4
     * The IPv4 address to validate.
     * @return bool
     * Returns true if the IPv4 address is valid, false otherwise.
     */
    public function validateIpv4(string $ipv4): bool
    {
        return filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /**
     * Validate an IPv6 address.
     * This method checks if the provided string is a valid IPv6 address.
     * @param string $ipv6
     * The IPv6 address to validate.
     * @return bool
     * Returns true if the IPv6 address is valid, false otherwise.
     * If the IPv6 address is valid, it returns true, indicating it conforms to the standard format.
     * If the IPv6 address is not valid, it returns false, indicating it does not conform to the standard format.
     * @see https://datatracker.ietf.org/doc/html/rfc4291#section-2.2
     * @see https://datatracker.ietf.org/doc/html/rfc5952
     */
    public function validateIpv6(string $ipv6): bool
    {
        return filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    /**
     * Validate a port number.
     * This method checks if the provided port number is within the valid range for ports.
     * @param int $port
     * The port number to validate.
     * @return bool
     * Returns true if the port number is valid, false otherwise.
     * If the port number is less than 0 or greater than 65535, it returns false, indicating it is invalid.
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-3.2.3
     */
    public function validatePort(int $port): bool
    {
        return $port >= 0 && $port <= static::MAX_PORT;
    }

    /**
     * Validate the path of a URI.
     * This method checks if the provided path is a valid URI path according to RFC 3986.
     * If strict RFC validation is enabled, it will enforce the full set of rules for valid paths.
     * If strict RFC validation is disabled, it will allow some reserved characters in the path.
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-3.3
     * @param string $path
     * The path to validate.
     * @return bool
     * Returns true if the path is valid, false otherwise.
     */
    public function validatePath(string $path): bool
    {
        if (! $this->strictRFC) {
            return strcspn($path, '?#') === 0;
        }

        return preg_match(
            '/^(\/?([a-z0-9\-\.\_\~\!\$\&\'\(\)\*\+\,\;\=\:\@]|(%[a-f0-9]{2}))*)*$/i',
            $path
        ) === 1;
    }

    /**
     * Validate the query component of a URI.
     * This method checks if the provided query string is a valid URI query according to RFC 3986.
     * This method is useful for validating the query component of a URI to ensure it conforms to the standards defined in RFC 3986.
     * The method can be configured to be strict about RFC compliance or more lenient.
     * If strict RFC validation is enabled, it will enforce the full set of rules for valid queries.
     * If strict RFC validation is disabled, it will allow some reserved characters in the query.
     * @param string $query
     * The query string to validate.
     * @return bool
     * Returns true if the query is valid, false otherwise.
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-3.4
     */
    public function validateQuery(string $query): bool
    {
        if (! $this->strictRFC) {
            return strcspn($query, '#') === 0;
        }

        return preg_match(
            '/^([a-z0-9\-\.\_\~\!\$\&\'\(\)\*\+\,\;\=\:\@\/\?]|(%[a-f0-9]{2,2}))*$/i',
            $query
        ) === 1;
    }

    /**
     * Validate the fragment of a URI.
     * This method checks if the provided fragment is a valid URI fragment according to RFC 3986.
     * The method can be configured to be strict about RFC compliance or more lenient.
     * If strict RFC validation is enabled, it will enforce the full set of rules for valid fragments.
     * @param string $fragment
     * The fragment to validate.
     * @return bool
     * Returns true if the fragment is valid, false otherwise.
     * The method uses a regular expression to check the format of the fragment.
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-3.5
     */
    public function validateFragment(string $fragment): bool
    {
        if (! $this->strictRFC) {
            return true;
        }

        return preg_match(
            '/^([a-z0-9\-\.\_\~\!\$\&\'\(\)\*\+\,\;\=\:\@\/\?]|(%[a-f0-9]{2}))*/i',
            $fragment
        ) === 1;
    }
}
