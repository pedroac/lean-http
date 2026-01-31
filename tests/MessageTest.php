<?php

declare(strict_types=1);

use Pac\LeanHttp\Message;
use Pac\LeanHttp\Stream;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

final class MessageTest extends TestCase
{
    private Message $message;
    private StreamInterface $stream;

    protected function setUp(): void
    {
        $this->stream = $this->createMock(StreamInterface::class);
        $this->message = new Message($this->stream, ['Content-Type' => 'application/json'], '1.1');
    }

    public function testGetBody(): void
    {
        $this->assertSame($this->stream, $this->message->getBody());
    }

    public function testGetHeader(): void
    {
        $this->assertSame(['application/json'], $this->message->getHeader('Content-Type'));
        $this->assertSame([], $this->message->getHeader('Non-Existent-Header'));
    }

    public function testGetHeaderLine(): void
    {
        $this->assertSame('application/json', $this->message->getHeaderLine('Content-Type'));
    }

    public function testGetHeaders(): void
    {
        $expectedHeaders = ['Content-Type' => ['application/json']];
        $this->assertSame($expectedHeaders, $this->message->getHeaders());
    }

    public function testGetProtocolVersion(): void
    {
        $this->assertSame('1.1', $this->message->getProtocolVersion());
    }

    public function testHasHeader(): void
    {
        $this->assertTrue($this->message->hasHeader('Content-Type'));
        $this->assertFalse($this->message->hasHeader('Non-Existent-Header'));
    }

    #[TestWith([['hello world'], ['hello world']])]
    #[TestWith(['hello world', ['hello world']])]
    public function testWithAddedHeader($value, $expected): void
    {
        $newMessage = $this->message->withAddedHeader('X-Custom-Header', $value);
        $this->assertSame(
            [
                'Content-Type' => ['application/json'],
                'X-Custom-Header' => $expected
,           ],
            $newMessage->getHeaders()
        );
    }

    public function testWithAddedHeaderThrowsIfValueIsInvalid(): void
    {
        $this->expectException(RuntimeException::class);
        $this->message->withAddedHeader('X-Custom-Header', 111);
    }

    public function testWithAddedHeaderThrowsIfValueIsInvalidArray(): void
    {
        $this->expectException(RuntimeException::class);
        $this->message->withAddedHeader('X-Custom-Header', [111]);
    }

    public function testWithBody(): void
    {
        $newStream = $this->createMock(StreamInterface::class);
        $newMessage = $this->message->withBody($newStream);
        $this->assertSame($newStream, $newMessage->getBody());
        $this->assertNotSame($this->message, $newMessage);
    }

    #[TestWith(['text/html', 'text/html'])]
    #[TestWith([['text/html'], 'text/html'])]
    public function testWithHeader($newHeaderValue, $expected): void
    {
        $newMessage = $this->message->withHeader('Content-Type', $newHeaderValue);
        $this->assertSame($expected, $newMessage->getHeaderLine('Content-Type'));
        $this->assertNotSame($this->message, $newMessage);
    }

    #[TestWith([111])]
    #[TestWith([[111]])]
    public function testWithHeaderThrowsIfValueIsInvalid($newHeaderValue): void
    {
        $this->expectException(RuntimeException::class);
        $this->message->withHeader('Content-Type', $newHeaderValue);
    }

    public function testWithoutHeader(): void
    {
        $newMessage = $this->message->withoutHeader('Content-Type');
        $this->assertFalse($newMessage->hasHeader('Content-Type'));
        $this->assertNotSame($this->message, $newMessage);
    }

    public function testWithProtocolVersion(): void
    {
        $newMessage = $this->message->withProtocolVersion('1.1');
        $this->assertSame('1.1', $newMessage->getProtocolVersion());
        $this->assertNotSame($this->message, $newMessage);
    }

    public static function parseBodyProvider(): \Generator
    {
        yield [
            'application/json',
            '{"user": {"username": "pac", "age": 40}}',
            ['user' => ['username' => 'pac', 'age' => 40]],
        ];
        yield [
            'text/csv',
            "username,age\npac,40",
            [['username', 'age'], ['pac', '40']],
        ];
    }

    public function testParseBody(): void
    {
        $stream = Stream::fromMemory();
        $stream->write('hello world');
        $message = new Message($stream, ['Content-Type' => 'text/text'], '1.1');
        $this->assertSame(
            ['hello world'],
            $message->parseBody()
        );
    }

    public function testParseBodyJson(): void
    {
        $stream = Stream::fromMemory();
        $stream->write('{"name":"Pedro","age":41}');
        $message = new Message($stream, ['Content-Type' => 'application/json'], '1.1');
        $parsedBody = $message->parseBody();
        $this->assertSame(
            ['name' => 'Pedro', 'age' => 41],
            $parsedBody
        );
    }

    public function testParseBodyCsv(): void
    {
        $stream = Stream::fromMemory();
        $stream->write("name,age\npedro,41\n");
        $message = new Message($stream, ['Content-Type' => 'text/csv'], '1.1');
        $parsedBody = $message->parseBody();
        $this->assertSame(
            [['name','age'],['pedro','41']],
            $parsedBody
        );
    }

    public function testParseBodyXml(): void
    {
        $stream = Stream::fromMemory();
        $stream->write('<root><user username="pac" age="40" /></root>');
        $message = new Message($stream, ['Content-Type' => 'text/xml'], '1.1');
        $parsedBody = $message->parseBody();
        $this->assertInstanceOf(\DOMDocument::class, $parsedBody);
        $this->assertSame(
            '<?xml version="1.0"?>
<root><user username="pac" age="40"/></root>
',
            (string)$parsedBody->saveXML()
        );
    }

    public function testParseBodyHtml(): void
    {
        $stream = Stream::fromMemory();
        $stream->write('<html><head><title>hello</title></head><body>world</body></html>');
        $message = new Message($stream, ['Content-Type' => 'text/html'], '1.1');
        $parsedBody = $message->parseBody();
        $this->assertInstanceOf(\DOMDocument::class, $parsedBody);
        $this->assertSame(
            '<?xml version="1.0" standalone="yes"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" "http://www.w3.org/TR/REC-html40/loose.dtd">
<html><head><title>hello</title></head><body>world</body></html>
',
            (string)$parsedBody->saveXML()
        );
    }
}
