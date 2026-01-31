# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-01-31

### Added
- Complete PSR-7 HTTP message implementation
- Full URI normalization, validation, and building
- Automatic body parsing based on Content-Type headers
- Secure file upload handling with path traversal protection
- XXE attack prevention for XML/HTML parsing
- Immutable message objects throughout
- Comprehensive test suite (227 tests, 307 assertions)
- Static analysis with PHPStan level 8
- Code style enforcement with PSR-12

### Features
- `ServerRequest::fromGlobals()` - Easy instantiation from PHP superglobals
- `Response::byContentType()` - Automatic content serialization
- Smart URI handling with IDN support
- Specialized exception types for better error handling
- Zero dependencies (only requires `psr/http-message`)

### Security
- XXE protection in XML/HTML parsing
- Path traversal protection in file uploads
- Header injection prevention
- Input validation and sanitization

## [Unreleased]

### Added
- PHPStan static analysis tool for improved code quality
- PHP CS Fixer for consistent code style enforcement
- GitHub Actions workflow improvements with PHP version matrix testing
- CHANGELOG.md for tracking project changes
- CONTRIBUTING.md with detailed contribution guidelines
- .editorconfig for consistent code formatting across editors

### Changed
- Updated GitHub Actions cache action from v3 to v4
- Improved CI/CD workflow with separate test and coverage jobs
- Optimized Xdebug installation to only run when needed for coverage

[Unreleased]: https://github.com/pedroac/lean-http/compare/v0.1.0...HEAD
