<?php

declare(strict_types=1);

namespace Pac\LeanHttp\Uri;

use Pac\LeanHttp\SchemePort;

class UriNormalizer
{
    /**
     * Default normalizer instance.
     * This instance is used when no specific normalizer is provided.
     * It is initialized with default settings: punny code enabled and query sorting disabled.
     * @var UriNormalizer|null
     */
    private static ?UriNormalizer $defaultNormalizer = null;

    /**
        * Constructor to initialize the URI normalizer.
     * @param bool $forcePunnyCode
     * If true, punycode will be used for IDN normalization.
     * If false, the hostname will be normalized to lowercase without punycode.
     * @param bool $sortQuery
     * If true, the query parameters will be sorted alphabetically.
     * If false, the query parameters will remain in the order they were provided.
     */
    public function __construct(
        public readonly bool $forcePunnyCode = true,
        public readonly bool $sortQuery = false
    ) {
    }

    /**
     * Return the default UriNormalizer instance.
     * @return self
     * This method returns a singleton instance of the UriNormalizer class.
     * If the instance does not exist, it will be created with default settings.
     */
    public static function getDefault(): self
    {
        self::$defaultNormalizer ??= new self();

        return self::$defaultNormalizer;
    }

    /**
     * Return the specified string normalized following the RFC 3986 encoding rules, avoiding double encoding.
     *
     * This method:
     * - Decodes percent-encoded octets corresponding to unreserved characters.
     * - Normalizes hexadecimal digits in percent-encoding (e.g., converts "%3a" to "%3A").
     * - Ignores percent-encoding triplets that do not encode unreserved characters.
     *
     * Examples:
     * - If $original is "abc%20def", the method returns "abc%20def".
     * - If $original is "special&chars", the method returns "special%26chars".
     *
     * This method is not intended for normalizing an entire URL.
     * It encodes reserved characters ("/", ":", "?", and "#") and does not re-encode percent-encoded octets.
     * Unreserved characters (digits, ASCII letters, "-", ".", "_", and "~") are left unchanged.
     *
     * Can be used to normalize the fragment component or the
     *
     * @param string $original
     * This parameter is the original string that should be normalized.
     * It can contain any characters, including reserved characters and percent-encoded octets.
     * @return string
     * Returns the normalized string.
     * The returned string will have reserved characters encoded, and unreserved characters will remain unchanged.
     * Percent-encoded octets that correspond to unreserved characters will be decoded.
     */
    public function normalizeEncode(string $original): string
    {
        return rawurlencode(urldecode($original));
    }

    /**
     * Return the specified scheme normalized.
     * This method normalizes the scheme to lowercase and validates it.
     * RFC 3986: «Although schemes are case-insensitive, the canonical form is lowercase»
     *
     * @param string $scheme
     * This parameter is the scheme that should be normalized.
     * It should be a valid scheme according to RFC 3986.
     * Valid schemes include "http", "https", "ftp", "mailto", etc.
     * @throws \InvalidArgumentException
     * If the scheme is invalid, an InvalidArgumentException is thrown.
     * @return string
     * Returns the normalized scheme in lowercase.
     * The returned scheme will be in lowercase, ensuring consistency across URIs.
     * It will also be validated to ensure it conforms to the rules defined in RFC 3986.
     */
    public function normalizeScheme(string $scheme): string
    {
        if (! (UriValidator::getDefault())->validateScheme($scheme)) {
            throw new \InvalidArgumentException("Can't normalize invalid scheme.");
        }

        return strtolower($scheme);
    }

    /**
     * Return the specified IPv4 normalized.
     * RFC 791
     *   - "The decimal notation for each octet is a non-negative integer less than 256."
     *   - "The decimal notation MUST NOT include leading zeros."
     *   - "The decimal notation for each octet is a non-negative integer less than 256."
     *   - "The decimal notation MUST NOT include leading zeros."
     *   - "The decimal notation for each octet is a non-negative integer less than 256."
     *   - "The decimal notation MUST NOT include leading zeros."
     *   - "The decimal notation for each octet is a non-negative integer less than 256."
     *   - "The decimal notation MUST NOT include leading zeros."
     *
     * @param string $ipv4
     * This parameter is the IPv4 address that should be normalized.
     * @throws \InvalidArgumentException
     * @return string
     * Returns the normalized IPv4 address.
     * The returned IPv4 address will be in the format "x.x.x.x", where each octet is a non-negative integer less than 256.
     * Leading zeros in each octet will be removed, ensuring that the address is in its canonical form.
     * If the provided IPv4 address is invalid, an InvalidArgumentException will be thrown.
     */
    public function normalizeIpv4(string $ipv4): string
    {
        if (! (UriValidator::getDefault())->validateIpv4($ipv4)) {
            throw new \InvalidArgumentException("Invalid IPv4: $ipv4.");
        }

        return $this->normalizeIpv4WithoutValidation($ipv4);
    }

