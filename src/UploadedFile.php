<?php

declare(strict_types=1);

namespace Pac\LeanHttp;

use Pac\LeanHttp\Exception\UploadedFileException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

class UploadedFile implements UploadedFileInterface
{
    /**
     * The stream for the uploaded file.
     */
    private StreamInterface $stream;

    /**
     * Indicates if the file has been moved.
     */
    private bool $moved = false;

    /**
     * Constructor to initialize the UploadedFile instance.
     *
     * Validates that the file exists and is an uploaded file before initializing the stream.
     *
     * @param string $filePath The path to the temporary uploaded file
     * @param string $clientFilename The original filename as provided by the client
     * @param ?string $clientMediaType The MIME type as provided by the client (e.g., 'image/png')
     * @param int $size The file size in bytes (default: 0)
     * @param int $error The upload error code (default: UPLOAD_ERR_OK)
     * @throws \InvalidArgumentException If the file doesn't exist or isn't an uploaded file
     */
    public function __construct(
        public readonly string $filePath,
        public readonly string $clientFilename,
        public readonly ?string $clientMediaType = null,
        public readonly int $size = 0,
        public readonly int $error = UPLOAD_ERR_OK,
    ) {
        if ($this->error !== UPLOAD_ERR_OK) {
            // For error cases, create a dummy stream that will be rejected by getStream()
            $this->stream = Stream::fromMemory('');

            return;
        }

        if (! file_exists($this->filePath)) {
            throw new \InvalidArgumentException("`$filePath` wasn't found.");
        }
        if (! is_uploaded_file($this->filePath)) {
            throw new \InvalidArgumentException("`$filePath` isn't an uploaded file.");
        }
        $this->stream = new Stream($this->filePath, 'r');
    }

    /**
     * Create an UploadedFile instance from a $_FILES array entry.
     *
     * @param array<string, mixed> $data Array with keys: 'tmp_name', 'name', 'type', 'size', 'error' (optional)
     * @return self A new UploadedFile instance
     * @throws \InvalidArgumentException If required keys are missing or file is invalid
     */
    public static function fromArray(
        array $data
    ): self {
        return new self(
            $data['tmp_name'],
            basename($data['name']),
            $data['type'],
            $data['size'],
            $data['error'] ?? UPLOAD_ERR_OK
        );
    }

    /**
     * Convert the UploadedFile to a $_FILES array format.
     *
     * @return array<string, mixed> Array with keys: 'name', 'type', 'tmp_name', 'size', 'error'
     */
    public function toArray(): array
    {
        return [
            'name' => $this->clientFilename,
            'type' => $this->clientMediaType,
            'tmp_name' => $this->filePath,
            'size' => $this->size,
            'error' => $this->error,
        ];
    }

    /**
     * Create a tree of UploadedFile instances from $_FILES array.
     *
     * Handles both single files and nested file arrays, preserving the structure of $_FILES.
     *
     * @param array<string|int, mixed>|null $files The $_FILES array or null to use global $_FILES
     * @return array<string|int, mixed> Tree structure matching $_FILES with UploadedFile instances
     * @throws \InvalidArgumentException If file data is invalid or missing required keys
     */
    public static function fromGlobal(?array $files = null): array
    {
        $uploadedFiles = [];
        $validKeys = ['name', 'type', 'tmp_name', 'size', 'error'];
        foreach ($files ?? $_FILES as $name => $data) {
            if (! is_array($data)) {
                throw new \InvalidArgumentException("Invalid file data for key '$name': expected array, got " . gettype($data));
            }
            if (! isset($data['name'], $data['type'], $data['tmp_name'], $data['size'], $data['error'])) {
                throw new \InvalidArgumentException("Invalid file data for key '$name': missing required keys (name, type, tmp_name, size, error)");
            }
            if (! is_array($data['name'])) {
                $uploadedFiles[$name] = self::fromArray($data);

                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($data['name']));
            foreach ($iterator as $fileName) {
                if (! is_string($fileName) || $fileName === '') {
                    throw new \InvalidArgumentException("Invalid file name for key '$name': expected non-empty string, got " . gettype($fileName));
                }
                // build the path of keys to reach the value
                $path = [];
                for ($depth = 0; $depth <= $iterator->getDepth(); $depth++) {
                    $path[] = $iterator->getSubIterator($depth)->key();
                }
                // add missing keys to $uploadedFiles[$name]
                $current = &$uploadedFiles[$name];
                foreach ($path as $step) {
                    if (! isset($current[$step])) {
                        $current[$step] = [];
                    }
                    $current = &$current[$step];
                }
                // create uploaded file data as an array before instantiating UploadedFile
                $uploadedFileArray = ['name' => $fileName];
                $valid = true;
                foreach ($validKeys as $propertyName) {
                    if (! isset($data[$propertyName])) {
                        $valid = false;

                        break;
                    }
                    $crumb = $data[$propertyName];
                    foreach ($path as $key) {
                        if (! is_array($crumb) || ! isset($crumb[$key])) {
                            $valid = false;

                            break 2;
                        }
                        $crumb = $crumb[$key];
                    }
                    if ($propertyName === 'error') {
                        $uploadedFileArray[$propertyName] = is_numeric($crumb) ? (int)$crumb : UPLOAD_ERR_OK;
                    } elseif ($propertyName === 'size') {
                        $uploadedFileArray[$propertyName] = is_numeric($crumb) ? (int)$crumb : 0;
                    } elseif (! is_string($crumb)) {
                        $valid = false;

                        break;
                    } else {
                        $uploadedFileArray[$propertyName] = $crumb;
                    }
                }
                if ($valid && isset($uploadedFileArray['name'], $uploadedFileArray['type'], $uploadedFileArray['tmp_name'], $uploadedFileArray['size'], $uploadedFileArray['error'])) {
                    $current = self::fromArray($uploadedFileArray);
                }
            }
        }

        return $uploadedFiles;
    }

