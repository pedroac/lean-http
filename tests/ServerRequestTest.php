<?php

declare(strict_types=1);

namespace Pac\LeanHttp\Tests;

use Pac\LeanHttp\Message;
use Pac\LeanHttp\ServerRequest;
use Pac\LeanHttp\Stream;
use Pac\LeanHttp\Uri;
use phpmock\functions\FixedValueFunction;
use phpmock\MockBuilder;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;

class ServerRequestTest extends TestCase
{
    private const TEST_URI = 'http://example.com/test';
    private const TEST_METHOD = 'GET';

    private StreamInterface $body;
    private UriInterface $uri;

    protected function setUp(): void
    {
        $this->body = new Stream('php://memory', 'r+');
        $this->body->write('Test body content');
        $this->body->rewind();
        $this->uri = new Uri(self::TEST_URI);
    }

    public function testConstructor()
    {
        $request = new ServerRequest(
            self::TEST_METHOD,
            $this->uri,
            $this->body
        );

        $this->assertSame(self::TEST_METHOD, $request->getMethod());
        $this->assertSame($this->uri, $request->getUri());
        $this->assertSame('Test body content', (string) $request->getBody());
        $this->assertSame([], $request->getAttributes());
    }

    #[TestWith([['SERVER_PROTOCOL' => 'HTTP/2.0'], '2.0'])]
    #[TestWith([[], Message::DEFAULT_PROTOCOL_VERSION])]
    public function testConstructorWithoutProtocolVersionArgument($serverParams, $protocolVersion)
    {
        $request = new ServerRequest(
            self::TEST_METHOD,
            $this->uri,
            $this->body,
            serverParams: $serverParams
        );

        $this->assertSame(self::TEST_METHOD, $request->getMethod());
        $this->assertSame($this->uri, $request->getUri());
        $this->assertSame('Test body content', (string) $request->getBody());
        $this->assertSame([], $request->getAttributes());
        $this->assertSame($protocolVersion, $request->getProtocolVersion());
    }

    public function testConstructorWithInvalidUploadedFiles()
    {
        $this->expectException(RuntimeException::class);
        new ServerRequest(
            self::TEST_METHOD,
            $this->uri,
            $this->body,
            uploadedFiles:['12']
        );
    }

    public function testFromGlobalsCreatesRequestWithExpectedValues()
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/test/endpoint',
            'QUERY_STRING' => 'param=value',
            'HTTP_CUSTOM_HEADER' => 'HeaderValue',
        ];
        $_COOKIE = ['testCookie' => 'cookieValue'];
        $_FILES = [];

        $request = ServerRequest::fromGlobals();
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/test/endpoint', $request->getUri()->getPath());
        $this->assertEquals(['param' => 'value'], $request->getQueryParams());
        $this->assertEquals(['testCookie' => 'cookieValue'], $request->getCookieParams());
        $this->assertEquals('HeaderValue', $request->getHeaderLine('Custom-Header'));
    }

    public function testWithAttribute()
    {
        $request = new ServerRequest(
            self::TEST_METHOD,
            $this->uri,
            $this->body
        );
        $newRequest = $request->withAttribute('key', 'value');
        $this->assertNotSame($request, $newRequest);
        $this->assertSame('value', $newRequest->getAttribute('key'));
        $this->assertNull($request->getAttribute('key'));
    }

    public function testWithoutAttribute()
    {
        $request = new ServerRequest(
            self::TEST_METHOD,
            $this->uri,
            $this->body,
            attributes:['name' => 'Pedro', 'age' => 41]
        );
        $newRequest = $request->withoutAttribute('name');
        $this->assertSame(41, $newRequest->getAttribute('age'));
        $this->assertNull($newRequest->getAttribute('name'));
        $this->assertSame('Pedro', $request->getAttribute('name'));
        $this->assertSame(41, $request->getAttribute('age'));
    }

    public function testWithQueryParams()
    {
        $request = new ServerRequest(
            self::TEST_METHOD,
            $this->uri,
            $this->body
        );
        $newRequest = $request->withQueryParams(['foo' => 'bar']);
        $this->assertNotSame($request, $newRequest);
        $this->assertSame(['foo' => 'bar'], $newRequest->getQueryParams());
        $this->assertSame([], $request->getQueryParams());
    }

    public function testGetServerParams()
    {
        $request = new ServerRequest(
            self::TEST_METHOD,
            $this->uri,
            $this->body,
            serverParams: ['HTTP_HOST' => 'example.com']
        );
        $this->assertSame(['HTTP_HOST' => 'example.com'], $request->getServerParams());
    }

    public function testGetCookieParams()
    {
        $request = new ServerRequest(
            self::TEST_METHOD,
            $this->uri,
            $this->body,
            cookieParams: ['session' => '12345']
        );
        $this->assertSame(['session' => '12345'], $request->getCookieParams());
    }

    #[TestWith([['session' => '12345'], ['session' => '67890']])]
    #[TestWith([[], ['session' => '12345']])]
    public function testWithCookieParams(array $oldCookies, array $newCookies)
    {
        $request = new ServerRequest(
            self::TEST_METHOD,
            $this->uri,
            $this->body,
            cookieParams:$oldCookies
        );
        $newRequest = $request->withCookieParams($newCookies);
        $this->assertSame($newCookies, $newRequest->getCookieParams());
        $this->assertSame($oldCookies, $request->getCookieParams());
    }

    public function testWithParsedBody()
    {
        $request = new ServerRequest(
            self::TEST_METHOD,
            $this->uri,
            $this->body
        );
        $newRequest = $request->withParsedBody(['key' => 'value']);
        $this->assertNotSame($request, $newRequest);
        $this->assertSame(['key' => 'value'], $newRequest->getParsedBody());
        $this->assertSame(null, $request->getParsedBody());
    }

    public function testWithParsedBodyWithInvalidArgument()
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ServerRequest(
            self::TEST_METHOD,
            $this->uri,
            $this->body
        ))->withParsedBody('hello');
    }

    public function testWithUploadedFiles()
    {
        $request = new ServerRequest(
            self::TEST_METHOD,
            $this->uri,
            $this->body
        );
        $isUploadedFileMock = (new MockBuilder())
            ->setNamespace('Pac\Http')
            ->setName('is_uploaded_file')
            ->setFunctionProvider(new FixedValueFunction(true))
            ->build();
        $newRequest = $request->withUploadedFiles([$isUploadedFileMock]);
        $this->assertNotSame($request, $newRequest);
        $this->assertSame([$isUploadedFileMock], $newRequest->getUploadedFiles());
        $this->assertSame([], $request->getUploadedFiles());
    }

    public function testWithUploadedFilesException()
    {
        $request = new ServerRequest(
            self::TEST_METHOD,
            $this->uri,
            $this->body
        );
        $this->expectException(\InvalidArgumentException::class);
        $request->withUploadedFiles([123]);
    }
}
