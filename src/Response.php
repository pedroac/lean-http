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
     * @param array $headers
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
            $status = Status::tryFrom ($statusCode);
            $reasonPhrase = $status ? $status->getReasonPhrase() : '';
        }
        $this->reasonPhrase = $reasonPhrase;
        parent::__construct($body, $headers, $protocolVersion);
    }

    /**
     * Create a response by content type.
     * This method creates a response with the specified status code, data, headers, reason phrase, and protocol version.
     * The data is written to the body of the response based on the content type.
     * @param int $statusCode
     * The HTTP status code (e.g., 200, 404).
     * @param mixed $data
     * The data to be written to the response body.
     * @param array $headers
     * The headers of the response.
     * @param ?string $reasonPhrase
     * The reason phrase for the response (default is null).
     * @param ?string $protocolVersion
     * The HTTP protocol version (default is null).
     * @return self
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
        [$mainType] = $response->parseContentType();
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
    function getReasonPhrase(): string
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
    function getStatusCode(): int
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
    function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
    {
        if ($this->statusCode < 100 || $this->statusCode > 599) {
            throw new \InvalidArgumentException("Invalid HTTP status code:  $code. It must be between 100 and 599.");
        }
        $cloned = clone $this;
        $cloned->statusCode = $code;
        $cloned->reasonPhrase = $reasonPhrase === ''
            ? Status::tryFrom($code)?->getReasonPhrase()
            : $reasonPhrase;  
        return $cloned;
    }
}
