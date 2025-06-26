<?php

declare(strict_types=1);

namespace Pac\LeanHttp\Tests;

use InvalidArgumentException;
use Pac\LeanHttp\UploadedFile;
use phpmock\functions\FixedValueFunction;
use phpmock\MockBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class UploadedFileTest extends TestCase
{
    use \phpmock\phpunit\PHPMock;

    private string $tmpFile;

    private $fileExistsMock;

    private $isUploadedFileMock;

    private $moveUploadedFileMock;

    protected function setUp(): void
    {
        // TODO: Stop using namespaces for global functions.
        $this->isUploadedFileMock = (new MockBuilder)
            ->setNamespace('Pac\LeanHttp')
            ->setName('is_uploaded_file')
            ->setFunctionProvider(new FixedValueFunction(true))
            ->build();
        $this->moveUploadedFileMock = (new MockBuilder)
            ->setNamespace('Pac\LeanHttp')
            ->setName('move_uploaded_file')
            ->setFunction(function (string $from, string $to) {
                return rename($from, $to);
            })->build();
        $this->isUploadedFileMock->enable();
        $this->moveUploadedFileMock->enable();
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'phpunit');
        file_put_contents($this->tmpFile, 'test content');
    }

    protected function tearDown(): void
    {
        $this->isUploadedFileMock->disable();
        $this->moveUploadedFileMock->disable();
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testConstructorWithValidFile(): void
    {
        $uploadedFile = new UploadedFile(
            $this->tmpFile,
            'example.txt',
            'text/plain',
            12,
            UPLOAD_ERR_OK
        );

        $this->assertEquals('example.txt', $uploadedFile->getClientFilename());
        $this->assertEquals('text/plain', $uploadedFile->getClientMediaType());
        $this->assertEquals(12, $uploadedFile->getSize());
        $this->assertEquals(UPLOAD_ERR_OK, $uploadedFile->getError());
    }

    public function testConstructorThrowsExceptionIfFileNotFound(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UploadedFile(
            '/invalid/path/file.txt',
            'example.txt',
            'text/plain',
            12,
            UPLOAD_ERR_OK
        );
    }

    public function testConstructorThrowsExceptionIfNotUploadedFile(): void
    {
        $this->isUploadedFileMock->disable();
        $this->expectException(InvalidArgumentException::class);
        new UploadedFile(
            $this->tmpFile,
            'example.txt',
            'text/plain',
            12,
            UPLOAD_ERR_OK
        );
    }

    public function testFromGlobal(): void
    {
        $tmpFile1 = tempnam(sys_get_temp_dir(), 'phpunit');
        $tmpFile2 = tempnam(sys_get_temp_dir(), 'phpunit');
        $tmpFile3 = tempnam(sys_get_temp_dir(), 'phpunit');
        file_put_contents($tmpFile1, 'test content');
        file_put_contents($tmpFile2, 'test content');
        file_put_contents($tmpFile3, 'test content');

        $mockFiles = [
            'file1' => [
                'name' => 'test.txt',
                'type' => 'text/plain',
                'tmp_name' => $tmpFile1,
                'size' => 123,
                'error' => 0,
            ],
            'file2' => [
                'name' => ['nested' => 'image.png'],
                'type' => ['nested' => 'image/png'],
                'tmp_name' => ['nested' => $tmpFile2],
                'size' => ['nested' => 456],
                'error' => ['nested' => 0],
            ],
            'file3' => [
                'name' => ['a' => ['b' => 'image.jpg']],
                'type' => ['a' => ['b' => 'image/jpg']],
                'tmp_name' => ['a' => ['b' => $tmpFile3]],
                'size' => ['a' => ['b' => 789]],
                'error' => ['a' => ['b' => 0]],
            ],
        ];
        $uploadedFiles = UploadedFile::fromGlobal($mockFiles);
        $this->assertInstanceOf(UploadedFile::class, $uploadedFiles['file1']);
        $this->assertInstanceOf(UploadedFile::class, $uploadedFiles['file2']['nested']);
        $this->assertInstanceOf(UploadedFile::class, $uploadedFiles['file3']['a']['b']);
        $this->assertSame(
            [
                'file1' => [
                    'name' => 'test.txt',
                    'type' => 'text/plain',
                    'tmp_name' => $tmpFile1,
                    'size' => 123,
                    'error' => 0,
                ],
                'file2' => ['nested' => [
                    'name' => 'image.png',
                    'type' => 'image/png',
                    'tmp_name' => $tmpFile2,
                    'size' => 456,
                    'error' => 0,
                ]],
                'file3' => ['a' => ['b' => [
                    'name' => 'image.jpg',
                    'type' => 'image/jpg',
                    'tmp_name' => $tmpFile3,
                    'size' => 789,
                    'error' => 0,
                ]]],
            ],
            [
                'file1' => $uploadedFiles['file1']->toArray(),
                'file2' => ['nested' => $uploadedFiles['file2']['nested']->toArray()],
                'file3' => ['a' => ['b' => $uploadedFiles['file3']['a']['b']->toArray()]],
            ]
        );
        unlink($tmpFile1);
        unlink($tmpFile2);
        unlink($tmpFile3);
    }

    public function testMoveTo(): void
    {
        $uploadedFile = new UploadedFile(
            $this->tmpFile,
            'example.txt',
            'text/plain',
            12,
            UPLOAD_ERR_OK
        );

        $targetPath = sys_get_temp_dir() . '/target.txt';
        $uploadedFile->moveTo($targetPath);

        $this->assertFileExists($targetPath);
        $this->assertTrue($uploadedFile->wasMoved());

        unlink($targetPath);
    }

    public function testMoveToThrowsExceptionIfAlreadyMoved(): void
    {
        $uploadedFile = new UploadedFile(
            $this->tmpFile,
            'example.txt',
            'text/plain',
            12,
            UPLOAD_ERR_OK
        );

        $targetPath = sys_get_temp_dir() . '/target.txt';
        $uploadedFile->moveTo($targetPath);

        $this->moveUploadedFileMock->disable();
        $this->expectException(RuntimeException::class);
        $uploadedFile->moveTo($targetPath);

        unlink($targetPath);
    }

    public function testMoveToThrowsExceptionIfMoveFails(): void
    {
        $this->moveUploadedFileMock->disable();
        $this->moveUploadedFileMock = (new MockBuilder)
            ->setNamespace('Pac\LeanHttp')
            ->setName('move_uploaded_file')
            ->setFunctionProvider(new FixedValueFunction(false))
            ->build();
        $this->moveUploadedFileMock->enable();
        $uploadedFile = new UploadedFile(
            $this->tmpFile,
            'example.txt',
            'text/plain',
            12,
            UPLOAD_ERR_OK
        );

        $this->moveUploadedFileMock->disable();
        $this->expectException(RuntimeException::class);
        $uploadedFile->moveTo('/root/unwritable.txt');
    }

    public function testGetStream(): void
    {
        $uploadedFile = new UploadedFile(
            $this->tmpFile,
            'example.txt',
            'text/plain',
            12,
            UPLOAD_ERR_OK
        );

        $stream = $uploadedFile->getStream();
        $this->assertInstanceOf(StreamInterface::class, $stream);
    }

    public function testFromArray(): void
    {
        $data = [
            'tmp_name' => $this->tmpFile,
            'name' => 'example.txt',
            'type' => 'text/plain',
            'size' => 12,
            'error' => UPLOAD_ERR_OK
        ];

        $uploadedFile = UploadedFile::fromArray($data);

        $this->assertEquals('example.txt', $uploadedFile->getClientFilename());
        $this->assertEquals('text/plain', $uploadedFile->getClientMediaType());
        $this->assertEquals(12, $uploadedFile->getSize());
        $this->assertEquals(UPLOAD_ERR_OK, $uploadedFile->getError());
    }
}
