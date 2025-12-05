# Testing Guide: Bcc Support in Campaign Creation

## Overview
This document describes how to test the new Bcc (Blind Carbon Copy) functionality added to the campaign creation flow.

## Changes Made

### 1. Database Schema
- Added `bcc` column to `mskd_campaigns` table (TEXT, nullable)
- Database version updated to 1.5.0 with migration

### 2. UI Changes
- Added Bcc input field in Step 3 of the campaign wizard (admin/partials/compose-wizard.php)
- Field appears between "Send to lists" and "Scheduling" sections
- Placeholder text: "email1@example.com, email2@example.com"
- Help text explains Bcc functionality

### 3. Backend Changes
- **Email_Service**: Updated `queue_campaign()` and `queue_one_time()` to accept and store Bcc
- **Admin_Email**: Added validation for Bcc email addresses (comma-separated)
- **Cron_Handler**: Modified to fetch Bcc from campaigns table and pass to mailer
- **SMTP_Mailer**: Updated `process_headers()` to use PHPMailer's `addBCC()` method

### 4. Translations
- Added translations in Bulgarian (bg_BG), German (de_DE), and POT template
- All user-facing strings are translatable

## Manual Testing Steps

### Test 1: Basic Bcc Functionality
1. Log in to WordPress admin
2. Navigate to Mail System > New Campaign
3. Complete Steps 1 and 2 (select template, add content)
4. In Step 3, enter test email addresses in the Bcc field (e.g., "test1@example.com, test2@example.com")
5. Select recipients and send the campaign
6. **Expected**: Campaign is created, Bcc emails receive the campaign without being visible to main recipients

### Test 2: Empty Bcc (Optional Field)
1. Create a new campaign
2. Leave the Bcc field empty
3. Complete and send the campaign
4. **Expected**: Campaign sends normally without errors

### Test 3: Invalid Bcc Email Validation
1. Create a new campaign
2. Enter invalid email in Bcc field (e.g., "not-an-email")
3. Try to send
4. **Expected**: Error message "Invalid Bcc email address: not-an-email" appears
5. Correct the email and send successfully

### Test 4: Multiple Bcc Recipients
1. Create a new campaign
2. Enter multiple valid emails separated by commas: "bcc1@test.com, bcc2@test.com, bcc3@test.com"
3. Send campaign
4. **Expected**: All three Bcc addresses receive the email

### Test 5: Database Verification
1. After creating a campaign with Bcc, check the database:
   ```sql
   SELECT id, subject, bcc FROM wp_mskd_campaigns ORDER BY id DESC LIMIT 1;
   ```
2. **Expected**: The `bcc` column contains the email addresses

### Test 6: Queue Processing
1. Create a scheduled campaign with Bcc
2. Wait for cron to process the queue (or trigger manually)
3. Check that emails are sent with Bcc headers
4. **Expected**: Bcc recipients receive emails, main recipients don't see Bcc addresses

### Test 7: Translation Check (Optional)
1. Change WordPress language to Bulgarian or German
2. Create a new campaign
3. Navigate to Step 3
4. **Expected**: Bcc field label and help text appear in the selected language

## Database Migration Testing

### Fresh Install
1. Install plugin on a fresh WordPress installation
2. Check `wp_mskd_campaigns` table structure
3. **Expected**: `bcc` column exists in the table

### Upgrade from Previous Version
1. Install previous version (1.4.0 or earlier)
2. Upgrade to new version (1.5.0)
3. Check `wp_mskd_campaigns` table
4. **Expected**: `bcc` column is added by migration script

## Code Quality

### PHPCS Compliance
All modified files pass WordPress Coding Standards:
```bash
./vendor/bin/phpcs --standard=WordPress includes/Admin/class-admin-email.php
./vendor/bin/phpcs --standard=WordPress includes/Services/class-email-service.php
./vendor/bin/phpcs --standard=WordPress includes/class-activator.php
```

### PHP Syntax Check
All files pass PHP syntax validation:
```bash
php -l includes/class-activator.php
php -l includes/Services/class-email-service.php
php -l includes/Admin/class-admin-email.php
php -l includes/services/class-cron-handler.php
php -l includes/services/class-smtp-mailer.php
```

## Edge Cases to Test

1. **Very long Bcc list**: Try adding 50+ email addresses
2. **Whitespace handling**: Test "email1@test.com , email2@test.com" (spaces around commas)
3. **Mixed valid/invalid**: Test "valid@test.com, invalid-email, another@test.com"
4. **Special characters in emails**: Test internationalized email addresses if supported
5. **Performance**: Verify that adding Bcc doesn't significantly slow down campaign processing

## Security Considerations

- ✅ Bcc field is sanitized using `sanitize_text_field()`
- ✅ Email validation uses WordPress's `is_email()` function
- ✅ Nonce verification is in place for form submissions
- ✅ Only admin users can access campaign creation
- ✅ Bcc data is properly escaped when displayed

## Rollback Plan

If issues are discovered:
1. Revert to previous version
2. Database will retain `bcc` column (safe to leave, or can be removed manually)
3. No data loss - campaigns without Bcc continue to work normally

## Notes

- Bcc field is optional and doesn't affect existing campaigns
- Empty Bcc values are stored as empty strings in the database
- PHPMailer handles Bcc properly, ensuring recipients remain hidden
- The feature works with both immediate and scheduled campaigns
- Bcc applies to all recipients in a campaign (not per-recipient)
