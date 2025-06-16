<?php

declare(strict_types=1);

namespace Pac\LeanHttp;

use Psr\Http\Message\StreamInterface;

/**
 * Represents a stream that can be read from and written to.
 * This class implements the StreamInterface and provides methods to manipulate the stream.
 * It supports various operations such as reading, writing, seeking, and checking the stream's state.
 */
class Stream implements StreamInterface
{
    /**
     * The file pointer resource.
     * This is the underlying resource that represents the stream.
     * It is used to perform read and write operations on the stream.
     */
    private $filePointer;

    /**
     * Metadata for the stream.
     * This is an associative array that contains metadata about the stream, such as its mode, size, and other properties.
     * It is lazily loaded when requested.
     */
    private $metaData;

    /**
     * Constructor to initialize the stream with a file path and mode.
     * 
     * @param string $path
     * The path to the file or stream resource.
     * @param string $mode
     * The mode in which the file should be opened (default is an empty string).
     * @throws \RuntimeException
     * If the file cannot be opened.
     */
    function __construct(
        string $path,
        string $mode = ''
    ) {
        $this->filePointer = @fopen($path, $mode);
        if (!$this->filePointer) {
            throw new \RuntimeException("Couldn't open the file `$path`.");
        }
    }

    /**
     * Create a new Stream instance from a file path.
     * 
     * @param string $path
     * The path to the file or stream resource.
     * @param string $mode
     * The mode in which the file should be opened (default is 'r').
     * @return self
     * Creates a new Stream instance that reads from the specified file path.
     */
    static function fromMemory(string $content = null): self
    {
        $newInstance = new self('php://memory', 'w+');
        if ($content !== null) {
            $newInstance->write($content);
        }
        return $newInstance;
    }

    /**
     * Create a new Stream instance from a temporary file.
     * 
     * @param int|null $bytes
     * The size of the temporary memory stream in bytes. If null, it defaults to an unlimited size.
     * @return self
     * Creates a new Stream instance that uses a temporary memory stream.
     * If $bytes is specified, it creates a memory stream with the specified size.
     * If $bytes is null, it creates a memory stream with an unlimited size.
     */
    static function fromTemporary(?int $bytes = null): self
    {
        return new self(
            'php://temp' . ($bytes === null ? '' : "/memory:$bytes"),
            'w+'
        );
    }

    /**
     * Create a new Stream instance from a file path.
     * 
     * @param string $path
     * The path to the file or stream resource.
     * @param string $mode
     * The mode in which the file should be opened (default is 'r').
     * @return self
     * Creates a new Stream instance that reads from the specified file path.
     * If the file does not exist, it throws a RuntimeException.
     */
    static function fromInput(): self
    {
        return new self('php://input', 'r');
    }

    /**
     * Create a new Stream instance that writes to the output stream.
     * 
     * @return self
     * Creates a new Stream instance that writes to the output stream (php://output).
     * This stream is typically used for sending output to the browser or console.
     * It opens the stream in write mode ('w'), allowing data to be written to it.
     * If the stream cannot be opened, it throws a RuntimeException.
     */
    static function fromOutput(): self
    {
        return new self('php://output', 'w');
    }

    function __destruct()
    {
        if ($this->filePointer) {
            fclose($this->filePointer);
        }
    }

