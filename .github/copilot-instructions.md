# Copilot Instructions for Mail System by Katsarov Design

WordPress plugin for email newsletter management with subscribers, lists, and queue processing. Full Bulgarian UI.

## Architecture Overview

```
mail-system-by-katsarov-design.php  → Entry point, constants (MSKD_*), autoloader
├── admin/class-admin.php           → All admin logic (1000+ lines, handles CRUD, AJAX, forms)
├── public/class-public.php         → Public shortcodes, AJAX subscription, unsubscribe handling
├── includes/
│   ├── class-activator.php         → DB table creation, cron scheduling, default options
│   ├── class-deactivator.php       → Cron cleanup
│   └── services/
│       ├── class-cron-handler.php  → Queue processing (10 emails/min via MSKD_BATCH_SIZE)
│       └── class-smtp-mailer.php   → PHPMailer wrapper for SMTP sending
```

**Database Tables** (all prefixed `mskd_`):
- `mskd_subscribers` - email, first/last name, status (active/inactive/unsubscribed), unsubscribe_token
- `mskd_lists` - mailing list definitions
- `mskd_subscriber_list` - many-to-many pivot table
- `mskd_queue` - email queue with status (pending/processing/sent/failed), retry tracking

## Naming Conventions

| Type | Prefix | Example |
|------|--------|---------|
| Constants | `MSKD_` | `MSKD_BATCH_SIZE`, `MSKD_PLUGIN_DIR` |
| Classes | `MSKD_` | `MSKD_Admin`, `MSKD_Cron_Handler` |
| Functions | `mskd_` | `mskd_load_textdomain()` |
| DB tables | `mskd_` | `{$wpdb->prefix}mskd_subscribers` |
| Hooks/Actions | `mskd_` | `mskd_process_queue` |
| Admin pages | `mskd-` | `mskd-dashboard`, `mskd-subscribers` |

## Testing

Tests run **inside Docker** using Brain Monkey + Mockery (no WordPress installation needed):

```bash
# From host: enter PHP container and run tests
docker exec -it <php-container> bash -c "cd /var/www/html/wp-content/plugins/mail-system-by-katsarov-design && composer test"

# Or inside container at plugin dir:
composer test                    # All tests
./vendor/bin/phpunit tests/Unit/SubscriberTest.php  # Single file
```

**Test patterns** (see `tests/Unit/TestCase.php`):
- Extend `MSKD\Tests\Unit\TestCase` for base mocks
- Use `$this->setup_wpdb_mock()` to mock `$wpdb`
- Use `Functions\expect()` / `Functions\stubs()` for WP functions
- Clean `$_POST`, `$_GET` in `tearDown()`

## SCSS Structure

Follow `SCSS_GUIDELINES.md`. Uses 7-1 pattern with `@use`/`@forward`:

```scss
admin/scss/
├── main.scss              # Entry point (@use 'abstracts', 'base', 'components')
├── abstracts/_variables.scss  # $color-*, $spacing-*, $font-*
├── abstracts/_mixins.scss     # @mixin card, responsive breakpoints
├── base/_base.scss            # Reset, general styles
└── components/                # _dashboard.scss, _forms.scss, _queue.scss, etc.
```

**Key variables**: `$color-primary: #2271b1`, 4px grid spacing (`$spacing-1` to `$spacing-8`), status colors (`$color-success-bg`, etc.)

## Key Patterns

**Email placeholders**: `{first_name}`, `{last_name}`, `{email}`, `{unsubscribe_link}`, `{unsubscribe_url}`

**Admin partials**: `admin/partials/*.php` - PHP templates included by `MSKD_Admin` methods

**Security**: Always use `wp_verify_nonce()`, `current_user_can('manage_options')`, `sanitize_*()` functions

**Translations**: Text domain is `mail-system-by-katsarov-design`. All user-facing strings are in Bulgarian.

## Common Tasks

- **Add admin page**: Add `add_submenu_page()` in `MSKD_Admin::register_menu()`, create partial in `admin/partials/`
- **Add AJAX endpoint**: Add `add_action('wp_ajax_mskd_*')` in class `init()`, implement handler method
- **Modify queue processing**: Edit `MSKD_Cron_Handler::process_queue()`, constant `MSKD_BATCH_SIZE` controls batch size
- **Add subscriber field**: Update `class-activator.php` schema, `MSKD_Admin` CRUD methods, partials

## Composer Scripts

```bash
composer test        # Run PHPUnit tests
composer phpcs       # WordPress coding standards check
composer phpcbf      # Auto-fix coding standards
```
