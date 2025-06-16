<?php

declare(strict_types=1);

namespace Pac\LeanHttp\Tests;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Pac\LeanHttp\Stream;

class StreamTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'test_stream_');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testConstructorOpensFile(): void
    {
        $stream = new Stream($this->tempFile, 'w+');
        $this->assertNotNull($stream);
    }

    public function testConstructorThrowsExceptionOnInvalidPath(): void
    {
        $this->expectException(\RuntimeException::class);
        new Stream('/invalid/path/to/file', 'w+');
    }

    #[TestWith(['Hello World!'])]
    public function testGetContents($contents): void
    {
        $stream = new Stream($this->tempFile, 'w+');
        $stream->write($contents);
        $stream->rewind();
        $this->assertEquals($contents, $stream->getContents());
    }

    #[TestWith(['w+', 'mode', 'w+'])]
    #[TestWith(['r', 'mode', 'r'])]
    #[TestWith(['r', 'made_up', null])]
    public function testGetMetadata($mode, $key, $expected): void
    {
        $stream = new Stream($this->tempFile, $mode);
        $this->assertEquals($expected, $stream->getMetadata($key));
    }

    #[TestWith(['Hello world!', 12])]
    #[TestWith(['', 0])]
    public function testGetSize($contents, $size): void
    {
        $stream = new Stream($this->tempFile, 'w+');
        $stream->write($contents);
        $this->assertEquals($size, $stream->getSize());
    }

    public function testGetSizeIfDetached(): void
    {
        $stream = new Stream($this->tempFile, 'w+');
        $stream->write('Hello!');
        $pointer = $stream->detach();
        $this->assertEquals(null, $stream->getSize());
        fclose($pointer);
    }

    public function testEof(): void
    {
        $stream = new Stream($this->tempFile, 'w+');
        $stream->write("Hello");
        $stream->rewind();
        $this->assertFalse($stream->eof());
        $stream->read(6);
        $this->assertTrue($stream->eof());
    }

    #[TestWith(['r', true])]
    #[TestWith(['w+', true])]
    #[TestWith(['w', false])]
    public function testIsReadable($mode, $expected): void
    {
        $stream = new Stream($this->tempFile, $mode);
        $this->assertEquals($expected, $stream->isReadable());
    }

    #[TestWith(['w', true])]
    #[TestWith(['r+', true])]
    #[TestWith(['r', false])]
    public function testIsWritable($mode, $expected): void
    {
        $stream = new Stream($this->tempFile, $mode);
        $this->assertEquals($expected, $stream->isWritable());
    }

    #[TestWith(['php://temp', 'w', true])]
    public function testIsSeekable($fpath, $mode, $expected): void
    {
        $stream = new Stream($fpath, $mode);
        $this->assertEquals($expected, $stream->isSeekable());
    }

    #[TestWith([0, 'This is a test...'])]
    #[TestWith([5, 'is a test...'])]
    #[TestWith([16, '.'])]
    #[TestWith([17, ''])]
    #[TestWith([100, ''])]
    public function testTell($seek, $contents): void
    {
        $stream = new Stream('php://temp', 'w+');
        $stream->write('This is a test...');
        $stream->seek($seek);
        $this->assertEquals($seek, $stream->tell());
        $this->assertEquals($contents, $stream->getContents());
    }

    public function testDetach(): void
    {
        $stream = new Stream($this->tempFile, 'w+');
        $filePointer = $stream->detach();
        $this->assertIsResource($filePointer);
        $this->assertTrue($stream->isNull());
        fclose($filePointer);
    }

    public function testRead(): void
    {
        $stream = new Stream('php://temp', 'w+');
        $stream->write('This is a test...');
        $this->assertEquals('', $stream->read(1));
        $stream->rewind();
        $this->assertEquals('T', $stream->read(1));
        $this->assertEquals('his', $stream->read(3));
        $this->assertEquals(' is a test...', $stream->read(100));
    }

    public function testClose(): void
    {
        $stream = new Stream($this->tempFile, 'w+');
        $stream->close();
        $this->assertTrue($stream->isNull());
    }

    public function testApply(): void
    {
        $stream = new Stream($this->tempFile, 'w+');
        $stream->write('aaa');
        $stream->apply(fn($filePointer) => fwrite($filePointer, 'bbb'));
        $stream->rewind();
        $this->assertEquals('aaabbb', $stream->getContents());
    }

    public function testWriteByContent(): void
    {
        $stream = new Stream($this->tempFile, 'w+');
        $stream->writeByContentType(
            'Hello world!',
            'text'
        );
        $this->assertSame(
            'Hello world!',
            (string) $stream
        );
    }

    public function testWriteByContentThrowsExceptionIfDataIsInvalid(): void
    {
        $stream = new Stream($this->tempFile, 'w+');
        $this->expectException(\InvalidArgumentException::class);
        $stream->writeByContentType(
            ['Hello world!'],
            'text'
        );
    }

    public function testWriteByContentJson(): void
    {
        $stream = new Stream($this->tempFile, 'w+');
        $stream->writeByContentType(
            ['name' => 'Pedro', 'age' => 41],
            'application/json'
        );
        $this->assertSame(
            '{"name":"Pedro","age":41}',
            (string) $stream
        );
    }

    public function testWriteByContentCsv(): void
    {
        $stream = new Stream($this->tempFile, 'w+');
        $stream->writeByContentType(
            [['name', 'age'], ['Pedro', 41]],
            'text/csv'
        );
        $this->assertSame(
            "name,age\nPedro,41\n",
            (string) $stream
        );
        $this->expectException(\InvalidArgumentException::class);
        $stream->writeByContentType(
            'xxx',
            'text/csv'
        );
    }

    public function testWriteByContentXml(): void
    {
        $stream = new Stream($this->tempFile, 'w+');
        $stream->writeByContentType(
            new \SimpleXMLElement('<?xml version="1.0"?>
<book>
    <title>1984</title>
    <author>George Orwell</author>
</book>'),
            'application/xml'
        );
        $this->assertSame('<?xml version="1.0"?>
<book>
    <title>1984</title>
    <author>George Orwell</author>
</book>
',
            (string) $stream
        );

        $stream = new Stream($this->tempFile, 'w+');
        $dom = new \DOMDocument();
        $dom->loadXML('<?xml version="1.0"?>
<book>
    <title>1984</title>
    <author>George Orwell</author>
</book>');
        $stream->writeByContentType($dom, 'text/xml');
        $this->assertSame('<?xml version="1.0"?>
<book>
    <title>1984</title>
    <author>George Orwell</author>
</book>
',
            (string) $stream
        );
    
        $this->expectException(\InvalidArgumentException::class);
        $stream->writeByContentType(
            'hello',
            'application/xml'
        );
    }

    public function testWriteByContentHtml(): void
    {
        $stream = new Stream($this->tempFile, 'w+');
        $dom = new \DOMDocument();
        $dom->loadHTML('<html>
    <head>
        <title>hello</title>
    </head>
    <body></body>
</html>');
        $dom->normalizeDocument();
        $stream->writeByContentType(
            $dom,
            'text/html'
        );
        $this->assertSame('<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" "http://www.w3.org/TR/REC-html40/loose.dtd">
<html>
    <head>
        <title>hello</title>
    </head>
    <body></body>
</html>
',
    (string) $stream
        );
    }
}
