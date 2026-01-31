<?php

declare(strict_types=1);

namespace Pac\LeanHttp;

use Pac\LeanHttp\Exception\HeaderException;
use Pac\LeanHttp\Exception\ParseException;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Represents an HTTP message, which can be a request or a response.
 * This class implements the MessageInterface from PSR-7.
 * It provides methods to manage headers, protocol version, and body.
 */
class Message implements MessageInterface
{
    /**
     * Protocol version used in case it's not defined in the constructor or $_SERVER.
     */
    public const DEFAULT_PROTOCOL_VERSION = '1.0';

    /**
     * Valid HTTP protocol versions.
     * This array contains the valid protocol versions that can be used in the message.
     * It includes '1.0', '1.1', '2', '2.0', and '3'.
     * These versions are commonly used in HTTP communication.
     */
    public const VALID_PROTOCOL_VERSIONS = [
        '1.0',
        '1.1',
        '2',
        '2.0',
        '3',
    ];

    /**
     * Header names (values) associated to their lower cased versions (keys).
     * It's used to access header values by case insensitive header names.
     * And preserve the header names passed from method arguments.
     * @var array<string, string>
     */
    private array $headerNames = [];

    /**
     * The HTTP protocol version.
     */
    private string $protocolVersion = self::DEFAULT_PROTOCOL_VERSION;

    /**
     * Headers stored as associative array with header names as keys and arrays of string values.
     * @var array<string, string[]>
     */
    private array $headers = [];

    /**
     * Constructor to initialize the message with a body, headers, and protocol version.
     *
     * @param StreamInterface $body The message body as a stream
     * @param array<string, string|string[]> $headers Associative array of headers (values can be strings or arrays of strings)
     * @param ?string $protocolVersion HTTP protocol version (e.g., '1.1') or null to auto-detect from $_SERVER
     */
    public function __construct(
        private StreamInterface $body,
        array $headers = [],
        ?string $protocolVersion = null
    ) {
        foreach ($headers as $name => $value) {
            $this->headers[$name] = \is_array($value) ? $value : [(string)$value];
            $this->headerNames[strtolower($name)] = $name;
        }
        if ($protocolVersion === null || $protocolVersion === '') {
            $serverProtocol = filter_input(\INPUT_SERVER, 'SERVER_PROTOCOL');
            if ($serverProtocol && ($pos = strpos($serverProtocol, '/')) !== false) {
                $protocolVersion = substr($serverProtocol, $pos + 1);
            } else {
                $protocolVersion = self::DEFAULT_PROTOCOL_VERSION;
            }
        }
        $this->protocolVersion = $protocolVersion;
    }

    /**
     * Returns the HTTP protocol version used in the message.
     *
     * @return string The HTTP protocol version (e.g., '1.0', '1.1')
     */
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    /**
     * Return an instance with the specified HTTP protocol version.
     *
     * @param string $version The HTTP protocol version to set (e.g., '1.0', '1.1')
     * @return static A new instance with the specified protocol version
     * @throws \InvalidArgumentException If the protocol version is invalid
     */
    public function withProtocolVersion(string $version): MessageInterface
    {
        if (! in_array($version, self::VALID_PROTOCOL_VERSIONS, true)) {
            throw new \InvalidArgumentException("Invalid HTTP protocol version: $version");
        }
        $cloned = clone $this;
        $cloned->protocolVersion = $version;

        return $cloned;
    }

