<?php

declare(strict_types=1);

namespace Pac\LeanHttp;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

class UploadedFile implements UploadedFileInterface
{
    /**
     * The path to the temporary file.
     */
    private $stream;

    /**
     * Indicates if the file has been moved.
     */
    private bool $moved = false;

    /**
     * Constructor to initialize the UploadedFile instance.
     * This constructor is used to create an instance of UploadedFile from a temporary file on the server.
     * It checks if the file exists and is an uploaded file, and initializes the stream.
     * @param string $filePath
     * The path to the temporary file on the server.
     * @param string $clientFilename
     * The original name of the file as provided by the client.
     * @param string $clientMediaType
     * The media type of the file as provided by the client (e.g., 'image/png').
     * @param int $size
     * The size of the uploaded file in bytes. Defaults to 0.
     * @param int $error
     * The error code associated with the file upload. Defaults to UPLOAD_ERR_OK.
     * @throws \InvalidArgumentException
     * If the file does not exist or is not an uploaded file.
     * @throws \RuntimeException
     * If the file has already been moved or if there is an error with the upload.
     */
    public function __construct(
        public readonly string $filePath,
        public readonly string $clientFilename,
        public readonly ?string $clientMediaType=null,
        public readonly int $size = 0,
        public readonly int $error = UPLOAD_ERR_OK,
    ) {
        if ($this->error !== UPLOAD_ERR_OK) {
            $this->stream = null;
            return;
        }

        if (!file_exists($this->filePath)) {
            throw new \InvalidArgumentException("`$filePath` wasn't found.");
        }
        if (!is_uploaded_file($this->filePath)) {
            throw new \InvalidArgumentException("`$filePath` isn't an uploaded file.");
        }
        $this->stream = new Stream($this->filePath, 'r');
    }

    /**
     * Create an UploadedFile instance from an associative array.
     * This is useful for creating an instance from a $_FILES array.
     * @param array $data
     * The array should contain the keys 'tmp_name', 'name', 'type', 'size', and optionally 'error'.
     * - 'tmp_name': The path to the temporary file on the server.
     * - 'name': The original name of the file as provided by the client.
     * - 'type': The media type of the file as provided by the client (e.g., 'image/png').
     * - 'size': The size of the uploaded file in bytes.
     * - 'error': The error code associated with the file upload (optional, defaults to UPLOAD_ERR_OK).
     * @return UploadedFile
     * Returns a new instance of UploadedFile.
     * @throws \InvalidArgumentException
     * If the required keys are not present in the array or if the file does not exist.
     * @throws \RuntimeException
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
     * Return a $_FILE item.
     * @return array
     * This array will contain the keys 'name', 'type', 'tmp_name', 'size', and 'error'.
     * - 'name': The original name of the file as provided by the client.
     * - 'type': The media type of the file as provided by the client (e.g., 'image/png').
     * - 'tmp_name': The path to the temporary file on the server.
     * - 'size': The size of the uploaded file in bytes.
     * - 'error': The error code associated with the file upload.
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
     * Return, from $_FILES, a tree (as associative arrays) with instances UploadFile.
     * This method will traverse the $_FILES array and create a tree structure of UploadedFile instances.
     * It will handle both single files and multiple files (arrays of files).
     * @return array
     * The returned array will have the same structure as $_FILES, but each file will be an instance of UploadedFile.
     * - The keys will be the names of the files as they appear in $_FILES.
     * - The values will be arrays or instances of UploadedFile, depending on whether the file is a single file or an array of files.
     * @throws \InvalidArgumentException
     * If the required keys are not present in the array or if the file does not exist.
     * @throws \RuntimeException
     */
    public static function fromGlobal(?array $files=null): array
    {
        $uploadedFiles = [];
        $validKeys = ['name', 'type', 'tmp_name', 'size', 'error'];
        foreach ($files ?? $_FILES as $name => $data) {
            assert(isset($data['name'], $data['type'], $data['tmp_name'], $data['size'], $data['error']));
            if (!is_array($data['name'])) {
                $uploadedFiles[$name] = self::fromArray($data);
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($data['name']));
            foreach ($iterator as $fileName) {
                assert($fileName);
                // build the path of keys to reach the value
                $path = [];
                for ($depth = 0; $depth <= $iterator->getDepth(); $depth++) {
                    $path[] = $iterator->getSubIterator($depth)->key();
                }
                // add missing keys to $uploadedFiles[$name]
                $current = &$uploadedFiles[$name];
                foreach ($path as $step) {
                    if (!isset($current[$step])) {
                        $current[$step] = [];
                    }
                    $current = &$current[$step];
                }
                // create uploaded file data as an array before instantiating UploadedFile
                $uploadedFileArray = ['name' => $fileName];
                foreach ($validKeys as $propertyName) {
                    $crumb = $data[$propertyName];
                    foreach ($path as $key) {
                        assert(isset($crumb[$key]));
                        $crumb = $crumb[$key];
                    }
                    assert(is_string($crumb));
                    $uploadedFileArray[$propertyName] = $crumb;
                }
                $current = self::fromArray($uploadedFileArray);
            }
        }
        return $uploadedFiles;
    }

