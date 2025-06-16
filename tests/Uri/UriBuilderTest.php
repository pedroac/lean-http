<?php

declare(strict_types=1);

namespace Pac\LeanHttp\Tests\Uri;

use Pac\LeanHttp\Uri\UriBuilder;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class UriBuilderTest extends TestCase
{
    #[TestWith(['http', 'example.com', '', null, null, null, '', null, 'http://example.com'])]
    #[TestWith(['http', 'example.com', 'my/path', null, null, null, '', null, 'http://example.com/my/path'])]
    #[TestWith(['http', 'example.com', '/my/path', null, null, null, '', null, 'http://example.com/my/path'])]
    #[TestWith(['http', 'example.com', 'my/path', 'key=name', null, null, '', null, 'http://example.com/my/path?key=name'])]
    #[TestWith(['http', 'example.com', 'my/path', 'key=name', 'section', null, '', null, 'http://example.com/my/path?key=name#section'])]
    #[TestWith(['http', 'example.com', 'my/path', '', null, null, '', null, 'http://example.com/my/path?'])]
    #[TestWith(['http', 'example.com', 'my/path', 'key=name', '', null, '', null, 'http://example.com/my/path?key=name#'])]
    #[TestWith(['', 'example.com', '', null, null, null, '', null, 'example.com'])]
    #[TestWith(['', '', 'my/path', null, null, null, '', null, 'my/path'])]
    #[TestWith(['http', '', 'my/path', null, null, null, '', null, 'http:///my/path'])]
    #[TestWith(['', '', '', 'key=name', null, null, '', null, '?key=name'])]
    #[TestWith(['http', '', '', null, null, null, '', null, 'http://'])]
    public function testNormalizeScheme(
        string $scheme,
        string $host,
        string $path,
        ?string $query = null,
        ?string $fragment = null,
        ?int $port,
        string $user = '',
        ?string $password = null,
        string $expected
    ): void {
        $this->assertSame(
            $expected,
            (new UriBuilder(
                $scheme,
                $host,
                $path,
                $query,
                $fragment,
                $port,
                $user,
                $password
            ))->buildRaw()
        );
    }
}