    /**
     * Normalize the specified IPv4 address without validation.
     *
     * This method normalizes the IPv4 address by removing leading zeros from each octet.
     * It does not perform any validation on the input.
     *
     * @param string $ipv4
     * This parameter is the IPv4 address that should be normalized.
     * It should be a valid IPv4 address in the format "x.x.x.x", where each "x" is a decimal number.
     * Each octet should be a non-negative integer less than 256.
     * Leading zeros in each octet will be removed, ensuring that the address is in its canonical form.
     * @return string
     * Returns the normalized IPv4 address.
     * The returned IPv4 address will be in the format "x.x.x.x", where each octet is a non-negative integer less than 256.
     * Leading zeros in each octet will be removed, ensuring that the address is in its canonical form.
     * If the provided IPv4 address is invalid, an InvalidArgumentException will be thrown.
     * @throws \InvalidArgumentException
     * If the provided IPv4 address is invalid, an InvalidArgumentException will be thrown.
     * This exception will be thrown if any octet is not a non-negative integer less than 256, or if it contains invalid characters.
     */
    public function normalizeIpv4WithoutValidation(string $ipv4): string
    {
        $octets = explode('.', $ipv4);
        foreach ($octets as &$octet) {
            $int = (int)$octet;
            if (($int == 0 && ! ctype_digit($octet)) || $int < 0 || $int > 255) {
                throw new \InvalidArgumentException("Invalid IPv4: $ipv4.");
            }
            $octet = (string)$int;
        }
        $normalized = join('.', $octets);
        assert(filter_var($normalized, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4));

        return $normalized;
    }

    /**
     * Normalized the specified IPv6 normalized.
     *
     * RFC 4291
     *   - "Leading zeros MUST be suppressed."
     *   - "The use of the symbol "::" MUST be used to its maximum capability."
     *   - «The symbol "::" MUST NOT be used to shorten just one 16-bit 0 field.»
     *   - "When the length of the consecutive 16-bit 0 fields are equal (i.e., 2001:db8:0:0:1:0:0:1), the first sequence of zero bits MUST be shortened."
     *   - «The characters "a", "b", "c", "d", "e", and "f" in an IPv6 address MUST be represented in lowercase.»
     * @param string $ipv6
     * This parameter is the IPv6 address that should be normalized.
     * It should be a valid IPv6 address in the format "x:x:x:x:x:x:x:x", where each "x" is a hexadecimal number.
     * Each block should be a non-negative integer less than 65536.
     * Leading zeros in each block will be removed, ensuring that the address is in its canonical form.
     * The "::" notation will be used to shorten consecutive blocks of zeros to their maximum capability.
     * The characters "a", "b", "c", "d", "e", and "f" in the IPv6 address will be represented in lowercase.
     * @throws \InvalidArgumentException
     * If the provided IPv6 address is invalid, an InvalidArgumentException will be thrown.
     * This exception will be thrown if any block is not a non-negative integer less than 65536, or if it contains invalid characters.
     * @return string
     * Returns the normalized IPv6 address.
     * The returned IPv6 address will be in the format "[x:x:x:x:x:x:x:x]", where each "x" is a hexadecimal number.
     * Leading zeros in each block will be removed, ensuring that the address is in its canonical form.
     * The "::" notation will be used to shorten consecutive blocks of zeros to their maximum capability.
     * The characters "a", "b", "c", "d", "e", and "f" in the IPv6 address will be represented in lowercase.
     * The returned address will be enclosed in square brackets, as per RFC 2732.
     */
    public function normalizeIpv6(string $ipv6): string
    {
        if (! (UriValidator::getDefault())->validateIpv6($ipv6)) {
            throw new \InvalidArgumentException("Invalid IPv6: $ipv6.");
        }

        return $this->normalizeIpv6WithoutValidation($ipv6);
    }

