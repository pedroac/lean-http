<?php

declare(strict_types=1);

namespace Pac\LeanHttp\Tests\Uri;

use Pac\LeanHttp\Uri\UriValidator;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class UriValidatorTest extends TestCase
{
    #[TestWith(['http', true])]
    #[TestWith(['', false])]
    #[TestWith(['1ttp', false])]
    #[TestWith(['htt~', false])]
    public function testValidateScheme(string $scheme, bool $expected): void
    {
        $this->assertSame(
            $expected,
            (UriValidator::getDefault())->validateScheme($scheme)
        );
    }

    #[TestWith(['localhost ', true])]
    #[TestWith(['www.example.com', true])]
    #[TestWith(['192.168.1.1', true])]
    #[TestWith(['2001:0db8:85a3:0000:0000:8a2e:0370:7334', true])]
    #[TestWith(['2001:db8::ff00:42:8329', true])]
    #[TestWith(['::1', true])]
    #[TestWith(['::', true])]
    #[TestWith(['::ffff:192.168.1.', true])]
    #[TestWith(['2001:db8:1234:5678:90ab:cdef:1234:5678', true])]
    #[TestWith(['[2001:db8:1234:5678:90ab:cdef:1234:5678]', true])]
    #[TestWith(['', false])]
    #[TestWith(['www.example!.com ', false])]
    #[TestWith(['my site.com', false])]
    #[TestWith(['example..com', false])]
    #[TestWith(['example-.com', false])]
    #[TestWith(['256.256.256.256', false])]
    #[TestWith(['2001:0db8:85a3:0000:0000:8a2e:0370:7334:1234', false])]
    #[TestWith(['2001:db8:85a3:0000:0000:8a2e:0370:zzzz', false])]
    #[TestWith(['2001:0db8:85a3:0000:0000:8a2e:0370:7334:9999', false])]
    public function validateHost(string $host, string $expected): void
    {
        $this->assertSame(
            $expected,
            (UriValidator::getDefault())->validateHost($host)
        );
    }

    #[TestWith([80, true])]
    #[TestWith([65535, true])]
    #[TestWith([-1, false])]
    #[TestWith([65536, false])]
    public function testValidatePort(int $port, bool $expected): void
    {
        $this->assertSame(
            $expected,
            (UriValidator::getDefault())->validatePort($port)
        );
    }

    #[TestWith(['', true])]
    #[TestWith(['/', true])]
    #[TestWith(['/path/to/resource', true])]
    #[TestWith(['/path/to/resource%20name', true])]
    #[TestWith(['/users/~username', true])]
    #[TestWith(['/path/to/resource-123', true])]
    #[TestWith(['/2024/10/25/news', true])]
    #[TestWith(['/path/to/resource name', false])]
    #[TestWith(['/path/to/resource<>', false])]
    #[TestWith(['/path/to/resource#name', false])]
    public function testValidatePath(string $path, bool $expected): void
    {
        $this->assertSame(
            $expected,
            (UriValidator::getDefault())->validatePath($path)
        );
    }

    #[TestWith(['', true])]
    #[TestWith(['name=John', true])]
    #[TestWith(['name=John&age=30', true])]
    #[TestWith(['search=Hello%20World', true])]
    #[TestWith(['title=Top%20%26%20Best%20Movies', true])]
    #[TestWith(['item=12345', true])]
    #[TestWith(['active=true', true])]
    #[TestWith(['=value', true])]
    #[TestWith(['colors%5B%5D%3Dred%26colors%5B%5D%3Dblue', true])]
    #[TestWith(['colors[]=red&colors[]=blue', false])]
    #[TestWith(['search=Hello World', false])]
    #[TestWith(['name=John&age=30#', false])]
    #[TestWith(['title=Top & Best Movies', false])]
    public function testValidateQuery(string $query, bool $expected): void
    {
        $this->assertSame(
            $expected,
            (UriValidator::getDefault())->validateQuery($query)
        );
    }

    #[TestWith(['about', true])]
    #[TestWith(['section1', true])]
    #[TestWith(['my-page', true])]
    #[TestWith(['section_1', true])]
    #[TestWith(['section%20two', true])]
    #[TestWith(['fragment_with/forward-slash', true])]
    #[TestWith(['123', true])]
    #[TestWith(['section!', true])]
    #[TestWith(['section@', true])]
    #[TestWith(['page:section', true])]
    #[TestWith(['my site', false])]
    #[TestWith(['fragment#extra', false])]
    public function testValidateFragment(string $query, bool $expected): void
    {
        $this->assertSame(
            $expected,
            (UriValidator::getDefault())->validateQuery($query)
        );
    }
}
