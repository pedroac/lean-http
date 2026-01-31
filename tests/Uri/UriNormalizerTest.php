<?php

declare(strict_types=1);

namespace Pac\LeanHttp\Tests\Uri;

use Pac\LeanHttp\Uri\UriNormalizer;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class UriNormalizerTest extends TestCase
{
    #[TestWith(['special&chars', 'special%26chars'])]
    #[TestWith(['abc%20def', 'abc%20def'])]
    #[TestWith(['foo bar!', 'foo%20bar%21'])]
    #[TestWith(['foo+bar!', 'foo%20bar%21'])]
    public function testNormalizeEncode(string $original, string $expected): void
    {
        $this->assertSame(
            $expected,
            (UriNormalizer::getDefault())->normalizeEncode($original)
        );
    }

    #[TestWith(['http', 'http'])]
    #[TestWith(['HTTP', 'http'])]
    public function testNormalizeScheme(string $scheme, string $expected): void
    {
        $this->assertSame(
            $expected,
            (UriNormalizer::getDefault())->normalizeScheme($scheme)
        );
    }

    #[TestWith(['1'])]
    #[TestWith(['http!s'])]
    #[TestWith(['httpãs'])]
    #[TestWith(['http/s'])]
    #[TestWith(['http:s'])]
    #[TestWith(['http?s'])]
    #[TestWith(['http#s'])]
    public function testNormalizeSchemeThrowsIfInvalid(string $argument): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (UriNormalizer::getDefault())->normalizeScheme($argument);
    }

    #[TestWith(['example', 'example'])]
    #[TestWith(['www.example.com', 'www.example.com'])]
    #[TestWith(['WWW.EXAMPLE.COM', 'www.example.com'])]
    #[TestWith(['128.0.0.1', '128.0.0.1'])]
    #[TestWith(['123.56', '123.56'])]
    #[TestWith(['2001:0db8:0000:0000:0000:ff00:0042:8329', '[2001:db8::ff00:42:8329]'])]
    #[TestWith(['2001:db8:0:0:0:ff00:42:8329', '[2001:db8::ff00:42:8329]'])]
    #[TestWith(['2001:db8::ff00:42:8329', '[2001:db8::ff00:42:8329]'])]
    #[TestWith(['[2001:db8:0:0:0:ff00:42:8329]', '[2001:db8::ff00:42:8329]'])]
    #[TestWith(['[2001:db8::ff00:42:8329]', '[2001:db8::ff00:42:8329]'])]
    #[TestWith(['[2001:0db8:0000:0000:0000:ff00:0042:8329]', '[2001:db8::ff00:42:8329]'])]
    #[TestWith(['::1', '[::1]'])]
    #[TestWith(['1::', '[1::]'])]
    #[TestWith(['2001:db8:abcd:1234:5678:90ab:cdef:1234', '[2001:db8:abcd:1234:5678:90ab:cdef:1234]'])]
    public function testNormalizeHost(string $host, string $expected): void
    {
        $this->assertSame(
            $expected,
            (UriNormalizer::getDefault())->normalizeHost($host)
        );
    }

    #[TestWith([':'])]
    #[TestWith(['2001:db8::ff00:42:::'])]
    #[TestWith(['www.example!.com'])]
    #[TestWith(['www.exãmple.com'])]
    #[TestWith(['www.example:com'])]
    #[TestWith(['www.example/com'])]
    #[TestWith(['www.example?com'])]
    #[TestWith(['www.example#com'])]
    #[TestWith(['256.0.0.0'])]
    #[TestWith(['2001:db8:0:0:0:ff00:42'])]
    #[TestWith(['2001:db8::ff00:42:'])]
    public function testNormalizeHostThrowsIfInvalid(string $argument): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (UriNormalizer::getDefault())->normalizeHost($argument);
    }

    #[TestWith(['/about', '/about'])]
    #[TestWith(['/about////', '/about/'])]
    #[TestWith(['/////about/', '/about/'])]
    #[TestWith(['/////about////', '/about/'])]
    #[TestWith(['about', 'about'])]
    #[TestWith(['about/', 'about/'])]
    #[TestWith(['/about//team/', '/about/team/'])]
    #[TestWith(['/products/./electronics/', '/products/electronics/'])]
    #[TestWith(['/blog/2024/../2023/../2022', '/blog/2022'])]
    #[TestWith(['/docs/', '/docs/'])]
    #[TestWith(['/search%20results/../archive', '/archive'])]
    #[TestWith(['/../settings', '/settings'])]
    #[TestWith(['/', '/'])]
    #[TestWith(['docs/./intro/../overview', 'docs/overview'])]
    public function testNormalizePath(string $path, string $expected): void
    {
        $this->assertSame(
            $expected,
            (UriNormalizer::getDefault())->normalizePath($path)
        );
    }

    #[TestWith(['Tag=tech&tag=latest&Tag=tec', 'Tag=tech&tag=latest&Tag=tec'])]
    #[TestWith(['path=%2fhome%2fuser&path=/home/user', 'path=%2Fhome%2Fuser&path=%2Fhome%2Fuser'])]
    #[TestWith(['q=search results&lang=en&sort=asc', 'q=search%20results&lang=en&sort=asc'])]
    #[TestWith(['query=foo+bar&name=Jürgen', 'query=foo%20bar&name=J%C3%BCrgen'])]
    #[TestWith(['user=&active=true', 'user=&active=true'])]
    #[TestWith(['id=123&', 'id=123'])]
    #[TestWith(['User=Alice&user=alice', 'User=Alice&user=alice'])]
    #[TestWith(['tag=tech&tag=latest&tag=popular', 'tag=tech&tag=latest&tag=popular'])]
    public function testNormalizeQuery(string $query, string $expected): void
    {
        $this->assertSame(
            $expected,
            (UriNormalizer::getDefault())->normalizeQuery($query)
        );
    }


    #[TestWith(['b=2&a=1', 'b=2&a=1', false])]
    #[TestWith(['b=2&a=1', 'a=1&b=2', true])]
    /**
     * @covers ::normalizeQuery
     */
    public function testNormalizeQuerySort(string $query, string $expected, bool $sortQuery): void
    {
        $this->assertSame(
            $expected,
            (new UriNormalizer(sortQuery: $sortQuery))->normalizeQuery($query)
        );
    }
}