    /**
     * Normalize the specified IPv6.
     * This method normalizes the IPv6 address by removing leading zeros from each block,
     * using the "::" notation to shorten consecutive blocks of zeros to their maximum capability,
     * and ensuring that the characters "a", "b", "c", "d", "e", and "f" are represented in lowercase.
     * It does not perform any validation on the input.
     * @see https://www.rfc-editor.org/rfc/rfc4291
     * @see https://www.rfc-editor.org/rfc/rfc2732
     * @param string $ipv6
     * This parameter is the IPv6 address that should be normalized.
     * It should be a valid IPv6 address in the format "x:x:x:x:x:x:x:x", where each "x" is a hexadecimal number.
     * @return string
     * Returns the normalized IPv6 address.
     * The returned IPv6 address will be in the format "[x:x:x:x:x:x:x:x]", where each "x" is a hexadecimal number.
     * Leading zeros in each block will be removed, ensuring that the address is in its canonical form.
     * The "::" notation will be used to shorten consecutive blocks of zeros to their maximum capability.
     * The characters "a", "b", "c", "d", "e", and "f" in the IPv6 address will be represented in lowercase.
     * The returned address will be enclosed in square brackets, as per RFC 2732.
     * If the provided IPv6 address is invalid, an InvalidArgumentException will be thrown.
     */
    private function normalizeIpv6WithoutValidation(string $ipv6): string
    {
        if (function_exists('inet_pton')) {
            $packed = inet_pton($ipv6);
            if ($packed !== false) {
                $ntop = inet_ntop($packed);
                if ($ntop !== false) {
                    $normalized = strtolower($ntop);

                    return "[$normalized]";
                }
            }
        }

        // If inet_pton is not available or fails, we proceed with manual normalization
        $ipv6 = strtolower($ipv6);
        $parts = explode('::', $ipv6);
        if (count($parts) === 2) {
            $left = ($parts[0] === '') ? [] : explode(':', $parts[0]);
            $right = ($parts[1] === '') ? [] : explode(':', $parts[1]);
            $missing = 8 - (count($left) + count($right));
            $blocks = array_merge($left, array_fill(0, $missing, '0'), $right);
        } else {
            $blocks = explode(':', $ipv6);
        }

        // Normalize each block by removing leading zeros
        foreach ($blocks as &$block) {
            $block = ltrim($block, '0');
            if ($block === '') {
                $block = '0';
            }
        }

        // Find the longest sequence of consecutive zeros to replace with "::"
        $bestStart = -1;
        $bestLen = 0;
        $i = 0;
        while ($i < count($blocks)) {
            if ($blocks[$i] !== '0') {
                $i++;

                continue;
            }
            $start = $i;
            while ($i < count($blocks) && $blocks[$i] === '0') {
                $i++;
            }
            $len = $i - $start;
            if ($len > $bestLen) {
                $bestStart = $start;
                $bestLen = $len;
            }
        }

        // If we found a sequence of zeros, replace it with "::"
        if ($bestLen > 1) {
            array_splice($blocks, $bestStart, $bestLen, ['']);
            // Adjust the start and end of the blocks
            if ($bestStart === 0) {
                array_unshift($blocks, '');
            }
            if ($bestStart + $bestLen === 8) {
                array_push($blocks, '');
            }
        }

        $normalized = implode(':', $blocks);
        // Fail in case of an invalid IPv6 address (bug)
        assert(filter_var($normalized, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6));

        return "[$normalized]";
    }

