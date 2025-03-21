# Contributing to Woo Secure Video Locker

Thank you for your interest in contributing to Woo Secure Video Locker! This document provides guidelines and steps for contributing to the project.

## Code of Conduct

By participating in this project, you agree to abide by our Code of Conduct. Please read it before contributing.

## How to Contribute

### Reporting Bugs

1. Check if the bug has already been reported in the [Issues](https://github.com/yourusername/woo-secure-video-locker/issues) section
2. If not, create a new issue with:
   - A clear title and description
   - Steps to reproduce the bug
   - Expected behavior
   - Actual behavior
   - Screenshots (if applicable)
   - Your WordPress, WooCommerce, and PHP versions

### Suggesting Enhancements

1. Check if the enhancement has been suggested in the [Issues](https://github.com/yourusername/woo-secure-video-locker/issues) section
2. If not, create a new issue with:
   - A clear title and description
   - Why this enhancement would be useful
   - How it could be implemented
   - Any examples or mockups

### Pull Requests

1. Fork the repository
2. Create a new branch for your feature/fix:
   ```bash
   git checkout -b feature/your-feature-name
   # or
   git checkout -b fix/your-fix-name
   ```
3. Make your changes
4. Test thoroughly
5. Commit your changes:
   ```bash
   git commit -m "Description of your changes"
   ```
6. Push to your fork:
   ```bash
   git push origin feature/your-feature-name
   ```
7. Create a Pull Request

### Development Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/woo-secure-video-locker.git
   cd woo-secure-video-locker
   ```

2. Set up a local WordPress development environment

3. Install dependencies:
   ```bash
   composer install
   ```

4. Create a test environment:
   - Create a test WordPress installation
   - Install and activate WooCommerce
   - Install and activate this plugin
   - Create test products with videos

### Coding Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- Use meaningful variable and function names
- Add comments for complex logic
- Keep functions focused and single-purpose
- Write unit tests for new features
- Update documentation as needed

### Testing

1. Test your changes in different environments:
   - Different WordPress versions
   - Different WooCommerce versions
   - Different PHP versions
   - Different browsers

2. Run existing tests:
   ```bash
   phpunit
   ```

3. Add new tests for your changes

### Documentation

1. Update relevant documentation:
   - README.md
   - Code comments
   - Inline documentation
   - User guides

2. Follow documentation style:
   - Clear and concise
   - Use proper markdown formatting
   - Include examples where appropriate
   - Keep it up to date

## Release Process

1. Update version numbers in:
   - Main plugin file
   - README.md
   - CHANGELOG.md

2. Create a release branch:
   ```bash
   git checkout -b release/vX.X.X
   ```

3. Test thoroughly

4. Create a release on GitHub:
   - Tag the release
   - Add release notes
   - Create a ZIP file

## Questions?

If you have any questions, please:
1. Check the [documentation](https://yourwebsite.com/woo-secure-video-locker-docs)
2. Open an issue
3. Contact the maintainers

Thank you for contributing to Woo Secure Video Locker! 