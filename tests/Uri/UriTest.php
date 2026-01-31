<?php

declare(strict_types=1);

namespace Pac\LeanHttp\Tests\Uri;

use Pac\LeanHttp\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RawUriTest extends TestCase
{
    public static function classProvider(): \Generator
    {
        yield [Uri::class];
    }

    #[DataProvider('classProvider')]
    public function testUriToString(string $className): void
    {
        $uri = new $className('https://user:pass@www.example.com:8080/path?query=value#fragment');
        $this->assertEquals('https://user:pass@www.example.com:8080/path?query=value#fragment', (string) $uri);
    }

    #[DataProvider('classProvider')]
    public function testGetAuthority($className): void
    {
        $uri = new $className('https://user:pass@www.example.com:8080/path');
        $this->assertEquals('user:pass@www.example.com:8080', $uri->getAuthority());
    }

    #[DataProvider('classProvider')]
    public function testGetFragment($className): void
    {
        $uri = new $className('https://www.example.com/path#fragment');
        $this->assertEquals('fragment', $uri->getFragment());
    }

    #[DataProvider('classProvider')]
    public function testGetHost($className): void
    {
        $uri = new $className('https://www.example.com/path');
        $this->assertEquals('www.example.com', $uri->getHost());
    }

    #[DataProvider('classProvider')]
    public function testGetPath($className): void
    {
        $uri = new $className('https://www.example.com/path/to/resource');
        $this->assertEquals('/path/to/resource', $uri->getPath());
    }

    #[DataProvider('classProvider')]
    public function testGetPort($className): void
    {
        $uri = new $className('https://www.example.com:8080/path');
        $this->assertEquals(8080, $uri->getPort());
    }

    #[DataProvider('classProvider')]
    public function testGetQuery($className): void
    {
        $uri = new $className('https://www.example.com/path?query=value');
        $this->assertEquals('query=value', $uri->getQuery());
    }

    #[DataProvider('classProvider')]
    public function testGetScheme($className): void
    {
        $uri = new $className('https://www.example.com/path');
        $this->assertEquals('https', $uri->getScheme());
    }

    #[DataProvider('classProvider')]
    public function testGetUserInfo($className): void
    {
        $uri = new $className('https://user:pass@www.example.com/path');
        $this->assertEquals('user:pass', $uri->getUserInfo());
    }

    #[DataProvider('classProvider')]
    public function testWithFragment($className): void
    {
        $uri = new $className('https://www.example.com/path');
        $newUri = $uri->withFragment('new-fragment');
        $this->assertEquals('https://www.example.com/path#new-fragment', (string) $newUri);
    }

    #[DataProvider('classProvider')]
    public function testWithHost($className): void
    {
        $uri = new $className('https://www.example.com/path');
        $newUri = $uri->withHost('newhost.com');
        $this->assertEquals('https://newhost.com/path', (string) $newUri);
    }

    #[DataProvider('classProvider')]
    public function testWithPath($className): void
    {
        $uri = new $className('https://www.example.com/path');
        $newUri = $uri->withPath('/new-path');
        $this->assertEquals('https://www.example.com/new-path', (string) $newUri);
    }

    #[DataProvider('classProvider')]
    public function testWithPort($className): void
    {
        $uri = new $className('https://www.example.com/path');
        $newUri = $uri->withPort(9090);
        $this->assertEquals('https://www.example.com:9090/path', (string) $newUri);
    }

    #[DataProvider('classProvider')]
    public function testWithQuery($className): void
    {
        $uri = new $className('https://www.example.com/path');
        $newUri = $uri->withQuery('newquery=value');
        $this->assertEquals('https://www.example.com/path?newquery=value', (string) $newUri);
    }

    #[DataProvider('classProvider')]
    public function testWithScheme($className): void
    {
        $uri = new $className('http://www.example.com/path');
        $newUri = $uri->withScheme('https');
        $this->assertEquals('https://www.example.com/path', (string) $newUri);
    }

    #[DataProvider('classProvider')]
    public function testWithUserInfo($className): void
    {
        $uri = new $className('https://www.example.com/path');
        $newUri = $uri->withUserInfo('newuser', 'newpass');
        $this->assertEquals('https://newuser:newpass@www.example.com/path', (string) $newUri);
    }
}
