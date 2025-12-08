---
description: Update translations - add/edit .po files and compile .mo files
---

# Update Translations Workflow

When translation files need to be updated, follow these steps:

## 1. Regenerate POT file (if source strings changed)
// turbo
```bash
docker exec -w /var/www/html/wp-content/plugins/mail-system-by-katsarov-design php wp i18n make-pot . languages/mail-system-by-katsarov-design.pot --allow-root
```

## 2. Edit .po translation files

Update the following files with new translations:
- `languages/mail-system-by-katsarov-design-bg_BG.po` (Bulgarian)
- `languages/mail-system-by-katsarov-design-de_DE.po` (German)

Each .po file entry format:
```
msgid "English text"
msgstr "Translated text"
```

## 3. Compile .mo files (REQUIRED after any .po changes)
// turbo
```bash
docker exec -w /var/www/html/wp-content/plugins/mail-system-by-katsarov-design php composer translations
```

This runs `wp i18n make-mo languages/` to compile all .po files to .mo files.

## 4. Verify compilation
// turbo
```bash
file languages/*.mo
```

Should show `GNU message catalog` for each .mo file.

## 5. Commit all changes

Always commit both .po and .mo files together:
```bash
git add languages/*.po languages/*.mo
git commit -m "feat: Update translations"
```

> [!IMPORTANT]
> The .mo files MUST be recompiled after any .po file changes.
> WordPress reads .mo files, not .po files.
