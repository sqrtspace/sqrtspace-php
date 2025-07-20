# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2025-01-20

### Added
- Initial release of SpaceTime PHP library
- Core streaming functionality with `SpaceTimeStream` class
- Memory-efficient array implementation with `SpaceTimeArray`
- External sorting algorithm for datasets larger than memory
- External group-by algorithm for large-scale data aggregation
- Batch processing system with checkpoint support
- Memory pressure monitoring and automatic handling
- Laravel integration with service provider and Eloquent support
- Symfony bundle with console commands and DI configuration
- File processing utilities (CSV, JSON Lines)
- Comprehensive test suite
- Documentation and examples

### Features
- Process files larger than available memory
- Streaming operations: map, filter, flatMap, chunk, batch
- Automatic memory management with configurable thresholds
- Progress tracking and resumable operations
- Framework integrations for Laravel and Symfony
- Type-safe operations with PHP 8.1+ features

[Unreleased]: https://github.com/sqrtspace/spacetime-php/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/sqrtspace/spacetime-php/releases/tag/v1.0.0