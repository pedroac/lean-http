<?php

declare(strict_types=1);

namespace Pac\LeanHttp\Tests;

use Pac\LeanHttp\Request;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class RequestTest extends TestCase
{
    private Request $request;
    private StreamInterface $mockBody;
    private UriInterface $mockUri;

    protected function setUp(): void
    {
        $this->mockBody = $this->createMock(StreamInterface::class);
        $this->mockUri = $this->createMock(UriInterface::class);
        $this->request = new Request(
            'GET',
            $this->mockUri,
            $this->mockBody
        );
    }

    public function testGetRequestTarget(): void
    {
        $this->assertSame('/', $this->request->getRequestTarget());
    }

    public function testWithRequestTarget(): void
    {
        $newRequest = $this->request->withRequestTarget('/new-target');
        $this->assertSame('/new-target', $newRequest->getRequestTarget());
        $this->assertSame('/', $this->request->getRequestTarget());
    }

    public function testGetMethod(): void
    {
        $this->assertSame('GET', $this->request->getMethod());
    }

    public function testWithMethod(): void
    {
        $newRequest = $this->request->withMethod('POST');
        $this->assertSame('POST', $newRequest->getMethod());
        $this->assertSame('GET', $this->request->getMethod());
    }

    public function testGetUri(): void
    {
        $this->assertSame($this->mockUri, $this->request->getUri());
    }

    #[TestWith([true, 'example.com', 'example.com', 'example.com'])]
    #[TestWith([true, 'example.com', 'test.com', 'test.com'])]
    #[TestWith([false, 'example.com', 'test.com', 'example.com'])]
    public function testWithUri($preserveHeaderHost, $uriHost, $oldHeaderHost, $newHeaderHost): void
    {
        $newMockUri = $this->createMock(UriInterface::class);
        $newMockUri->method('getHost')
            ->willReturn($uriHost);
        $newRequest = $this->request->withHeader('Host', $oldHeaderHost);
        $newRequest = $newRequest->withUri($newMockUri, $preserveHeaderHost);
        $this->assertSame($newMockUri, $newRequest->getUri());
        $this->assertSame($this->mockUri, $this->request->getUri());
        $this->assertSame($newHeaderHost, $newRequest->getHeaderLine('Host'));
    }
}
