# pac/lean-http

![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-blue)
![License](https://img.shields.io/badge/license-MIT-green)
![Stable](https://img.shields.io/badge/status-stable-brightgreen)
[![codecov](https://codecov.io/gh/pedroac/lean-http/graph/badge.svg?token=OIB8SVF1NO)](https://codecov.io/gh/pedroac/lean-http)
[![Buy Me a Coffee](https://img.shields.io/badge/buy%20me%20a%20coffee-%E2%98%95-yellow)](https://www.buymeacoffee.com/pedroac)

A fast, developer-friendly [PSR-7](https://www.php-fig.org/psr/psr-7/) HTTP message library for PHP, focused on robust URI handling, message validation, and convenient request/response operations. Designed for modern PHP applications that need a lightweight, standards-compliant foundation for HTTP messaging.

---

## Table of Contents

- [Why lean-http?](#why-lean-http)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Core Features](#core-features)
  - [HTTP Messages](#http-messages)
  - [Body Parsing](#body-parsing)
  - [URI Handling](#uri-handling)
  - [File Uploads](#file-uploads)
- [Advanced Usage](#advanced-usage)
- [Contributing](#contributing)
- [License](#license)

---

## Why lean-http?

**lean-http** provides comprehensive HTTP message features without the bloat. Unlike full-stack frameworks, this library focuses solely on HTTP messaging:

- ✅ **PSR-7 Compliant** - Full implementation of the PSR-7 standard
- ✅ **Zero Dependencies** - Only requires `psr/http-message` interface
- ✅ **Lightweight** - No routers, middleware, or application-layer code
- ✅ **Production Ready** - Optimized for performance and reliability
- ✅ **Well Tested** - High test coverage with comprehensive test suite
- ✅ **Type Safe** - Built for PHP 8.3+ with strict types and static analysis

Perfect for building custom HTTP applications, API clients, or when you need a solid foundation without framework overhead.

---

## Features

### 🎯 PSR-7 Implementation
Complete implementation of the [PSR-7 HTTP message interfaces](https://www.php-fig.org/psr/psr-7/), including:
- `MessageInterface` - Headers, protocol version, and body management
- `RequestInterface` - HTTP request methods, URI, and request target
- `ServerRequestInterface` - Server-side request with cookies, query params, and uploaded files
- `ResponseInterface` - HTTP response with status codes and reason phrases
- `UriInterface` - Full URI manipulation and normalization
- `StreamInterface` - Stream-based body handling
- `UploadedFileInterface` - Secure file upload handling

### 🔍 Smart Body Parsing
Automatically parses request/response bodies based on `Content-Type`:
- **JSON** (`application/json`) - Automatic JSON decoding
- **Form Data** (`application/x-www-form-urlencoded`) - Query string parsing
- **Multipart** (`multipart/form-data`) - Form data with file uploads
- **CSV** (`text/csv`) - CSV row parsing
- **XML** (`text/xml`, `application/xml`) - DOMDocument parsing with XXE protection
- **HTML** (`text/html`) - HTML parsing with XXE protection

### 🔗 Advanced URI Handling
Comprehensive URI tools for validation, normalization, and building:
- **URI Normalization** - Removes dot segments, normalizes encoding, handles default ports
- **URI Validation** - Validates schemes, hosts, ports, paths, queries, and fragments
- **URI Builder** - Construct URIs from components with type safety
- **Query Parameter Helpers** - Easy conversion between query strings and arrays
- **IDN Support** - Internationalized domain name handling (requires `ext-intl`)

### 📤 File Upload Management
Secure and convenient file upload handling:
- Automatic parsing from `$_FILES` superglobal
- Support for single and multiple file uploads
- Nested file array structures
- Secure file movement with validation
- Upload error handling

### 🛡️ Security Features
Built-in security best practices:
- XXE (XML External Entity) attack prevention
- Header injection protection
- Path traversal protection for file uploads
- Input validation and sanitization
- Specialized exception types for better error handling

### ⚡ Developer Experience
- **From Globals** - Easy instantiation from `$_SERVER`, `$_GET`, `$_POST`, `$_FILES`
- **Immutable Objects** - All message objects are immutable (PSR-7 compliant)
- **Type Safety** - Full PHP 8.3+ type hints and return types
- **Clear Exceptions** - Specialized exception classes for different error types
- **Comprehensive Documentation** - Well-documented code with examples

---

## Requirements

- **PHP 8.3+** - Modern PHP features and performance
- **Composer** - For dependency management

### Optional Extensions

- `ext-intl` - Recommended for IDN (Internationalized Domain Names) support
- `ext-xml` - Recommended for XML/HTML body parsing

---

## Installation

Install via [Composer](https://getcomposer.org/):

```bash
composer require pac/lean-http
```

---

## Quick Start

### Handle an HTTP Request

```php
use Pac\LeanHttp\ServerRequest;
use Pac\LeanHttp\Response;

// Create request from PHP globals
$request = ServerRequest::fromGlobals();

// Parse request body automatically based on Content-Type
$data = $request->parseBody(); // Returns array, object, or null

// Access request data
$method = $request->getMethod(); // 'GET', 'POST', etc.
$uri = $request->getUri();
$headers = $request->getHeaders();
$queryParams = $request->getQueryParams();
$cookies = $request->getCookieParams();
```

### Create an HTTP Response

```php
use Pac\LeanHttp\Response;
use Pac\LeanHttp\Stream;

// Simple response
$response = new Response(200);
$response->getBody()->write('Hello, World!');

// Response with automatic content type handling
$response = Response::byContentType(
    200,
    ['message' => 'Success', 'data' => [1, 2, 3]],
    ['Content-Type' => 'application/json']
);
// Body is automatically JSON-encoded
```

### Handle File Uploads

```php
$request = ServerRequest::fromGlobals();
$uploadedFiles = $request->getUploadedFiles();

// Single file
$file = $uploadedFiles['avatar'] ?? null;
if ($file) {
    $file->moveTo('/path/to/uploads/' . $file->getClientFilename());
}

// Multiple files
foreach ($uploadedFiles['images'] ?? [] as $file) {
    $file->moveTo('/path/to/uploads/' . $file->getClientFilename());
}
```

---

## Core Features

### HTTP Messages

#### Creating Requests

```php
use Pac\LeanHttp\ServerRequest;
use Pac\LeanHttp\Uri;
use Pac\LeanHttp\Stream;

// From PHP globals (most common)
$request = ServerRequest::fromGlobals();

// Manually
$request = new ServerRequest(
    'POST',
    new Uri('https://api.example.com/users'),
    Stream::fromMemory('{"name": "John"}'),
    headers: ['Content-Type' => 'application/json'],
    cookieParams: ['session' => 'abc123'],
    queryParams: ['page' => '1']
);
```

#### Creating Responses

```php
use Pac\LeanHttp\Response;
use Pac\LeanHttp\Stream;

// Basic response
$response = new Response(200, 'OK');

// With headers
$response = new Response(
    201,
    'Created',
    ['Location' => '/users/123', 'X-Custom-Header' => 'value']
);

// With body
$body = Stream::fromMemory('{"status": "ok"}');
$response = new Response(200, 'OK', ['Content-Type' => 'application/json'], $body);

// Automatic content type handling
$response = Response::byContentType(
    200,
    ['status' => 'ok'],
    ['Content-Type' => 'application/json']
);
```

#### Working with Headers

```php
// Get headers
$contentType = $request->getHeaderLine('Content-Type');
$allHeaders = $request->getHeaders();

// Modify headers (returns new instance - immutable)
$newRequest = $request
    ->withHeader('X-API-Key', 'secret123')
    ->withAddedHeader('X-Custom', 'value1')
    ->withAddedHeader('X-Custom', 'value2') // Adds second value
    ->withoutHeader('X-Old-Header');
```

### Body Parsing

The library automatically parses request/response bodies based on the `Content-Type` header:

```php
$request = ServerRequest::fromGlobals();
$parsed = $request->parseBody();

// Content-Type: application/json
// Returns: array or object (decoded JSON)

// Content-Type: application/x-www-form-urlencoded
// Returns: array (parsed form data)

// Content-Type: multipart/form-data
// Returns: array (from $_POST)

// Content-Type: text/csv
// Returns: array of arrays (CSV rows)

// Content-Type: text/xml or application/xml
// Returns: DOMDocument instance

// Content-Type: text/html
// Returns: DOMDocument instance

// Unknown or empty Content-Type
// Returns: array with body as string
```

**Example:**

```php
// JSON request body
$request = new ServerRequest(
    'POST',
    new Uri('https://api.example.com/users'),
    Stream::fromMemory('{"name": "John", "age": 30}'),
    headers: ['Content-Type' => 'application/json']
);
$data = $request->parseBody();
// $data = ['name' => 'John', 'age' => 30]

// Form data
$request = new ServerRequest(
    'POST',
    new Uri('https://api.example.com/users'),
    Stream::fromMemory('name=John&age=30'),
    headers: ['Content-Type' => 'application/x-www-form-urlencoded']
);
$data = $request->parseBody();
// $data = ['name' => 'John', 'age' => 30]
```

### URI Handling

#### URI Normalization

URIs are automatically normalized when created:

```php
use Pac\LeanHttp\Uri;

$uri = new Uri('http://example.com:80/%7Efoo/./bar/baz/../qux/index.html#fragment');
echo (string) $uri;
// Output: http://example.com/~foo/bar/qux/index.html#fragment

// Normalization includes:
// - Removing default ports (80 for http, 443 for https)
// - Decoding percent-encoded characters where appropriate
// - Removing dot segments (./ and ../)
// - Lowercasing scheme and host
```

#### Query Parameters

```php
$uri = new Uri('https://example.com/search?q=hello&page=2&sort=name');

// Get query string
$query = $uri->getQuery(); // 'q=hello&page=2&sort=name'

// Get as array
$params = $uri->getQueryParams();
// ['q' => 'hello', 'page' => '2', 'sort' => 'name']

// Modify query parameters
$newUri = $uri->withQueryParams([
    'q' => 'world',
    'page' => '1',
    'filter' => 'active'
]);
// Query string is automatically built and URL-encoded
```

#### URI Builder

Build URIs from components:

```php
use Pac\LeanHttp\Uri\UriBuilder;

// Using positional arguments
$uri = (new UriBuilder(
    'https',
    'api.example.com',
    '/users',
    ['page' => 1, 'limit' => 10],
    'section1',
    443,
    'user',
    'pass'
))->build();

// Using named arguments (PHP 8+)
$uri = (new UriBuilder(
    scheme: 'https',
    host: 'api.example.com',
    path: '/users',
    query: ['page' => 1],
    fragment: 'section1',
    user: 'admin',
    password: 'secret'
))->build();
```

#### URI Validation and Normalization Tools

```php
use Pac\LeanHttp\Uri\UriValidator;
use Pac\LeanHttp\Uri\UriNormalizer;

$validator = UriValidator::getDefault();
$normalizer = UriNormalizer::getDefault();

// Validate components
$isValid = $validator->validateQuery('name=John&age=30'); // true
$isValid = $validator->validatePort(8080); // true
$isValid = $validator->validatePort(99999); // false

// Normalize components
$normalized = $normalizer->normalizePath('/foo/../bar/./baz');
// Result: '/bar/baz'

$normalized = $normalizer->normalizeHost('EXAMPLE.COM');
// Result: 'example.com'

$normalized = $normalizer->normalizeQuery('b=2&a=1', sortQuery: true);
// Result: 'a=1&b=2' (sorted)
```

### File Uploads

#### Handling Uploaded Files

```php
$request = ServerRequest::fromGlobals();
$files = $request->getUploadedFiles();

// Single file upload
$avatar = $files['avatar'] ?? null;
if ($avatar && $avatar->getError() === UPLOAD_ERR_OK) {
    $filename = $avatar->getClientFilename();
    $size = $avatar->getSize();
    $type = $avatar->getClientMediaType();
    
    // Move to permanent location
    $avatar->moveTo('/uploads/' . $filename);
}

// Multiple file uploads
$images = $files['images'] ?? [];
foreach ($images as $image) {
    if ($image->getError() === UPLOAD_ERR_OK) {
        $image->moveTo('/uploads/' . $image->getClientFilename());
    }
}

// Nested file structures
$documents = $files['documents']['user']['profile'] ?? null;
if ($documents) {
    $documents->moveTo('/uploads/' . $documents->getClientFilename());
}
```

#### Creating UploadedFile Instances

```php
use Pac\LeanHttp\UploadedFile;

// From $_FILES array
$file = UploadedFile::fromArray($_FILES['avatar']);

// Manually
$file = new UploadedFile(
    filePath: '/tmp/phpXXXXXX',
    clientFilename: 'photo.jpg',
    clientMediaType: 'image/jpeg',
    size: 102400,
    error: UPLOAD_ERR_OK
);

// Get file information
$filename = $file->getClientFilename();
$size = $file->getSize();
$type = $file->getClientMediaType();
$error = $file->getError();

// Read file content
$stream = $file->getStream();
$content = $stream->getContents();
```

---

## Advanced Usage

### Streams

```php
use Pac\LeanHttp\Stream;

// Create from file
$stream = new Stream('/path/to/file.txt', 'r');

// Create from memory
$stream = Stream::fromMemory('Initial content');
$stream->write('More content');

// Create temporary stream
$stream = Stream::fromTemporary();

// Read from PHP input
$input = Stream::fromInput();

// Write to PHP output
$output = Stream::fromOutput();
$output->write('Hello, World!');

// Stream operations
$content = $stream->getContents();
$size = $stream->getSize();
$position = $stream->tell();
$stream->seek(0); // Rewind
$data = $stream->read(1024); // Read 1024 bytes
```

### Custom Status Codes

```php
use Pac\LeanHttp\Response;
use Pac\LeanHttp\Status;

// Using Status enum
$response = new Response(Status::OK->value);
$reasonPhrase = Status::OK->getReasonPhrase(); // 'OK'

// Custom status code
$response = new Response(418, "I'm a teapot");

// Modify status
$newResponse = $response->withStatus(404, 'Not Found');
```

### Error Handling

The library uses specialized exception types for better error handling:

```php
use Pac\LeanHttp\Exception\ParseException;
use Pac\LeanHttp\Exception\StreamException;
use Pac\LeanHttp\Exception\UploadedFileException;
use Pac\LeanHttp\Exception\HeaderException;

try {
    $data = $request->parseBody();
} catch (ParseException $e) {
    // Handle parsing errors (invalid JSON, XML, etc.)
    error_log("Parse error: " . $e->getMessage());
} catch (StreamException $e) {
    // Handle stream errors (file not found, read errors, etc.)
    error_log("Stream error: " . $e->getMessage());
} catch (UploadedFileException $e) {
    // Handle file upload errors
    error_log("Upload error: " . $e->getMessage());
} catch (HeaderException $e) {
    // Handle header validation errors
    error_log("Header error: " . $e->getMessage());
}
```

---

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for detailed guidelines on how to contribute to this project.

---

## License

This project is open-source under the **MIT License**.