    /**
     * Return the specified hostname normalized.
     * This method normalizes the hostname to lowercase and, if forcePunnyCode is true, converts it to punycode.
     *
     * RFC 1630: "Although host is case-insensitive, producers and normalizers should use lowercase for registered names and hexadecimal addresses for the sake of uniformity"
     * @see https://www.rfc-editor.org/rfc/rfc1630
     * RFC 3490: "The ASCII-compatible encoding (ACE) is used to represent non-ASCII characters in the domain name system (DNS)."
     * @see https://www.rfc-editor.org/rfc/rfc3490
     * @param string $host
     * This parameter is the hostname that should be normalized.
     * It can be a fully qualified domain name (FQDN) or an IP address.
     * The hostname can contain alphanumeric characters, hyphens, and dots.
     * If the hostname contains non-ASCII characters, it will be converted to punycode if forcePunnyCode is true.
     * @throws \InvalidArgumentException
     * If the provided hostname is invalid, an InvalidArgumentException will be thrown.
     * This exception will be thrown if the hostname does not conform to the rules defined in RFC 1034 and RFC 1123.
     * @return string
     * Returns the normalized hostname.
     * The returned hostname will be in lowercase, ensuring consistency across URIs.
     * If forcePunnyCode is true, the hostname will be converted to punycode if it contains non-ASCII characters.
     * If the hostname is a valid IPv4 or IPv6 address, it will be returned as is.
     * If the hostname is invalid, an InvalidArgumentException will be thrown.
     */
    public function normalizeHostname(string $host): string
    {
        if (! (UriValidator::getDefault())->validateHost($host)) {
            throw new \InvalidArgumentException("Can't normalize invalid host: $host.");
        }
        if ($this->forcePunnyCode) {
            if (! function_exists('idn_to_ascii')) {
                throw new \InvalidArgumentException('IDN to ASCII conversion is not supported on this system.');
            }
            $ascii = idn_to_ascii($host, IDNA_NONTRANSITIONAL_TO_ASCII | IDNA_USE_STD3_RULES);
            if ($ascii === false) {
                throw new \InvalidArgumentException("Invalid host: $host.");
            }
            $host = $ascii;
        }

        return strtolower($host);
    }

    /**
     * Return the specified host (hostname, IPv4 or IPv6) normalized.
     * This method normalizes the host to a canonical form.
     * It handles IPv4 and IPv6 addresses, as well as hostnames.
     * @param string $host
     * This parameter is the host that should be normalized.
     * It can be a hostname, an IPv4 address, or an IPv6 address.
     * The host can contain alphanumeric characters, hyphens, dots, and colons.
     * If the host is an IPv6 address, it should be enclosed in square brackets (e.g., "[2001:db8::1]").
     * If the host is an IPv4 address, it should be in the format "x.x.x.x", where each "x" is a decimal number.
     * If the host is a hostname, it can contain alphanumeric characters, hyphens, and dots.
     * @throws \InvalidArgumentException
     * If the provided host is invalid, an InvalidArgumentException will be thrown.
     * This exception will be thrown if the host does not conform to the rules defined in RFC 1034 and RFC 1123 for hostnames,
     * or if it is not a valid IPv4 or IPv6 address.
     * @see https://www.rfc-editor.org/rfc/rfc1034
     * @see https://www.rfc-editor.org/rfc/rfc1123
     * @return string The normalized host.
     * The returned host will be in a canonical form:
     * - If the host is an IPv4 address, it will be in the format "x.x.x.x", where each "x" is a decimal number without leading zeros.
     * - If the host is an IPv6 address, it will be in the format "[x:x:x:x:x:x:x:x]", where each "x" is a hexadecimal number without leading zeros.
     * - If the host is a hostname, it will be in lowercase and, if forcePunnyCode is true, converted to punycode.
     * If the host is invalid, an InvalidArgumentException will be thrown.
     */
    public function normalizeHost(string $host): string
    {
        if ($host[0] === '[') {
            if (! str_ends_with($host, ']')) {
                throw new \InvalidArgumentException("Invalid host: $host.");
            }
            $ip = substr($host, 1, -1);

            return $this->normalizeIpv6($ip);
        }
        if (preg_match('/^(?:\d{1,3}\.){3}\d{1,3}$/', $host) === 1) {
            return $this->normalizeIpv4($host);
        }
        if ((UriValidator::getDefault())->validateIpv6($host)) {
            return $this->normalizeIpv6($host);
        }

        return $this->normalizeHostname($host);
    }

    /**
     * Return a normalized user information from a specified user and, optionally, a password.
     * This method combines the user and password into a user info string.
     * If the password is not provided, only the user is included.
     * If both user and password are empty, an empty string is returned.
     * @param string $user
     * This parameter is the user that should be normalized.
     * It can be an alphanumeric string, and it may include special characters.
     * The user can be an email address or a simple username.
     * @param ?string $password
     * This parameter is the password that should be normalized.
     * It can be an alphanumeric string, and it may include special characters.
     * If the password is not provided (null), only the user will be included in the user info string.
     * If the password is an empty string, it will be included in the user info string as an empty password.
     * @return string
     * Returns the user info string in the format "user:password".
     * If the user is an empty string, an empty string is returned.
     * If the password is not provided (null), only the user is included in the user info string.
     * If both user and password are empty, an empty string is returned.
     * The user info string will be percent-encoded according to RFC 3986 rules.
     */
    public function normalizeUserInfo(
        string $user = '',
        ?string $password = null
    ): string {
        if ($user === '') {
            return '';
        }
        $userInfo = $this->normalizeEncode($user);
        if ($password !== null) {
            $userInfo .= ':' . $this->normalizeEncode($password);
        }

        return $userInfo;
    }

