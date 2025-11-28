# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned
- Open and click statistics
- A/B testing
- Integration with popular SMTP plugins

---

## [1.1.0] - 2025-11-28

### Added
- **Email Templates Management System**
  - Predefined templates (Blank, Newsletter, Welcome, Promotional)
  - Custom template creation and management
  - Template duplication functionality
  - Visual editor integration for templates
  - Template usage from compose forms

- **Import/Export Functionality**
  - Import subscribers from CSV/JSON files
  - Export subscribers to CSV/JSON formats
  - Import/export mailing lists
  - Bulk import with list assignment
  - Redesigned Import/Export UI

- **Batch Edit for Subscribers**
  - Bulk list assignment for multiple subscribers
  - Batch status changes
  - Improved subscriber management

- **Configurable Email Rate Limit**
  - New "Emails per minute" setting (1-1000)
  - Adjustable sending speed based on hosting limits
  - Setting accessible in Emails → Settings → Sending settings

- **Admin Shortcodes Page**
  - Visual form gallery with `[mskd_form_gallery]` shortcode
  - Read-only form preview functionality
  - Replaced public shortcode with admin-managed Shortcodes page

- **Queue Improvements**
  - Email content accordion in queue details page
  - Enhanced queue detail view
  - Better campaign tracking

- **Multi-language Support**
  - English as the default/primary language
  - Bulgarian (bg_BG) translation - fully translated
  - German (de_DE) translation - fully translated
  - Automatic language detection based on WordPress locale
  - Documentation for adding new translations

- **API & Developer Features**
  - Comprehensive API reference documentation
  - External lists hook (`mskd_register_external_lists`)
  - External subscribers hook (`mskd_register_external_subscribers`)
  - Template_Service class for programmatic template management

### Changed
- Refactored admin architecture into modular PSR-4 classes
- Replaced Select2 with SlimSelect for multi-select lists
- Updated autoloading to support both PSR-4 and legacy classes
- Source strings changed from Bulgarian to English
- Updated POT file with all English source strings
- Improved CSS structure with better SCSS organization

### Fixed
- Visual editor CSS code appearing in email body
- Visual editor content not transferring to step 3 in campaign wizard
- Timestamp normalization for scheduled tasks
- Various UI improvements and bug fixes

---

## [1.0.0] - 2025-01-15

### Added
- Initial plugin version
- **Subscribers**
  - Add, edit, and delete subscribers
  - Statuses: active, inactive, unsubscribed
  - Filter by status
  - Pagination
- **Lists**
  - Create mailing lists
  - Add subscribers to multiple lists
  - Subscriber count statistics per list
- **Sending Queue**
  - Automatic email queuing
  - WP-Cron integration for sending
  - Speed: 10 emails/minute (MSKD_BATCH_SIZE)
  - Statuses: pending, processing, sent, failed
  - Send attempt tracking
- **Email Composition**
  - WYSIWYG editor for content
  - List selection for sending
  - Placeholders: {first_name}, {last_name}, {email}, {unsubscribe_link}
- **Settings**
  - Sender name and email
  - Reply-to email
  - SMTP configuration
- **Public Features**
  - Subscription form shortcode: [mskd_subscribe_form]
  - AJAX subscription without page reload
  - Unsubscribe page with unique token
- **Internationalization**
  - Ready for translation (.pot file)
- **Administration**
  - Dashboard with general statistics
  - WP-Cron warning (shown only within the plugin)
  - System cron recommendation

### Technical Details
- Requires PHP 7.4+
- Requires WordPress 5.0+
- Uses SMTP for sending (configurable)
- 4 new database tables (mskd_subscribers, mskd_lists, mskd_subscriber_list, mskd_queue)
- Automatic table creation on activation
- Automatic cron job scheduling on activation
- Automatic cron job removal on deactivation

---

## Versioning

- **Major** (X.0.0) - Incompatible API changes
- **Minor** (0.X.0) - New features, backward compatible
- **Patch** (0.0.X) - Bug fixes, backward compatible
