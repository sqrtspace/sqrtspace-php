# Contributing to Ubiquity SpaceTime PHP

Thank you for your interest in contributing to Ubiquity SpaceTime PHP! This document provides guidelines and instructions for contributing.

## Code of Conduct

By participating in this project, you agree to abide by our code of conduct: be respectful, inclusive, and considerate of others.

## How to Contribute

### Reporting Issues

1. Check if the issue already exists in the [issue tracker](https://github.com/ubiquity/spacetime-php/issues)
2. If not, create a new issue with:
   - Clear title and description
   - Steps to reproduce (if applicable)
   - Expected vs actual behavior
   - PHP version and environment details

### Submitting Pull Requests

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Make your changes following our coding standards
4. Add/update tests as needed
5. Update documentation if applicable
6. Commit with descriptive messages
7. Push to your fork
8. Submit a pull request

## Development Setup

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/ubiquity-php.git
cd ubiquity-php

# Install dependencies
composer install

# Run tests
vendor/bin/phpunit

# Run tests with coverage
vendor/bin/phpunit --coverage-html coverage
```

## Coding Standards

### PHP Standards

- Follow PSR-12 coding style
- Use PHP 8.1+ features appropriately
- Add type declarations for all parameters and return types
- Use strict types (`declare(strict_types=1);`)

### Code Style

```php
<?php

declare(strict_types=1);

namespace Ubiquity\SpaceTime\Example;

use Ubiquity\SpaceTime\SomeClass;

/**
 * Class description
 */
class ExampleClass
{
    private string $property;
    
    public function __construct(string $property)
    {
        $this->property = $property;
    }
    
    /**
     * Method description
     * 
     * @throws \Exception When something goes wrong
     */
    public function doSomething(int $param): array
    {
        // Implementation
        return [];
    }
}
```

### Testing Guidelines

- Write tests for all new features
- Maintain or improve code coverage
- Use descriptive test method names
- Follow AAA pattern (Arrange, Act, Assert)

```php
public function testFeatureWorksCorrectly(): void
{
    // Arrange
    $instance = new TestedClass();
    
    // Act
    $result = $instance->doSomething();
    
    // Assert
    $this->assertEquals('expected', $result);
}
```

## Documentation

- Update README.md for new features
- Add PHPDoc blocks for all public methods
- Include usage examples for complex features
- Update CHANGELOG.md following [Keep a Changelog](https://keepachangelog.com/)

## Performance Considerations

Since SpaceTime focuses on memory efficiency:

1. Always consider memory usage in your implementations
2. Benchmark memory usage and performance for new features
3. Document any trade-offs between memory and speed
4. Follow the √n principle where applicable

## Pull Request Process

1. Ensure all tests pass
2. Update documentation
3. Add entry to CHANGELOG.md
4. Request review from maintainers
5. Address feedback promptly
6. Squash commits if requested

## Release Process

Releases are managed by maintainers following semantic versioning:

- MAJOR: Breaking changes
- MINOR: New features (backward compatible)
- PATCH: Bug fixes

## Questions?

Feel free to:
- Open an issue for questions
- Join our discussions
- Contact maintainers

Thank you for contributing!