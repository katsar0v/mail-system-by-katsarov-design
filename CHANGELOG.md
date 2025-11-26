# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned
- CSV subscriber import
- Open and click statistics
- Email templates
- A/B testing
- Integration with popular SMTP plugins

---

## [1.1.0] - 2024-XX-XX

### Added
- **Multi-language Support**
  - English as the default/primary language
  - Bulgarian (bg_BG) translation
  - German (de_DE) translation
  - Automatic language detection based on WordPress locale
  - Documentation for adding new translations

### Changed
- Source strings changed from Bulgarian to English
- Updated POT file with all English source strings
- Updated README with internationalization documentation

---

## [1.0.0] - 2024-XX-XX

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
