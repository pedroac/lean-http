# Contributing to lean-http

Thank you for your interest in contributing to lean-http! This document provides guidelines and instructions for contributing to the project.

## Getting Started

1. **Fork the repository** on GitHub
2. **Clone your fork** locally:
   ```bash
   git clone https://github.com/your-username/lean-http.git
   cd lean-http
   ```
3. **Install dependencies**:
   ```bash
   composer install
   ```
4. **Setup git hooks** (optional but recommended):
   ```bash
   chmod +x scripts/setup-git-hooks.sh
   ./scripts/setup-git-hooks.sh
   ```
   
   This will enable a pre-commit hook that automatically runs code style checks, static analysis, and tests before allowing commits. This helps catch issues early and keeps the codebase clean.

## Development Workflow

### Making Changes

1. Create a new branch for your changes:
   ```bash
   git checkout -b feature/your-feature-name
   ```
   or
   ```bash
   git checkout -b fix/your-bug-fix
   ```

2. Make your changes following the coding standards (see below)

3. Run the quality checks before committing:
   ```bash
   # Run tests
   composer test
   
   # Check code style
   composer cs-check
   
   # Run static analysis
   composer phpstan
   ```

4. Fix any issues found by the tools:
   ```bash
   # Auto-fix code style issues
   composer cs-fix
   ```

5. Commit your changes with a clear, descriptive commit message

6. Push to your fork and create a pull request

## Coding Standards

### Code Style

This project follows **PSR-12** coding standards. We use PHP CS Fixer to enforce code style consistency.

- Run `composer cs-check` to check for style issues
- Run `composer cs-fix` to automatically fix style issues

### Static Analysis

We use PHPStan for static analysis to catch potential bugs and type issues.

- Run `composer phpstan` to check for issues
- PHPStan is configured at level 8 (maximum strictness)

### Testing

- All new features should include tests
- All bug fixes should include tests that demonstrate the fix
- Run `composer test` to execute the test suite
- Ensure all tests pass before submitting a pull request

### Documentation

- Update the README.md if you add new features or change existing behavior
- Add PHPDoc comments for new classes, methods, and properties
- Update CHANGELOG.md with your changes (see below)

## Pull Request Process

1. **Fork and PR:** Fork the repository and create a pull request for any changes
2. **Include tests:** All changes should include relevant tests
3. **Update documentation:** Update README.md and other documentation as needed
4. **Update CHANGELOG:** Add an entry to CHANGELOG.md describing your changes
5. **Ensure quality checks pass:** All tests, code style checks, and static analysis must pass

### Pull Request Checklist

- [ ] Code follows PSR-12 coding standards
- [ ] All tests pass (`composer test`)
- [ ] Code style is correct (`composer cs-check`)
- [ ] Static analysis passes (`composer phpstan`)
- [ ] Documentation is updated (README.md, PHPDoc)
- [ ] CHANGELOG.md is updated
- [ ] Commit messages are clear and descriptive

## Issue Tracking

Use GitHub issues to:
- Report bugs
- Suggest new features
- Ask questions
- Discuss improvements

When reporting bugs, please include:
- PHP version
- Steps to reproduce
- Expected behavior
- Actual behavior
- Any relevant error messages or stack traces

## Using AI Tools

Using AI tools like GitHub Copilot, ChatGPT, or similar tools is **encouraged** to:
- Find issues faster
- Ensure specifications are followed
- Improve code quality
- Generate test cases

However, **always apply critical thinking** and carefully review AI-generated suggestions. AI tools are assistants, not replacements for human judgment.

## Code Review

All pull requests will be reviewed. Reviewers may:
- Request changes
- Ask questions
- Suggest improvements
- Approve the changes

Please be patient and responsive to feedback. The goal is to maintain high code quality and consistency.

## Questions?

If you have questions about contributing, feel free to:
- Open an issue on GitHub
- Check existing issues and discussions
- Review the README.md for project information

Thank you for contributing to lean-http! 🎉
