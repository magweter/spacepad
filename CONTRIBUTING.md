# Contributing to Spacepad

Thank you for your interest in contributing to Spacepad! This document provides guidelines and instructions for contributing to our project.

## How to Contribute

### Reporting Bugs

- Check if the bug has already been reported in the [Issues](https://github.com/magweter/spacepad/issues) section
- If not, create a new issue with a clear title and description
- Include as much relevant information as possible (steps to reproduce, expected behavior, actual behavior, screenshots, etc.)
- Use the bug report template if available

### Suggesting Enhancements

- Check if the enhancement has already been suggested in the [Issues](https://github.com/magweter/spacepad/issues) section
- If not, create a new issue with a clear title and description
- Explain why this enhancement would be useful to most users
- Use the feature request template if available

### Pull Requests

1. Fork the repository
2. Create a new branch for your feature or bugfix (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Run tests to ensure your changes don't break existing functionality
5. Commit your changes (`git commit -m 'Add some amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

### Development Setup

1. Clone your fork of the repository
2. Install dependencies:
   ```bash
   composer install
   pnpm install
   ```
3. Set up your environment:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
4. Start the development server:
   ```bash
   docker-compose up -d
   ```

### Coding Standards

- Follow the [PSR-12](https://www.php-fig.org/psr/psr-12/) coding style guide for PHP
- Always use import statements instead of inline fully qualified class names - See [Coding Standards](backend/docs/CODING_STANDARDS.md) for details
- Use ESLint and Prettier for JavaScript/TypeScript
- Write meaningful commit messages
- Add comments for complex logic
- Update documentation as needed

### Testing

- Write tests for new features
- Ensure all tests pass before submitting a pull request
- Run the test suite:
  ```bash
  php artisan test
  ```

## Documentation

- Update the README.md if needed
- Add inline documentation for complex functions
- Update API documentation if you change endpoints
- Add examples for new features

## Release Process

1. Update version numbers in relevant files
2. Update the CHANGELOG.md
3. Create a new release on GitHub
4. Tag the release with the version number

## Questions?

If you have any questions, please open an issue or contact the maintainers.

Thank you for contributing to Spacepad! 