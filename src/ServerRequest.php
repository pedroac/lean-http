<?php

declare(strict_types=1);

namespace Pac\LeanHttp;

use Pac\LeanHttp\Exception\UploadedFileException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

/**
 * Represents an HTTP server request.
 * This class implements the ServerRequestInterface and extends the Request class.
 * It provides methods to access and modify server request-specific parameters such as cookies, query parameters, uploaded files, parsed body, attributes, and server parameters.
 */
class ServerRequest extends Request implements ServerRequestInterface
{
    /**
     * Constructor to initialize the server request with method, URI, body, headers, cookie parameters, query parameters, uploaded files, parsed body, attributes, server parameters, request target, and protocol version.
     * @param string $method
     * The HTTP method (e.g., 'GET', 'POST').
     * @param UriInterface $uri
     * The URI of the request.
     * @param StreamInterface $body
     * The body of the request.
     * @param array<string, string[]> $headers
     * The headers of the request.
     * @param array<string, string> $cookieParams
     * The cookie parameters.
     * @param array<string|int, mixed> $queryParams
     * The query parameters.
     * @param array<string|int, mixed> $uploadedFiles
     * The uploaded files.
     * @param array<string|int, mixed>|object|string|null $parsedBody
     * The parsed body data.
     * @param array<string, mixed> $attributes
     * The attributes of the request.
     * @param array<string, mixed> $serverParams
     * The server parameters.
     * @param string $requestTarget
     * The request target (default is '/').
     * @param ?string $protocolVersion
     * The HTTP protocol version (default is null).
     */
    public function __construct(
        string $method,
        UriInterface $uri,
        StreamInterface $body,
        array $headers = [],
        private array $cookieParams = [],
        private array $queryParams = [],
        private array $uploadedFiles = [],
        private array|string|object|null $parsedBody = null,
        private array $attributes = [],
        private array $serverParams = [],
        string $requestTarget = '/',
        ?string $protocolVersion = null
    ) {
        if (! empty($uploadedFiles) && ! UploadedFile::validateTree($uploadedFiles)) {
            throw new UploadedFileException('Invalid uploadedFiles argument: it must be an array tree of UploadedFileInterface instances.');
        }
        if ($protocolVersion === null && isset($serverParams['SERVER_PROTOCOL'])) {
            $protocolVersion = substr(
                $serverParams['SERVER_PROTOCOL'],
                strpos($serverParams['SERVER_PROTOCOL'], '/') + 1
            );
        }
        parent::__construct($method, $uri, $body, $headers, $requestTarget, $protocolVersion);
    }

    /**
     * Create a ServerRequest instance from PHP global variables.
     *
     * Reads data from $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES and creates a fully
     * initialized ServerRequest with parsed body, query parameters, and uploaded files.
     *
     * @return self A new ServerRequest instance
     * @see UploadedFile::fromGlobal() for handling uploaded files
     */
    public static function fromGlobals(): self
    {
        $stream = new Stream('php://input', 'rb');
        $queryParams = [];
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        $uriString = $_SERVER['REQUEST_URI'] ?? null;
        if (is_string($queryString) && $queryString !== '') {
            parse_str($queryString, $queryParams);
        }
        $instance = new ServerRequest(
            $_SERVER[ 'REQUEST_METHOD'] ?? 'GET',
            new Uri($uriString),
            $stream,
            static::getAllHeadersFromGlobal(),
            $_COOKIE,
            $queryParams,
            UploadedFile::fromGlobal(),
            null,
            [],
            filter_input_array(INPUT_SERVER) ?: []
        );
        $instance->parsedBody = $instance->parseBody();

        return $instance;
    }

