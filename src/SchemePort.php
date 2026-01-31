<?php

declare(strict_types=1);

namespace Pac\LeanHttp;

/**
 * Enum representing common scheme ports.
 * This enum provides a mapping between URI schemes and their default port numbers.
 * It can be used to retrieve the port number for a given scheme or to check if a scheme has a defined port.
 */
enum SchemePort: int
{
    case HTTP = 80;
    case HTTPS = 443;
    case FTP = 21;
    case SFTP = 22;
    case FTPS = 990;
    case GOPHER = 70;
    case NEWS = 119;
    case TELNET = 23;
    case IMAP = 143;
    case POP = 110;
    case LDAP = 389;
    case SMTP = 25;
    case SMTPS = 465;
    case SUBMISSION = 587;
    case RSYNC = 873;
    case IRC = 6667;
    case IRCS = 6697;
    case MYSQL = 3306;
    case PGSQL = 5432;
    case RDP = 3389;

    /**
     * Get the default port for the scheme.
     * This method returns the default port number associated with the scheme.
     * If the scheme does not have a defined port, it returns null.
     *
     * @return static|null
     * The SchemePort instance or null if not defined.
     */
    public static function fromScheme(string $scheme): ?static
    {
        return match(strtolower($scheme)) {
            'http' => self::HTTP,
            'https' => self::HTTPS,
            'ftp' => self::FTP,
            'sftp' => self::SFTP,
            'ftps' => self::FTPS,
            'ssh' => self::SFTP,
            'gopher' => self::GOPHER,
            'nntp' => self::NEWS,
            'news' => self::NEWS,
            'telnet' => self::TELNET,
            'tn3270' => self::TELNET,
            'imap' => self::IMAP,
            'pop' => self::POP,
            'ldap' => self::LDAP,
            'smtp' => self::SMTP,
            'smtps' => self::SMTPS,
            'submission' => self::SUBMISSION,
            'rsync' => self::RSYNC,
            'irc' => self::IRC,
            'ircs' => self::IRCS,
            'mysql' => self::MYSQL,
            'pgsql' => self::PGSQL,
            'rdp' => self::RDP,
            default => null,
        };
    }
}
