# pac\lean-http

![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-blue)
![License](https://img.shields.io/badge/license-MIT-green)
![WIP](https://img.shields.io/badge/status-work--in--progress-orange)
[![Buy Me a Coffee](https://img.shields.io/badge/buy%20me%20a%20coffee-%E2%98%95-yellow)](https://www.buymeacoffee.com/pedroac)

A fast, developer-friendly [PSR-7](https://www.php-fig.org/psr/psr-7/) HTTP message library, focused on robust URI handling, message validation, and convenient request/response operations. Designed for modern PHP applications that need a lightweight, standards-compliant foundation for HTTP messaging.

This library is called "lean-http" because it provides only comprehensive HTTP message features—without routers, middleware, or other application-layer responsibilities. It’s focused on a single responsibility and avoids extra dependencies or unnecessary abstractions.

---

## Table of Contents

- [Goals](#goals)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Example](#quick-example)
- [Getting Started](#getting-started)
- [URI Class](#uri-class)
- [URI Validator](#uri-validator)
- [Normalizer](#normalizer)
- [URI Builder](#uri-builder)
- [Requests](#requests)
- [Responses](#responses)
- [Contributing](#contributing)
- [License](#license)

## 🎯 Goals

- **PSR-7 Implementation:** Deliver a solid, PSR-7-compliant library focused solely on HTTP messages, without including client or router functionalities.
- **Developer-Friendly:** Provide an intuitive API for URI handling, body parsing, and request/response operations.
- **High Performance:** Optimized for production-level applications without unnecessary complexity.
- **Comprehensive and Flexible:** Includes validation and building features to handle diverse message requirements.
- **Community Collaboration:** Welcomes contributions and feedback to refine and expand the library.

## 🛠️ Features

- Full implementation of the [PSR-7 standard](https://www.php-fig.org/psr/psr-7/).
- URI validator, normalizer, and builder.
- Body parsing based on the `Content-Type` header.
- Convenient instantiation of classes from global variables.

## 📝 Requirements

- PHP 8.3 or greater
- Composer
- `Intl` (optional) extension is needed for IDNA (Unicode hosts).
- `libxml` (optional) is needed for XML/HTML body parsing.

## 📥 Installation

Install via [Composer](https://getcomposer.org/):

```bash
composer require pac/lean-http
```

## ⚡ Quick Example

Quick example:

```php
use Pac\LeanHttp\ServerRequest;
use Pac\LeanHttp\Response;

$request = ServerRequest::fromGlobals();
$data = $request->parseBody();

$response = new Response(200, 'OK');
$response->getBody()->write('Hello, world!');
```

## 👣 Getting Started

To create a server request from global variables and parse the body:

```php
use Pac\LeanHttp\ServerRequest;

$request = ServerRequest::fromGlobals();
$data = $request->parseBody();
```

Handle uploaded files:

``` php
$uploadedFiles = $request->getUploadedFiles();
$uploadedFile = $uploadedFiles['image'] ?? null;
$uploadedFile?->moveTo('newPath.png');
```

Create a response based on content type:

``` php
use Pac\LeanHttp\Response;

$response = Response::byContentType(
    200,
    ['username' => 'pac', 'age' => 40],
    ['Content-Type' => 'application/json']
);
$parsedBody = $response->parseBody(); // {"username": "pac", "age": 40}
```

## 🔗 URI class

The `Uri` class provides a normalized version of the URI, applying transformations such as removing dot segments, decoding certain percent-encoded characters, and removing the default port.

Example:

```php
use Pac\LeanHttp\Uri;

$uri = new Uri('http://example.com:80/%7Efoo/./bar/baz/../qux/index.html#fragment');
echo (string) $uri; // http://example.com/~foo/bar/qux/index.html#fragment
```

**Additional methods:**
- `getQueryParams()`: Returns the URI query parameters as an associative array.
- `withQueryParams(array $params)`: Returns a new instance with a query string built from an associative array.

## 🕵️‍♂️ URI Validator

Validate query strings using the URI Validator:

``` php
$uriValidator = UriValidator::getDefault();
$isValidQuery = $uriValidator->validateQuery('name=John&age=30');
```

## 🧹 Normalizer

Normalize URI components:

``` php
use Pac\LeanHttp\Uri\UriNormalizer;

$uriNormalizer = UriNormalizer::getDefault();
$normalizedPath = $uriNormalizer->normalizePath('search%20results/../archive'); // "archive"
```

For absolute paths:

``` php
$normalizedPath = $uriNormalizer->normalizePath('search%20results/../archive', true); // "/archive"
```

Encode special characters:

``` php
$encoded = $uriNormalizer->normalizeEncode('foo%20bar!'); // "foo%20bar%21"
```

You can customize the normalizer:

``` php
use Pac\LeanHttp\Uri\UriNormalizer;

$uriNormalizer = new UriNormalizer(
    forcePunyCode: false,
    sortQuery: true
);
$normalizedHost = $uriNormalizer->normalizeHost('João.com'); // joão.com
$normalizedQuery = $uriNormalizer->normalizeQuery('b=1&a=2'); // a=2&b=1
```

## 🏗️ URI Builder

Build URIs from components with the `UriBuilder`:

``` php
use Pac\LeanHttp\Uri\UriBuilder;

$uriBuilder = new UriBuilder(
    'http',
    'example.com',
    'path',
    ['key' => 'name']
);
$uriString = $uriBuilder->build();
```

Since PHP 8, it's possible to use named arguments:
``` php
$uriBuilder = new UriBuilder(
    scheme: 'http',
    host: 'example.com',
    path: 'path',
    query: ['key' => 'name'],
    fragment: 'section',
    user: 'someuser',
    password: 'mypass123'
);
```

The `$query` argument can be a string, an associative array or null.

## 📝 Requests

The `ServerRequest` class represents an HTTP request as per the PSR-7 specification. It includes the method, URI, headers, body, cookies, and server parameters.

Example:

``` php
use Pac\LeanHttp\ServerRequest;

$request = new ServerRequest(
    'GET',
    new Uri('http://example.com/index.html/'),
    Stream::fromInput(),
    headers: ['Content-Type' => 'text/html'],
    cookieParams: ['session' => '12345'],
    serverParams: ['HTTP_HOST' => 'example.com']
);
```

**Use Case:** Use `ServerRequest` when you need to represent a complete HTTP request in your application, such as for handling incoming HTTP requests in a web server environment.

``` php
$request = ServerRequest::fromGlobals();
```

## 📬 Responses

The `Response` class represents an HTTP response as per the PSR-7 specification. It includes the status code, reason phrase, headers, and body content.

Example:

``` php
use Pac\LeanHttp\Response;

$response = new Response(
    200,
    'OK',
    ['X-Custom-Header' => 'values'] // headers
);
```

**Use Case:** Use `Response` when you need to represent and return an HTTP response to the client, such as in API responses or page rendering.

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

- **Fork and PR:** Fork the repository and create a pull request for any changes. Include relevant tests and documentation for new features.
- **Issue Tracking:** Use GitHub issues to report bugs or suggest improvements.
- **Coding Standards:** Maintain PSR-12 coding standards and adhere to the project’s overall style.
- **Testing:** Run `composer test` and ensure all tests pass before submitting a pull request.
- **LLM:** Using AI tools like Copilot is encouraged to find issues faster, ensure specifications are followed, and improve code quality. However, always apply critical thinking and carefully review AI-generated suggestions.


## 📄 License

This project is open-source under the **MIT License**.
