<?php

declare(strict_types=1);

namespace Pac\LeanHttp;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Represents an HTTP response.
 * This class implements the ResponseInterface and extends the Message class.
 * It provides methods to access and modify the response status code, reason phrase, headers, and body.
 */
class Response extends Message implements ResponseInterface
{
    /**
     * Minimum valid HTTP status code.
     */
    private const MIN_STATUS_CODE = 100;

    /**
     * Maximum valid HTTP status code.
     */
    private const MAX_STATUS_CODE = 599;
    /**
     * The reason phrase for the response.
     * This is a short description of the status code.
     * It is typically used in the response status line.
     * If not provided, it will be derived from the status code.
     */
    private string $reasonPhrase;

    /**
     * Constructor to initialize the response with status code, body, headers, reason phrase, and protocol version.
     * @param int $statusCode
     * The HTTP status code (e.g., 200, 404).
     * @param StreamInterface $body
     * The body of the response.
     * @param array<string, string[]> $headers
     * The headers of the response.
     * @param ?string $reasonPhrase
     * The reason phrase for the response (default is null).
     * If not provided, it will be derived from the status code.
     * @param ?string $protocolVersion
     * The HTTP protocol version (default is null).
     */
    public function __construct(
        private int $statusCode,
        StreamInterface $body,
        array $headers = [],
        ?string $reasonPhrase = null,
        ?string $protocolVersion = null,
    ) {
        if (empty($reasonPhrase)) {
            $status = Status::tryFrom($statusCode);
            $reasonPhrase = $status ? $status->getReasonPhrase() : '';
        }
        $this->reasonPhrase = $reasonPhrase;
        parent::__construct($body, $headers, $protocolVersion);
    }

    /**
     * Create a response by content type.
     *
     * Automatically serializes the data to the response body based on the Content-Type header.
     * Supports JSON, CSV, XML, HTML, and plain text.
     *
     * @param int $statusCode The HTTP status code (e.g., 200, 404)
     * @param mixed $data The data to serialize to the response body
     * @param array<string, string[]> $headers Response headers (must include Content-Type)
     * @param ?string $reasonPhrase The reason phrase or null to use default
     * @param ?string $protocolVersion The HTTP protocol version or null to use default
     * @return self A new Response instance with serialized body
     */
    public static function byContentType(
        int $statusCode,
        mixed $data,
        array $headers,
        ?string $reasonPhrase = null,
        ?string $protocolVersion = null
    ): self {
        $body = Stream::fromTemporary();
        $response = new self($statusCode, $body, $headers, $reasonPhrase, $protocolVersion);
        $contentTypeParts = $response->parseContentType();
        $mainType = $contentTypeParts[0] ?? '';
        $body->writeByContentType($data, $mainType);

        return $response;
    }

    /**
     * Get the reason phrase for the response.
     * The reason phrase is a short description of the status code.
     * It is typically used in the response status line.
     * @return string
     * Returns the reason phrase for the response.
     */
    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    /**
     * Get the status code for the response.
     * The status code is a three-digit integer that indicates the result of the request.
     * It is typically used in the response status line.
     * @return int
     * Returns the status code for the response.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Set the status code for the response.
     * This method creates a new instance of the response with the updated status code and reason phrase.
     * @param int $code
     * The HTTP status code (e.g., 200, 404).
     * @param string $reasonPhrase
     * The reason phrase for the response (default is an empty string).
     * @return ResponseInterface
     * Returns a new instance of the response with the updated status code and reason phrase.
     */
    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
    {
        if ($code < self::MIN_STATUS_CODE || $code > self::MAX_STATUS_CODE) {
            throw new \InvalidArgumentException(
                "Invalid HTTP status code: $code. It must be between " .
                self::MIN_STATUS_CODE . " and " . self::MAX_STATUS_CODE . "."
            );
        }
        $cloned = clone $this;
        $cloned->statusCode = $code;
        $cloned->reasonPhrase = $reasonPhrase === ''
            ? Status::tryFrom($code)?->getReasonPhrase() ?? ''
            : $reasonPhrase;

        return $cloned;
    }
}
