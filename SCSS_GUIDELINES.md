# SCSS Guidelines

This document outlines the SCSS architecture and coding standards for the Mail System by Katsarov Design plugin.

## 📁 Folder Structure

```
admin/scss/
├── main.scss                    # Main entry point
├── abstracts/                   # Variables, mixins, functions
│   ├── _index.scss              # Index file for abstracts
│   ├── _variables.scss          # All SCSS variables
│   └── _mixins.scss             # Reusable mixins
├── base/                        # Base/reset styles
│   ├── _index.scss              # Index file for base
│   └── _base.scss               # General base styles
└── components/                  # UI components
    ├── _index.scss              # Index file for components
    ├── _dashboard.scss          # Dashboard page styles
    ├── _status.scss             # Status badges
    ├── _forms.scss              # Form wrappers
    ├── _queue.scss              # Queue section
    └── _utilities.scss          # Utility classes
```

## 🎨 Color Palette

### Brand Colors
| Variable | Value | Usage |
|----------|-------|-------|
| `$color-primary` | `#2271b1` | Primary actions, links |
| `$color-primary-hover` | `#135e96` | Hover states |
| `$color-primary-light` | `#cce5ff` | Light backgrounds |

### Status Colors
| Status | Background | Text |
|--------|------------|------|
| Success | `$color-success-bg` (#d4edda) | `$color-success-text` (#155724) |
| Warning | `$color-warning-bg` (#fff3cd) | `$color-warning-text` (#856404) |
| Error | `$color-error-bg` (#f8d7da) | `$color-error-text` (#721c24) |
| Info | `$color-info-bg` (#cce5ff) | `$color-info-text` (#004085) |

## 📐 Spacing System

We use a 4px grid system for consistent spacing:

| Variable | Value | Usage |
|----------|-------|-------|
| `$spacing-1` | 4px | Minimal spacing |
| `$spacing-2` | 8px | Tight spacing |
| `$spacing-3` | 12px | Small spacing |
| `$spacing-4` | 16px | Default spacing |
| `$spacing-5` | 20px | Medium spacing |
| `$spacing-6` | 24px | Large spacing |
| `$spacing-8` | 32px | Extra large spacing |

## 🔤 Typography

### Font Sizes
| Variable | Value | Usage |
|----------|-------|-------|
| `$font-size-sm` | 12px | Small text, badges |
| `$font-size-base` | 13px | Body text |
| `$font-size-lg` | 16px | Headings |
| `$font-size-4xl` | 42px | Large numbers |

### Font Weights
- `$font-weight-normal`: 400
- `$font-weight-medium`: 500
- `$font-weight-semibold`: 600
- `$font-weight-bold`: 700

## 🧩 Mixins

### Responsive Breakpoints
```scss
// Mobile-first approach
@include mobile {
  // Styles for screens <= 782px
}

@include tablet {
  // Styles for screens >= 768px
}

@include desktop {
  // Styles for screens >= 992px
}

// Custom breakpoints
@include respond-below(600px) {
  // Custom breakpoint
}
```

### Cards
```scss
// Basic card
@include card;

// Card with padding
@include card-padded;
```

### Status Badges
```scss
// Apply status badge variant
@include status-badge-variant($background, $text-color);
```

### Transitions
```scss
// Standard transition
@include transition;

// Hover shadow effect
@include hover-shadow;
```

## 📝 Naming Conventions

### BEM-like Naming
We use a simplified BEM approach:

```scss
.mskd-block {}           // Block
.mskd-block__element {}  // Element (optional)
.mskd-block-modifier {}  // Modifier
```

### Prefix
All classes use the `mskd-` prefix to avoid conflicts with WordPress and other plugins.

## 🛠️ Build Commands

```bash
# Install dependencies
npm install

# Watch for changes (development)
npm run sass:dev

# Build for production
npm run sass:build

# Watch all (admin + public)
npm run watch

# Build all
npm run build
```

## ✅ Best Practices

### 1. Use Variables
Always use variables for colors, spacing, and typography:

```scss
// ✅ Good
color: $color-primary;
padding: $spacing-4;

// ❌ Bad
color: #2271b1;
padding: 16px;
```

### 2. Use Mixins
Use mixins for repeated patterns:

```scss
// ✅ Good
.my-card {
  @include card-padded;
}

// ❌ Bad
.my-card {
  background: #fff;
  border: 1px solid #c3c4c7;
  // ... repeated styles
}
```

### 3. Mobile-First Responsive Design
Write mobile styles first, then add larger breakpoint styles:

```scss
.my-element {
  // Mobile styles (default)
  padding: $spacing-2;
  
  @include tablet {
    // Tablet and up
    padding: $spacing-4;
  }
}
```

### 4. Keep Specificity Low
Avoid deep nesting (max 3 levels):

```scss
// ✅ Good
.mskd-stat-box {
  .button {
    margin-top: auto;
  }
}

// ❌ Bad
.mskd-wrap .mskd-dashboard .mskd-stats-grid .mskd-stat-box .button {
  margin-top: auto;
}
```

### 5. Comment Sections
Use section comments for organization:

```scss
// ==========================================================================
// Section Title
// ==========================================================================
```

## 🆕 Adding New Components

1. Create a new partial in `components/`:
   ```
   admin/scss/components/_my-component.scss
   ```

2. Add the `@use` statement at the top:
   ```scss
   @use '../abstracts' as *;
   ```

3. Add to `components/_index.scss`:
   ```scss
   @forward 'my-component';
   ```

4. Run the build to compile.

## 🔗 Resources

- [Sass Documentation](https://sass-lang.com/documentation)
- [WordPress CSS Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/)
