# Email Header and Footer Configuration

The Mail System by Katsarov Design plugin supports custom email headers and footers that are automatically added to all outgoing emails.

## Overview

You can configure custom HTML content that will be:
- **Header**: Prepended (added before) the main email content
- **Footer**: Appended (added after) the main email content

This feature applies to all email types:
- **Campaign emails** (newsletters sent to lists)
- **One-time emails** (individual emails sent immediately or scheduled)
- **Scheduled emails** (processed by the queue)

This feature is useful for:
- Adding consistent branding elements (logos, company info)
- Including legal disclaimers or privacy notices
- Adding unsubscribe links to comply with anti-spam regulations
- Creating a consistent email template for all communications

## Configuration

### Accessing the Settings

1. Navigate to **Mail System** → **Settings** in your WordPress admin menu
2. Scroll down to the **Email Template Settings** section

### Setting Up Header and Footer

Both the header and footer fields accept raw HTML content. You can include any valid HTML that works in emails, including:

- Text and headings
- Images (use absolute URLs)
- Links
- Tables for layout
- Inline CSS styles

**Example Header:**
```html
<div style="text-align: center; padding: 20px; background-color: #f4f4f4;">
    <img src="https://yoursite.com/logo.png" alt="Company Logo" style="max-width: 200px;">
</div>
```

**Example Footer:**
```html
<div style="text-align: center; padding: 20px; font-size: 12px; color: #666;">
    <p>© 2024 Your Company Name. All rights reserved.</p>
    <p>You received this email because you subscribed to our newsletter.</p>
    <p>{unsubscribe_link}</p>
</div>
```

## Template Variables

Both header and footer support the same template variables as the main email content. These placeholders are replaced with actual subscriber data when the email is sent:

| Variable | Description | Example Output |
|----------|-------------|----------------|
| `{first_name}` | Subscriber's first name | John |
| `{last_name}` | Subscriber's last name | Smith |
| `{email}` | Subscriber's email address | john.smith@example.com |
| `{unsubscribe_link}` | Clickable unsubscribe link | `<a href="...">Unsubscribe</a>` |
| `{unsubscribe_url}` | Raw unsubscribe URL | https://yoursite.com/?mskd_unsubscribe=token123 |

### Variable Usage Examples

**Personalized Header:**
```html
<div style="padding: 20px;">
    <p>Hello, {first_name}! Here's your weekly newsletter.</p>
</div>
```

**Footer with Unsubscribe Link:**
```html
<div style="text-align: center; padding: 20px;">
    <p>This email was sent to {email}.</p>
    <p>Don't want to receive these emails? {unsubscribe_link}</p>
</div>
```

## Important Notes

1. **Email Client Compatibility**: Not all email clients support the same CSS. Use inline styles and keep your HTML simple for best compatibility.

2. **Leave Empty to Disable**: If you don't want a header or footer, simply leave the field empty.

3. **Test Your Emails**: Always send a test email after configuring headers and footers to ensure they display correctly.

4. **HTML Sanitization**: The header and footer content is sanitized to allow only safe HTML elements appropriate for email templates.

5. **Placeholder Replacement Order**: Headers and footers are applied first, then all placeholders (including those in the header/footer) are replaced with subscriber data.

## Best Practices

1. **Keep It Simple**: Use simple, table-based layouts for maximum email client compatibility
2. **Use Absolute URLs**: Always use full URLs for images and links
3. **Include Unsubscribe Link**: To comply with anti-spam laws, always include `{unsubscribe_link}` or `{unsubscribe_url}` in your footer
4. **Test Across Clients**: Test your emails in multiple email clients (Gmail, Outlook, Apple Mail, etc.)
5. **Mobile Responsiveness**: Keep content width under 600px for better mobile display

## Troubleshooting

### Header/Footer Not Showing
- Ensure you've saved the settings after making changes
- Check that the HTML is valid and not empty whitespace

### Placeholders Not Being Replaced
- Make sure you're using the exact placeholder syntax: `{variable_name}`
- Placeholders are case-sensitive

### Styling Issues
- Use inline CSS styles instead of external stylesheets
- Avoid complex CSS selectors; email clients have limited CSS support