    /**
     * Return the specified port or null, if it's the standard port for the specified scheme.
     * This method normalizes the port number to ensure it is valid and not the default port for the specified scheme.
     * RFC 3986: "If a port is not specified, the default port for the scheme is implied."
     * @see https://www.rfc-editor.org/rfc/rfc3986
     * @param int|null $port
     * This parameter can be an integer representing the port number or null.
     * @param string $scheme
     * This parameter is the scheme that should be used to determine the default port.
     * It should be a valid scheme according to RFC 3986, such as "http", "https", "ftp", etc.
     * Valid schemes include "http", "https", "ftp", "mailto", etc.
     * @throws \InvalidArgumentException
     * If the port is not null and is not a valid port number, an InvalidArgumentException will be thrown.
     * This exception will be thrown if the port is not an integer between 0 and 65535, inclusive.
     * @return ?int
     * Returns the normalized port number or null.
     * If the port is null, it indicates that no port is specified, and the default port for the scheme is implied.
     * If the port is a valid integer between 0 and 65535, it will be returned as is.
     * If the port is the default port for the specified scheme, it will return null.
     * The default ports for common schemes are:
     * - "http": 80
     * - "https": 443
     * - "ftp": 21
     * - "mailto": 25
     * If the port is not the default port for the specified scheme, it will return the port number as an integer.
     * If the port is the default port for the specified scheme, it will return null.
     */
    public function normalizePort(?int $port, string $scheme): ?int
    {
        if ($port === null) {
            return null;
        }
        if (! (UriValidator::getDefault())->validatePort($port)) {
            throw new \InvalidArgumentException("Invalid port: $port.");
        }
        $schemePort = SchemePort::fromScheme($scheme);

        return $schemePort?->value !== $port
            ? $port
            : null;
    }

    /**
     * Return the specified path normalized.
     * This method normalizes the path according to the rules defined in RFC 3986.
     * It removes leading and trailing slashes, handles dot segments (e.g., ".", ".."), and encodes reserved characters.
     * RFC 3986: "A path is a sequence of characters that does not include a slash ("/") or a percent-encoded octet."
     * «the hexadecimal digits within a percent-encoding triplet (e.g., "%3a" versus "%3A") are case-insensitive and therefore should be normalized to use uppercase letters for the digits A-F»
     * "URIs should be normalized by decoding any percent-encoded octet that corresponds to an unreserved character"
     * @see Psr\Http\Message\UriInterface::getPath()
     * @see https://www.rfc-editor.org/rfc/rfc3986
     * @see https://www.rfc-editor.org/rfc/rfc3986#section-5.2.4
     * @see https://www.rfc-editor.org/rfc/rfc3986#section-5.2.3
     * @see https://www.rfc-editor.org/rfc/rfc3986#section-5.2.2
     * @see https://www.rfc-editor.org/rfc/rfc3986#section-5.2.1
     * @see https://www.rfc-editor.org/rfc/rfc3986#section-5.2
     * @see https://www.rfc-editor.org/rfc/rfc3986#section-5.2.5
     * @param string $path
     * This parameter is the path that should be normalized.
     * It can be a string representing a file path, a URL path, or any other path-like structure.
     * The path can include segments separated by slashes ("/"), and it may contain dot segments (e.g., ".", "..").
     * The path can also include reserved characters (e.g., "?", "#", "&") that need to be percent-encoded.
     * The path should not start or end with a slash unless it is the root path ("/").
     * If the path is empty, it will be treated as the root path ("/").
     * If the path is relative, it will be normalized accordingly.
     * @param bool $normalizeRelative
     * This parameter determines whether the path should be normalized as a relative path.
     * If true, the path will be normalized as a relative path, meaning it will not start with a leading slash.
     * If false, the path will be normalized as an absolute path, meaning it will start with a leading slash if it is not empty.
     * If the path is empty, it will be treated as the root path ("/") regardless of this parameter.
     * @return string
     * Returns the normalized path.
     * The returned path will be in a canonical form.
     */
    public function normalizePath(
        string $path,
        bool $normalizeRelative = false
    ): string {
        if ($path === '' || $path === '/') {
            return $normalizeRelative ? '/' : $path;
        }
        // Remove leading and trailing slashes
        $leadSlash = $normalizeRelative ? '/' : ($path[0] === '/' ? '/' : '');
        $tailSlash = substr($path, -1) === '/' ? '/' : '';
        $segments = [];
        $inputSegments = explode('/', $path);
        $index = 0;
        if ($leadSlash != '/' && ! $normalizeRelative) {
            // If the path is relative and does not start with a slash, we need to handle dot segments
            for (; isset($inputSegments[$index]); ++$index) {
                $segment = $inputSegments[$index];
                if ($segment === '' || $segment === '.') {
                    continue; // Skip empty segments and single dot segments
                }
                // If we encounter a double dot segment, we need to pop the last segment if it exists
                if ($segment === '..') {
                    $segments[] = '..';
                } elseif ($segment !== '.') {
                    break;
                }
            }
        }
        // Normalize the segments
        for (; isset($inputSegments[$index]); ++$index) {
            $segment = $inputSegments[$index];
            if ($segment === '.' || $segment === '') {
                continue; // Skip single dot segments and empty segments
            } elseif ($segment === '..') {
                array_pop($segments); // Remove the last segment if it exists
            } else {
                $segments[] = $this->normalizeEncode($segment);
            }
        }

        // If the path is relative and starts with a dot, we need to add a leading slash
        return $leadSlash . implode('/', $segments) . $tailSlash;
    }

