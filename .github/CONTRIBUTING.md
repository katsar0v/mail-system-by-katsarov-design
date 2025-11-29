# Contributing to Mail System by Katsarov Design

Thank you for your interest in contributing to Mail System by Katsarov Design! This document outlines the workflow and guidelines for contributing to this project.

## 🔒 Branch Protection Rules

### Main Branch is Protected

**The `main` branch is protected and cannot be pushed to directly.** All changes must go through the pull request process.

- ❌ Direct pushes to `main` are **not allowed**
- ✅ All changes must be submitted via pull request
- ✅ Pull requests require review before merging

### Why?

This ensures:
- Code quality through peer review
- All changes are tested before merging
- Clean, traceable commit history
- Prevention of accidental breaking changes

## 📋 Issue-Based Workflow

### Each Issue Requires Its Own Pull Request

**Every issue must have its own dedicated branch and pull request.**

- ❌ Do not combine multiple unrelated issues in one PR
- ✅ Create a separate branch for each issue
- ✅ Reference the issue number in your PR

### Workflow Steps

1. **Find or Create an Issue**
   - Check if an issue already exists for your intended change
   - If not, create a new issue describing the bug, feature, or improvement
   - Wait for discussion/approval if it's a significant change

2. **Create a Feature Branch**
   ```bash
   # For a new feature
   git checkout -b feature/issue-123-short-description
   
   # For a bug fix
   git checkout -b fix/issue-456-short-description
   
   # For documentation
   git checkout -b docs/issue-789-short-description
   ```

3. **Make Your Changes**
   - Follow the WordPress Coding Standards (see below)
   - Write or update tests as needed
   - Update documentation if applicable
   - Keep commits focused and atomic

4. **Test Your Changes**
   ```bash
   # Run tests inside Docker container
   docker exec -it <php-container> bash -c "cd /var/www/html/wp-content/plugins/mail-system-by-katsarov-design && composer test"
   
   # Check coding standards
   docker exec -it <php-container> bash -c "cd /var/www/html/wp-content/plugins/mail-system-by-katsarov-design && composer phpcs"
   
   # Auto-fix what's possible
   docker exec -it <php-container> bash -c "cd /var/www/html/wp-content/plugins/mail-system-by-katsarov-design && composer phpcbf"
   ```

5. **Commit Your Changes**
   ```bash
   git add .
   git commit -m "Fix #123: Brief description of the change"
   ```
   
   Use conventional commit prefixes:
   - `feat:` for new features
   - `fix:` for bug fixes
   - `docs:` for documentation
   - `test:` for test updates
   - `refactor:` for code refactoring
   - `style:` for formatting changes

6. **Push to Your Fork**
   ```bash
   git push origin feature/issue-123-short-description
   ```

7. **Open a Pull Request**
   - Go to the repository on GitHub
   - Click "New Pull Request"
   - Select your branch
   - Fill in the PR template:
     - **Title**: Clear, descriptive title
     - **Description**: What changes were made and why
     - **Related Issue**: Link to the issue (e.g., "Closes #123")
     - **Testing**: How you tested the changes
     - **Screenshots**: If applicable

8. **Respond to Review Feedback**
   - Address all review comments
   - Push additional commits to the same branch
   - Request re-review when ready

9. **Merge**
   - Once approved, a maintainer will merge your PR
   - Your branch will be deleted after merging

## 📏 Coding Standards

### WordPress Coding Standards (WPCS)

**All PHP code MUST follow WordPress Coding Standards.**

Before submitting a PR:

1. **Check for violations**: `composer phpcs`
2. **Auto-fix what's possible**: `composer phpcbf`
3. **Manually fix remaining issues**

### Key Rules

| Rule | Example |
|------|---------|
| **Yoda conditions** | `if ( 'value' === $var )` NOT `if ( $var === 'value' )` |
| **wp_unslash before sanitize** | `sanitize_text_field( wp_unslash( $_POST['field'] ) )` |
| **Pre-increment** | `++$counter;` NOT `$counter++;` |
| **Nonce verification** | Always check `isset()` before `wp_verify_nonce()` |
| **Security first** | Always sanitize input, escape output, verify nonces |

### Translation Updates

If you add or modify user-facing strings:

1. Update all `.po` files in `languages/`:
   - `mail-system-by-katsarov-design.pot` (template)
   - `mail-system-by-katsarov-design-bg_BG.po` (Bulgarian)
   - `mail-system-by-katsarov-design-de_DE.po` (German)

2. Compile translations:
   ```bash
   docker exec -it <php-container> bash -c "cd /var/www/html/wp-content/plugins/mail-system-by-katsarov-design && composer translations"
   ```

### SCSS Guidelines

Follow the patterns in `docs/scss-guidelines.md`:
- Use the 7-1 pattern with `@use`/`@forward`
- Follow the existing variable naming conventions
- Keep component styles modular

## 🧪 Testing Requirements

### All PRs Must Include Tests

- **Unit tests** for new functionality
- **Integration tests** for complex workflows
- Tests should run inside Docker container
- All tests must pass before merge

### Running Tests

```bash
# All tests
composer test

# Specific test file
./vendor/bin/phpunit tests/Unit/SubscriberTest.php

# With coverage
composer test:coverage
```

## 📝 Documentation

### Update Documentation When Needed

- **Code comments**: PHPDoc blocks for classes and methods
- **README.md**: For user-facing features
- **docs/**: For technical documentation
- **CHANGELOG.md**: Add entry for your change

## 🚫 What Not to Do

- ❌ Push directly to `main` branch
- ❌ Combine multiple issues in one PR
- ❌ Submit PRs without tests
- ❌ Ignore coding standards violations
- ❌ Make breaking changes without discussion
- ❌ Add dependencies without approval
- ❌ Commit `vendor/` directory changes (unless updating dependencies)

## ✅ Pull Request Checklist

Before submitting your PR, ensure:

- [ ] Code follows WordPress Coding Standards (`composer phpcs` passes)
- [ ] All tests pass (`composer test` passes)
- [ ] New functionality includes tests
- [ ] Documentation is updated if needed
- [ ] Translation files are updated if needed (`.po` and `.mo` files)
- [ ] Commit messages are clear and descriptive
- [ ] PR references the related issue (e.g., "Closes #123")
- [ ] No unrelated changes are included
- [ ] Branch is up to date with `main`

## 🆘 Getting Help

- **Issues**: Use GitHub Issues for bugs and feature requests
- **Discussions**: Use GitHub Discussions for questions and ideas
- **Documentation**: Check `docs/` folder for technical details

## 📜 License

By contributing, you agree that your contributions will be licensed under the same license as the project (see LICENSE file).

---

Thank you for contributing! 🎉