    /**
     * Validate a tree of uploaded files.
     * @param array $tree
     * This method will traverse the provided tree structure and check if all values are instances of UploadedFileInterface.
     * The tree can be a nested associative array where each value is an instance of UploadedFileInterface.
     * - The keys can be any string, and the values can be either instances of UploadedFileInterface or nested arrays.
     * - If any value in the tree is not an instance of UploadedFileInterface, the method will return false.
     * - If all values are instances of UploadedFileInterface, the method will return true.
     * @return bool
     * Returns true if all values in the tree are instances of UploadedFileInterface, false otherwise.
     * @throws \InvalidArgumentException
     * If the provided tree is not a valid structure or if it contains invalid values.
     * @throws \RuntimeException
     * If the tree structure is not valid or if it contains unexpected values.
     */
    public static function validateTree(array $tree): bool {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($tree));
        foreach ($iterator as $value) {
            if (!($value instanceof UploadedFileInterface)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get the stream associated with the uploaded file.
     * @return Stream
     * This method returns a StreamInterface instance that represents the uploaded file.
     * The stream can be used to read the contents of the uploaded file.
     * It is important to note that the stream is read-only and should not be modified.
     * If the file has already been moved, the stream will still point to the original temporary file.
     * @throws \RuntimeException
     * If the file has already been moved, this method will throw a RuntimeException.
     * This is to prevent accessing the stream after the file has been moved, as the original temporary file may no longer exist.
     * @return StreamInterface
     * Returns a StreamInterface instance that represents the uploaded file.
     * The stream can be used to read the contents of the uploaded file.
     * It is important to note that the stream is read-only and should not be modified.
     * If the file has already been moved, the stream will still point to the original temporary file.
     * @throws \RuntimeException
     * If the file has already been moved, this method will throw a RuntimeException.
     * This is to prevent accessing the stream after the file has been moved, as the original temporary file may no longer exist.
     */
	public function getStream(): StreamInterface 
    {
        if ($this->moved) {
            throw new \RuntimeException('Cannot retrieve stream after file has been moved.');
        }
        if ($this->error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Cannot retrieve stream due to upload error.');
        }
        return $this->stream;
    }

    /**
     * Move the uploaded file to a new location.
     * @param string $targetPath
     * The path to the target location where the file should be moved.
     * This path should be a valid file path on the server where the file will be stored.
     * The target path should not already exist, as this method will attempt to move the file to that location.
     * If the target path already exists, the method will throw an exception.
     * @throws \RuntimeException
     * If the file has already been moved, this method will throw a RuntimeException.
     * This is to prevent moving the file multiple times, as the file can only be moved once.
     * If the move operation fails, this method will also throw a RuntimeException.
     * This can happen if the target path is not writable, if the file does not exist, or if there are other issues with the file system.
     * @return void
     */
	public function moveTo(string $targetPath): void 
    {
        if ($this->moved) {
            throw new RuntimeException("The uploaded file `$this->filePath` was already moved.");
        }
        if ($this->error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Cannot move file due to upload error.');
        }
        if (!move_uploaded_file($this->filePath, $targetPath)) {
            throw new RuntimeException("Failed to move uploaded file `$this->filePath` to `$targetPath`.");
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
     * @return ?string
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
