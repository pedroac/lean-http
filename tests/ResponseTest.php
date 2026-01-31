<?php

declare(strict_types=1);

namespace Pac\LeanHttp\Tests;

use Pac\LeanHttp\Message;
use Pac\LeanHttp\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

class ResponseTest extends TestCase
{
    private StreamInterface $mockStream;

    protected function setUp(): void
    {
        $this->mockStream = $this->createMock(StreamInterface::class);
    }

    public function testResponseInitialization(): void
    {
        $response = new Response(200, $this->mockStream, [], 'OK', '1.1');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getReasonPhrase());
        $this->assertEquals('1.1', $response->getProtocolVersion());
    }

    public function testResponseInitializationWithDefaultArguments(): void
    {
        $response = new Response(200, $this->mockStream, []);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getReasonPhrase());
        $this->assertEquals(Message::DEFAULT_PROTOCOL_VERSION, $response->getProtocolVersion());
    }

    public function testGetReasonPhrase(): void
    {
        $response = new Response(404, $this->mockStream, [], 'Not Found', '1.1');
        $this->assertEquals('Not Found', $response->getReasonPhrase());
    }

    public function testGetStatusCode(): void
    {
        $response = new Response(500, $this->mockStream, [], 'Internal Server Error', '1.1');
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testWithStatusCode(): void
    {
        $response = new Response(500, $this->mockStream, [], 'Internal Server Error', '1.1');
        $newResponse = $response->withStatus(404);
        $this->assertEquals(404, $newResponse->getStatusCode());
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testWithStatusReturnsClonedInstance(): void
    {
        $response = new Response(200, $this->mockStream, [], 'OK', '1.1');
        $newResponse = $response->withStatus(404, 'Not Found');
        $this->assertEquals(404, $newResponse->getStatusCode());
        $this->assertEquals('Not Found', $newResponse->getReasonPhrase());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getReasonPhrase());
        $this->assertNotSame($response, $newResponse);
    }

    public function testByContentTypeJson(): void
    {
        $response = Response::byContentType(
            200,
            ['title' => 'The Name of the Rose', 'year' => 1980],
            ['Content-Type' => 'application/json'],
            'OK',
            '2.0'
        );
        $this->assertEquals('{"title":"The Name of the Rose","year":1980}', (string)$response->getBody());
        $this->assertEquals(200, (string)$response->getStatusCode());
        $this->assertEquals('OK', (string)$response->getReasonPhrase());
        $this->assertEquals('2.0', (string)$response->getProtocolVersion());
    }
}