    /**
     * Get all headers from the global $_SERVER array.
     * This method extracts HTTP headers from $_SERVER and returns them as an associative array.
     * @return array<string, string[]>
     * Returns an associative array of header names to arrays of header values.
     */
    public static function getAllHeadersFromGlobal(): array
    {
        $headers = [];
        // Handle environments where getallheaders() is available
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                $headers[$name] = $value;
            }
        } else {
            foreach ($_SERVER as $key => $value) {
                if (str_starts_with($key, 'HTTP_')) {
                    $headerName = str_replace(' ', '-', ucwords(strtolower(strtr(substr($key, 5), '_', ' '))));
                    $headers[$headerName] = $value;
                } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'])) {
                    $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
                    $headers[$headerName] = $value;
                }
            }
        }
        // Normalize multiple headers with the same name as arrays (e.g., Set-Cookie)
        foreach ($headers as $name => $value) {
            if (isset($headers[$name]) && is_string($headers[$name]) && strpos($headers[$name], ',') !== false && strtolower($name) !== 'cookie') {
                $headers[$name] = array_map('trim', explode(',', $headers[$name]));
            }
        }

        return $headers;
    }

    /**
     * Get the value of an attribute by name.
     * If the attribute does not exist, it returns the default value provided.
     * @param string $name
     * The name of the attribute to retrieve.
     * @param mixed $default
     * The default value to return if the attribute does not exist (default is null).
     * @return mixed
     * Returns the value of the attribute or the default value if the attribute does not exist.
     */
    public function getAttribute(string $name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * Get all attributes of the request.
     * This method returns an associative array of all attributes set in the request.
     * @return array<string, mixed>
     * Returns an associative array of attributes.
     * The keys are the attribute names, and the values are the corresponding attribute values.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get the cookie parameters of the request.
     * This method returns an associative array of cookies sent with the request.
     * @return array<string, string>
     * Returns an associative array of cookies.
     * The keys are the cookie names, and the values are the corresponding cookie values.
     */
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /**
     * Get the parsed body of the request.
     * This method returns the parsed body data, which can be an array, object, string, or null.
     * @return array<string|int, mixed>|object|string|null
     * Returns the parsed body data.
     * If the body is empty or not set, it returns null.
     */
    public function getParsedBody(): array|string|object|null
    {
        return $this->parsedBody;
    }

    /**
     * Get the query parameters of the request.
     * This method returns an associative array of query parameters parsed from the request URI.
     * @return array<string|int, mixed>
     * Returns an associative array of query parameters.
     * The keys are the parameter names, and the values are the corresponding parameter values.
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * Get the server parameters of the request.
     * This method returns an associative array of server parameters, such as headers and environment variables.
     * @return array<string, mixed>
     * Returns an associative array of server parameters.
     * The keys are the parameter names, and the values are the corresponding parameter values.
     */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /**
     * Get the uploaded files of the request.
     * This method returns an associative array of uploaded files, where the keys are the file names and the values are UploadedFileInterface instances.
     * @return array<string|int, mixed>
     * Returns an associative array of uploaded files.
     * The keys are the file names, and the values are UploadedFileInterface instances representing the uploaded files.
     */
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * Return an instance with the specified attribute added or replaced.
     * This method adds or replaces an attribute in the request and returns a new instance.
     * @param string $name The attribute name.
     * @param mixed $value The attribute value.
     * @return static
     * Returns a new ServerRequest instance with the attribute added or replaced.
     */
    public function withAttribute(string $name, $value): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;

        return $clone;
    }

    /**
     * Set the cookie parameters for the server request.
     * This method creates a new instance of the server request with the updated cookie parameters.
     * @param array<string, string> $cookies
     * The cookie parameters to set.
     * It should be an associative array where the keys are cookie names and the values are cookie values.
     * @return static
     * Returns a new instance of the server request with the updated cookie parameters.
     */
    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->cookieParams = $cookies;

        return $clone;
    }

    /**
     * Remove an attribute from the server request.
     * This method creates a new instance of the server request without the specified attribute.
     * @param string $name
     * The name of the attribute to remove.
     * @return ServerRequestInterface
     * Returns a new instance of the server request without the specified attribute.
     */
    public function withoutAttribute(string $name): ServerRequestInterface
    {
        $clone = clone $this;
        unset($clone->attributes[$name]);

        return $clone;
    }

    /**
     * Set the parsed body of the server request.
     * This method creates a new instance of the server request with the updated parsed body.
     * The parsed body can be an array, object, string, or null.
     * @param mixed $data
     * The parsed body data to set.
     * It must be an array, an object, or null. If it is not, an InvalidArgumentException is thrown.
     * @return static
     * Returns a new instance of the server request with the updated parsed body.
     * @throws \InvalidArgumentException
     * If the provided data is not an array, object, or null, it throws an InvalidArgumentException.
     */
    public function withParsedBody(mixed $data): ServerRequestInterface
    {
        if (
            ! is_array($data)
            && ! is_object($data)
            && $data !== null
        ) {
            throw new \InvalidArgumentException('Invalid argument: it must be an array, an object or null.');
        }
        $clone = clone $this;
        $clone->parsedBody = $data;

        return $clone;
    }

    /**
     * Set the query parameters for the server request.
     * This method creates a new instance of the server request with the updated query parameters.
     * @param array<string|int, mixed> $query
     * The query parameters to set.
     * It should be an associative array where the keys are parameter names and the values are parameter values.
     * @return static
     * Returns a new instance of the server request with the updated query parameters.
     */
    public function withQueryParams(array $query): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->queryParams = $query;

        return $clone;
    }

    /**
     * Set the uploaded files for the server request.
     * This method creates a new instance of the server request with the updated uploaded files.
     * The uploaded files must be a valid array tree of UploadedFileInterface instances.
     * @param array<string|int, mixed> $uploadedFiles
     * The uploaded files to set.
     * It should be an array tree of UploadedFileInterface instances.
     * @return static
     * Returns a new instance of the server request with the updated uploaded files.
     * @throws \InvalidArgumentException
     * If the provided argument is not a valid array tree of UploadedFileInterface instances, it throws an InvalidArgumentException.
     */
    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
    {
        if (! UploadedFile::validateTree($uploadedFiles)) {
            throw new \InvalidArgumentException('Invalid argument: it must be an array tree of UploadedFileInterface instances.');
        }
        $clone = clone $this;
        $clone->uploadedFiles = $uploadedFiles;

        return $clone;
    }
}