    /**
     * Convert the stream to a string representation.
     * 
     * This method rewinds the stream to the beginning and reads its contents,
     * returning them as a string. It is useful for getting the entire content of the stream.
     *
     * @return string
     * Returns the contents of the stream as a string.
     */
    function __toString(): string
    {
        try {
            $this->rewind();
            return stream_get_contents($this->filePointer) ?: '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Close the stream.
     * 
     * This method closes the file pointer associated with the stream.
     * After calling this method, the stream cannot be used for reading or writing.
     * It sets the file pointer to null to indicate that the stream is closed.
     */
    function close(): void
    {
        fclose($this->filePointer);
        $this->filePointer = null;
    }

    /**
     * Detach the stream from its underlying resource.
     * 
     * This method returns the file pointer resource and sets the file pointer to null.
     * After calling this method, the stream is in an unusable state.
     * It is useful when you want to take ownership of the file pointer resource.
     *
     * @return mixed
     * Returns the file pointer resource, which can be used for further operations.
     */
    function detach(): mixed
    {
        $filePointer = $this->filePointer;
        $this->filePointer = null;
        return $filePointer;
    }

    /**
     * Check if the end of the stream has been reached.
     * 
     * This method checks if the file pointer has reached the end of the stream.
     * It returns true if the end of the stream has been reached, otherwise false.
     * If the stream is not open, it returns true.
     *
     * @return bool
     * Returns true if the end of the stream has been reached, false otherwise.
     */
    function eof(): bool
    {
        return $this->filePointer ? feof($this->filePointer) : true;
    }

    /**
     * Get the contents of the stream.
     * 
     * This method reads the entire contents of the stream and returns it as a string.
     * It is useful for getting all data from the stream without needing to read it in chunks.
     *
     * @return string
     * Returns the contents of the stream as a string.
     */
    function getContents(): string
    {
        return stream_get_contents($this->getHandlerOrThrow());
    }

    /**
     * Get metadata for the stream.
     * 
     * This method retrieves metadata for the stream, such as its mode, size, and other properties.
     * If a specific key is provided, it returns the value for that key; otherwise, it returns null.
     *
     * @param string|null $key
     * The key for which to retrieve metadata. If null, returns null.
     * @return mixed
     * Returns the metadata value for the specified key or null if the key does not exist.
     */
    function getMetadata(string|null $key = null): mixed
    {
        if (!$this->metaData) {
            $this->metaData = stream_get_meta_data($this->filePointer);
        }
        if ($key === null) {
            return $this->metaData;
        }
        return $this->metaData[$key] ?? null;
    }

    /**
     * Get the size of the stream.
     * 
     * This method returns the size of the stream in bytes.
     * If the stream is not open or the size cannot be determined, it returns null.
     *
     * @return int|null
     * Returns the size of the stream in bytes or null if the size cannot be determined.
     */
    function getSize(): int|null
    {
        if (!$this->filePointer) {
            return null;
        }
        return fstat($this->filePointer)['size'] ?? null;
    }

    /**
     * Check if the stream is readable.
     * 
     * This method checks if the stream can be read from.
     * It returns true if the stream is open in a readable mode, otherwise false.
     *
     * @return bool
     * Returns true if the stream is readable, false otherwise.
     */
    function isReadable(): bool
    {
        $mode = $this->getMetadata('mode');
        return strpbrk($mode, 'r+') !== false;
    }

    /**
     * Check if the stream is seekable.
     * 
     * This method checks if the stream supports seeking operations.
     * It returns true if the stream can be seeked, otherwise false.
     *
     * @return bool
     * Returns true if the stream is seekable, false otherwise.
     */
    function isSeekable(): bool
    {
        return $this->getMetadata('seekable') ?? false;
    }

    /**
     * Check if the stream is writable.
     * 
     * This method checks if the stream can be written to.
     * It returns true if the stream is open in a writable mode, otherwise false.
     *
     * @return bool
     * Returns true if the stream is writable, false otherwise.
     */
    function isWritable(): bool
    {
        $mode = $this->getMetadata('mode');
        return strpbrk($mode, 'waxc+') !== false;
    }

    /**
     * Read a specified number of bytes from the stream.
     * 
     * This method reads a specified number of bytes from the stream and returns them as a string.
     * If the end of the stream is reached before reading the specified number of bytes, it returns whatever is available.
     *
     * @param int $length
     * The number of bytes to read from the stream.
     * @return string
     * Returns the read bytes as a string.
     */
    function read(int $length): string
    {
        return fread($this->getHandlerOrThrow(), $length);
    }

    /**
     * Rewind the stream to the beginning.
     * 
     * This method sets the file pointer back to the start of the stream.
     * It is useful when you want to read or write from the beginning of the stream again.
     *
     * @return void
     */
    function rewind(): void
    {
        rewind($this->getHandlerOrThrow());
    }

    /**
     * Seek to a specific position in the stream.
     * 
     * This method moves the file pointer to a specified offset within the stream.
     * The offset can be relative to the beginning, current position, or end of the stream.
     *
     * @param int $offset
     * The offset in bytes to seek to.
     * @param int $whence
     * The reference point for the offset (default is SEEK_SET).
     * It can be SEEK_SET (beginning), SEEK_CUR (current position), or SEEK_END (end).
     * @return void
     */
    function seek(int $offset, int $whence = SEEK_SET): void
    {
        fseek($this->getHandlerOrThrow(), $offset, $whence);
    }

    /**
     * Get the current position of the file pointer in the stream.
     * 
     * This method returns the current position of the file pointer within the stream.
     * It is useful for determining where you are in the stream.
     *
     * @return int
     * Returns the current position of the file pointer in bytes.
     */
    function tell(): int
    {
        return ftell($this->getHandlerOrThrow());
    }

    /**
     * Write a string to the stream.
     * 
     * This method writes a string to the stream and returns the number of bytes written.
     * If the stream is not writable, it throws an exception.
     *
     * @param string $string
     * The string to write to the stream.
     * @return int
     * Returns the number of bytes written to the stream.
     */
    function write(string $string): int
    {
        return fwrite($this->getHandlerOrThrow(), $string);
    }

    /**
     * Check if the stream is null (i.e., not initialized).
     * 
     * This method checks if the file pointer is null, indicating that the stream has not been initialized or has been closed.
     * It returns true if the stream is null, otherwise false.
     *
     * @return bool
     * Returns true if the stream is null, false otherwise.
     */
    public function isNull(): bool
    {
        return $this->filePointer === null;
    }

    /**
     * Apply a callable function to the stream.
     * 
     * This method allows you to apply a callable function to the file pointer of the stream.
     * It is useful for performing custom operations on the stream without exposing the file pointer directly.
     *
     * @param callable $callable
     * A callable function that takes the file pointer as an argument.
     * @return mixed
     * Returns the result of the callable function applied to the file pointer.
     */
    public function apply(callable $callable): mixed
    {
        return $callable($this->getHandlerOrThrow());
    }

    /**
     * Write data to the stream based on the specified Content-Type.
     * 
     * This method writes data to the stream according to the specified Content-Type.
     * It supports various content types such as JSON, CSV, XML, HTML, and plain text.
     *
     * @param mixed $data
     * The data to write to the stream. The type of data depends on the Content-Type.
     * @param string $mainType
     * The main Content-Type (e.g., 'application/json', 'text/csv', 'text/xml', etc.).
     * @throws \InvalidArgumentException
     * If the data type does not match the expected type for the specified Content-Type.
     */
    public function writeByContentType(
        mixed $data,
        string $mainType
    ) {
        switch ($mainType) {
            case 'application/json':
                $this->write(json_encode($data, \JSON_THROW_ON_ERROR));
                break;
            case 'text/csv':
                if (!is_array($data)) {
                    throw new \InvalidArgumentException("Invalid data type for Content-Type `$mainType`. Expected array.");
                }
                foreach ($data as $row) {
                    $this->apply(fn ($filePointer) => fputcsv($filePointer, $row));
                }
                break;
            case 'text/xml':
            case 'application/xml':
            case 'text/html':
                if ($data instanceof \SimpleXMLElement) {
                    $this->write($data->asXML());
                } else if ($data instanceof \DOMDocument) {
                    $this->write(
                        $mainType === 'text/html'
                            ? $data->saveHTML()
                            : $data->saveXML()
                    );
                } else {
                    throw new \InvalidArgumentException("Invalid data type for Content-Type `$mainType`. Expected \\SimpleXMLElement, \\DOMDocument or string.");
                }
                break;
            default:
                if (!is_string($data) && !$data instanceof \Stringable) {
                    throw new \InvalidArgumentException("Invalid data type for Content-Type `$mainType`. Expected a string.");
                }
                $this->write($data);
        }
    }

    /**
     * Get the file pointer resource or throw an exception if the stream is detached or closed.
     * 
     * This method checks if the file pointer is valid and returns it.
     * If the file pointer is null, it throws a RuntimeException indicating that the stream is detached or closed.
     *
     * @return mixed
     * Returns the file pointer resource.
     * @throws \RuntimeException
     * If the stream is detached or closed.
     */
    private function getHandlerOrThrow(): mixed
    {
        if (!$this->filePointer) {
            throw new \RuntimeException('Stream is detached or closed.');
        }
        return $this->filePointer;
    }
}