    /**
     * Return a specified query normalized.
     * This method normalizes the query string according to the rules defined in RFC 3986.
     * It decodes percent-encoded octets that correspond to unreserved characters,
     * normalizes hexadecimal digits in percent-encoding, and sorts the query parameters if specified.
     * RFC 3986: "A query component is a sequence of characters that does not include a slash ("/") or a percent-encoded octet."
     * «the hexadecimal digits within a percent-encoding triplet (e.g., "%3a" versus "%3A") are case-insensitive and therefore should be normalized to use uppercase letters for the digits A-F»
     * "URIs should be normalized by decoding any percent-encoded octet that corresponds to an unreserved character"
     * The method also handles percent-encoded characters and ensures that the query string is in a canonical form.
     * @see Psr\Http\Message\UriInterface::getQuery()
     * @see https://www.rfc-editor.org/rfc/rfc3986
     * @param string $query
     * This parameter is the query string that should be normalized.
     * It can be a string containing key-value pairs separated by ampersands ("&").
     * The query string can include percent-encoded characters, and it may contain reserved characters (e.g., "?", "#", "&").
     * Each key-value pair should be in the format "key=value", and multiple pairs should be separated by ampersands ("&").
     * @return string
     * Returns the normalized query string.
     * The returned query string will be in a canonical form:
     * - Each key-value pair will be percent-encoded according to RFC 3986 rules.
     * - Reserved characters (e.g., "?", "#", "&") will be percent-encoded.
     * - Unreserved characters (digits, ASCII letters, "-", ".", "_", and "~") will remain unchanged.
     * - The query string will not contain any double ampersands ("&&") or empty key-value pairs.
     * - If the query string is empty, an empty string will be returned.
     * - If the query string contains multiple key-value pairs, they will be sorted alphabetically by key if $sortQuery is true.
     * - If the query string contains duplicate keys, they will be included in the order they were provided.
     * - If the query string contains empty key-value pairs, they will be included as "key=".
     */
    public function normalizeQuery(string $query): string
    {
        $pairs = explode('&', $query);
        if ($this->sortQuery) {
            sort($pairs, SORT_STRING);
        }
        $encodedPairs = [];
        foreach ($pairs as $pair) {
            if ($pair === '') {
                continue;
            }
            $parts = explode('=', $pair, 2);
            $encodedKey = $this->normalizeEncode($parts[0] ?? '');
            $encodedValue = $this->normalizeEncode($parts[1] ?? '');
            if ($encodedKey === '') {
                continue;
            }
            $encodedPairs[] = "$encodedKey=$encodedValue";
        }

        return implode('&', $encodedPairs);
    }
}