    /**
     * Validate a tree of uploaded files.
     *
     * Recursively checks that all values in the tree are UploadedFileInterface instances.
     *
     * @param array<string|int, mixed> $tree Nested array structure with UploadedFileInterface instances
     * @return bool True if all values are UploadedFileInterface instances
     */
    public static function validateTree(array $tree): bool
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($tree));
        foreach ($iterator as $value) {
            if (! ($value instanceof UploadedFileInterface)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the stream associated with the uploaded file.
     *
     * The stream is read-only and points to the temporary uploaded file.
     *
     * @return StreamInterface The stream for reading the uploaded file
     * @throws UploadedFileException If the file has been moved or has an upload error
     */
    public function getStream(): StreamInterface
    {
        if ($this->moved) {
            throw new UploadedFileException('Cannot retrieve stream after file has been moved.');
        }
        if ($this->error !== UPLOAD_ERR_OK) {
            throw new UploadedFileException('Cannot retrieve stream due to upload error.');
        }

        return $this->stream;
    }

    /**
     * Move the uploaded file to a new location.
     *
     * Creates the target directory if it doesn't exist and validates permissions.
     * The file can only be moved once.
     *
     * @param string $targetPath The destination path for the file
     * @return void
     * @throws UploadedFileException If the file has been moved, has an upload error, or the move operation fails
     */
    public function moveTo(string $targetPath): void
    {
        if ($this->moved) {
            throw new UploadedFileException("The uploaded file `$this->filePath` was already moved.");
        }
        if ($this->error !== UPLOAD_ERR_OK) {
            throw new UploadedFileException('Cannot move file due to upload error.');
        }
        $targetPath = rtrim($targetPath, '/');
        $targetDir = dirname($targetPath);
        if ($targetDir === '.' || $targetDir === '') {
            throw new \InvalidArgumentException("Invalid target path: $targetPath");
        }
        if (! is_dir($targetDir) && ! mkdir($targetDir, 0755, true)) {
            $error = error_get_last();
            $errorMsg = $error ? $error['message'] : 'Unknown error';

            throw new UploadedFileException("Failed to create target directory `$targetDir`: $errorMsg");
        }
        if (! move_uploaded_file($this->filePath, $targetPath)) {
            $error = error_get_last();
            $errorMsg = $error ? $error['message'] : 'Unknown error';

            throw new UploadedFileException("Failed to move uploaded file `$this->filePath` to `$targetPath`: $errorMsg");
        }
        $this->moved = true;
    }

    /**
     * Get the size of the uploaded file.
     * @return int
     * This method returns the size of the uploaded file in bytes.
     * The size is determined at the time of the file upload and is not affected by any subsequent operations.
     * If the file was not uploaded successfully, the size will be 0.
     * @throws \RuntimeException
     * If the file has already been moved, this method will still return the size of the original file.
     * This is because the size is determined at the time of the file upload and does not change after the file has been moved.
     * @return int
     * Returns the size of the uploaded file in bytes.
     * The size is determined at the time of the file upload and is not affected by any subsequent operations.
     * If the file was not uploaded successfully, the size will be 0.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Get the error code associated with the uploaded file.
     * @return int
     * This method returns the error code associated with the file upload.
     * The error code is determined at the time of the file upload and indicates whether the upload was successful or if there were any issues.
     * The error codes are defined by the PHP constants for file upload errors, such as UPLOAD_ERR_OK, UPLOAD_ERR_INI_SIZE, etc.
     * If the file was uploaded successfully, the error code will be UPLOAD_ERR_OK (0).
     */
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * Get the client filename of the uploaded file.
     * @return string
     * This method returns the original name of the file as provided by the client.
     * The client filename is the name of the file that was uploaded by the user, and it may include the file extension.
     * It is important to note that the client filename may not be unique, and it is recommended to sanitize or rename the file when storing it on the server.
     */
    public function getClientFilename(): string
    {
        return $this->clientFilename;
    }

    /**
     * Get the client media type of the uploaded file.
     * @return string
     * This method returns the media type of the file as provided by the client.
     * The client media type is typically the MIME type of the file, such as 'image/png', 'text/plain', etc.
     * It is important to note that the client media type may not always be accurate, and it is recommended to validate or sanitize the file type when processing the uploaded file.
     * If the client did not provide a media type, this method will return an empty string.
     */
    public function getClientMediaType(): string
    {
        return $this->clientMediaType ?? '';
    }

    /**
     * Check if the method moveTo was already called.
     * This method is useful to determine if the file has already been moved to a new location.
     * It can be used to prevent multiple calls to moveTo, which would result in an error.
     * @return bool Was the method moveTo already called?
     * This method returns true if the file has already been moved to a new location, false otherwise.
     * If the file has been moved, it means that the original temporary file is no longer available for further operations.
     * If the file has not been moved, it means that the original temporary file is still available for operations such as reading or moving.
     */
    public function wasMoved(): bool
    {
        return $this->moved;
    }
}