    /**
     * Get all headers as an associative array.
     *
     * @return array<string, string[]> Associative array of headers where keys are header names and values are arrays of strings
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Check if a header exists by the given case-insensitive name.
     *
     * @param string $name Case-insensitive header field name
     * @return bool True if the header exists, false otherwise
     */
    public function hasHeader(string $name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    /**
     * Retrieve a message header value by the given case-insensitive name.
     *
     * @param string $name Case-insensitive header field name
     * @return string[] Array of string values for the given header, or empty array if header doesn't exist
     */
    public function getHeader(string $name): array
    {
        $originalName = $this->headerNames[strtolower($name)] ?? null;

        return $originalName !== null ? $this->headers[$originalName] : [];
    }

    /**
     * Retrieve a message header value as a single string, concatenated by commas.
     *
     * @param string $name Case-insensitive header field name
     * @return string Single string containing all values for the given header, concatenated by commas, or empty string if header doesn't exist
     */
    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    /**
     * Return an instance with the specified header.
     *
     * @param string $name Case-insensitive header field name to set
     * @param string|string[] $value Header value(s)
     * @return static A new instance with the specified header set
     * @throws \InvalidArgumentException For invalid header names or values
     */
    public function withHeader(string $name, $value): MessageInterface
    {
        $this->assertHeaderName($name);
        $this->assertHeaderValue($value);
        $cloned = clone $this;
        $cloned->headers[$name] = is_array($value) ? $value : [$value];
        $cloned->headerNames[strtolower($name)] = $name;

        return $cloned;
    }

    /**
     * Return an instance with the specified header added.
     *
     * @param string $name Case-insensitive header field name to add
     * @param string|string[] $value Header value(s)
     * @return static A new instance with the specified header added
     * @throws \RuntimeException If the value is not a string or an array of strings
     */
    public function withAddedHeader(string $name, $value): MessageInterface
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (! is_string($item)) {
                    throw new HeaderException("Invalid header value. Array values must be strings.");
                }
            }
        } elseif (! is_string($value)) {
            throw new HeaderException("Invalid header value. It must be a string or an array.");
        }
        $cloned = clone $this;
        $lowerCasedName = strtolower($name);
        if (! isset($cloned->headerNames[$lowerCasedName])) {
            $cloned->headers[$name] = [];
        }
        if (is_array($value)) {
            $cloned->headers[$name] = array_merge($cloned->headers[$name], $value);
        } else {
            $cloned->headers[$name][] = $value;
        }
        $cloned->headerNames[$lowerCasedName] = $name;

        return $cloned;
    }

    /**
     * Return an instance without the specified header.
     *
     * @param string $name Case-insensitive header field name to remove
     * @return static A new instance without the specified header
     */
    public function withoutHeader(string $name): MessageInterface
    {
        $cloned = clone $this;
        $lowerCased = strtolower($name);
        if (! isset($cloned->headerNames[$lowerCased])) {
            return $cloned;
        }
        $originalName = $this->headerNames[strtolower($name)];
        unset($cloned->headerNames[$lowerCased]);
        unset($cloned->headers[$originalName]);

        return $cloned;
    }

    /**
     * Get the message body as a stream.
     *
     * @return StreamInterface The body of the message as a stream
     */
    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    /**
     * Return an instance with the specified body.
     *
     * @param StreamInterface $body The body of the message as a stream
     * @return static A new instance with the specified body
     */
    public function withBody(StreamInterface $body): MessageInterface
    {
        $cloned = clone $this;
        $cloned->body = $body;

        return $cloned;
    }

    /**
     * Parse the body based on the Content-Type header.
     * This method analyzes the body of the message and returns it in a structured format based on the Content-Type header.
     *
     * If Content-Type is:
     * - `application/x-www-form-urlencoded` or `multipart/form-data`, an array is returned;
     * - `application/json`, an array is returned;
     * - `application/csv`, an array of arrays is returned;
     * - `application/xml`, 'text/xml' or 'text/html', an instance of \DomDocument is returned;
     * @return array<string|int, mixed>|object|null
     * Returns the parsed body in a structured format:
     * - An associative array for `application/x-www-form-urlencoded` or `multipart/form-data`.
     * - An associative array for `application/json`.
     * - An array of arrays for `text/csv`.
     * - An instance of \DomDocument for `text/xml`, `application/xml`, or `text/html`.
     * - If the body is empty, it returns null.
     * @throws \JsonException
     * If the body cannot be parsed as JSON, a JsonException is thrown.
     * @throws \RuntimeException
     * If the body cannot be parsed as CSV, XML, or HTML, a RuntimeException is thrown.
     * @throws \InvalidArgumentException
     * If the Content-Type header is not recognized or if the body cannot be parsed, an InvalidArgumentException is thrown.
     */
    public function parseBody(): array|object|null
    {
        $body = (string) $this->body;
        if (strlen($body) === 0) {
            return null;
        }
        $contentTypeParts = $this->parseContentType();
        $mainType = $contentTypeParts[0] ?? '';
        if (! empty($mainType)) {
            switch ($mainType) {
                case 'application/x-www-form-urlencoded':
                    parse_str($body, $result);

                    return $result;
                case 'multipart/form-data':
                    return filter_input_array(INPUT_POST) ?: [];
                case 'application/json':
                    return json_decode((string) $this->body, true, 512, JSON_THROW_ON_ERROR);
                case 'text/csv':
                    $rows = [];
                    $lines = explode("\n", (string) $this->body);
                    foreach ($lines as $lineNum => $line) {
                        if ($line === '' || $line === "\n") {
                            continue;
                        }
                        $csvRow = str_getcsv($line);
                        if (! is_array($csvRow)) {
                            throw new ParseException("Failed to parse CSV line {$lineNum}: invalid CSV format");
                        }
                        $rows[] = $csvRow;
                    }

                    return $rows;
                case 'text/xml':
                case 'application/xml':
                    $dom = new \DOMDocument();
                    $previousUseInternalErrors = libxml_use_internal_errors(true);

                    try {
                        libxml_set_external_entity_loader(function () {
                            return null;
                        });
                        if (! $dom->loadXML((string) $this->body, LIBXML_NONET)) {
                            $errors = libxml_get_errors();
                            libxml_clear_errors();
                            $errorMsg = $errors ? $errors[0]->message : 'Unknown XML parsing error';

                            throw new ParseException("Failed to parse XML body: $errorMsg");
                        }
                        libxml_set_external_entity_loader(null);

                        return $dom;
                    } finally {
                        libxml_use_internal_errors($previousUseInternalErrors);
                    }
                case 'text/html':
                    $dom = new \DOMDocument();
                    $previousUseInternalErrors = libxml_use_internal_errors(true);

                    try {
                        libxml_set_external_entity_loader(function () {
                            return null;
                        });
                        if (! $dom->loadHTML((string) $this->body, LIBXML_NONET)) {
                            $errors = libxml_get_errors();
                            libxml_clear_errors();
                            $errorMsg = $errors ? $errors[0]->message : 'Unknown HTML parsing error';

                            throw new ParseException("Failed to parse HTML body: $errorMsg");
                        }
                        libxml_set_external_entity_loader(null);

                        return $dom;
                    } finally {
                        libxml_use_internal_errors($previousUseInternalErrors);
                    }
            }
        }

        return [$body];
    }

    /**
     * Parse the Content-Type header into an associative array.
     * This method extracts the main type and parameters from the Content-Type header.
     * It splits the header value by semicolons and returns an associative array where:
     * - The first element is the main type (e.g., 'text/html').
     * - The subsequent elements are parameters in the format 'key' => 'value'.
     * If the Content-Type header is not set or empty, it returns an empty array.
     * @return array<string|null>
     * Returns an associative array with the main type as the first element and parameters as key-value pairs.
     * The keys are the parameter names, and the values are the corresponding parameter values.
     * If the Content-Type header is not set or empty, it returns an empty array.
     */
    public function parseContentType(): array
    {
        $contentType = $this->getHeader('Content-Type');
        if (empty($contentType[0])) {
            return [];
        }
        $parts = explode(';', $contentType[0]);
        $parameters = [];
        $parameters[0] = trim($parts[0]);
        $count = count($parts);
        for ($index = 1; $index < $count; ++$index) {
            $param = trim($parts[$index]);
            $paramParts = explode('=', $param, 2);
            $key = trim($paramParts[0]);
            $value = isset($paramParts[1]) ? trim($paramParts[1]) : null;
            $parameters[$key] = $value;
        }

        return $parameters;
    }

    /**
     * Validate a header name according to RFC 7230.
     *
     * @param string $name
     * The header name to validate.
     * @throws \InvalidArgumentException
     * If the header name is invalid.
     */
    private function assertHeaderName(string $name): void
    {
        if (! preg_match('/^[A-Za-z0-9\'\-!#$%&*+.^_`|~]+$/', $name)) {
            throw new \InvalidArgumentException("Invalid header name: $name");
        }
    }

    /**
     * Validate and assert the header value.
     * This method checks if the header value is a valid string or an array of strings.
     * It also checks if the value does not contain any newline characters.
     * If the header value is invalid, it throws an InvalidArgumentException.
     *
     * @param mixed $value
     * The header value to validate. It can be a string or an array of strings.
     * @throws \InvalidArgumentException
     * If the header value is invalid.
     */
    private function assertHeaderValue($value): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (! is_string($item) || preg_match('/[\r\n]/', $item)) {
                    throw new HeaderException("Invalid header value. Array values must be strings.");
                }
            }
        } elseif (! is_string($value)) {
            throw new HeaderException("Invalid header value. It must be a string or an array.");
        }
    }
}
