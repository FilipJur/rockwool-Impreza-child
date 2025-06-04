# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

- `npm run build:css` - Compile SCSS to CSS with source maps
- `npm run watch:css` - Watch SCSS files for changes and auto-compile
- `npm run build:css:prod` - Production build (compressed CSS, no source maps)
- `npm run build:js` - Build JavaScript for production
- `npm run build:js:dev` - Build JavaScript for development
- `npm run watch:js` - Watch JavaScript files for changes and auto-compile
- `npm run build` - Build both CSS and JS for production
- `npm run dev` - Build both CSS and JS for development
- `npm run watch` - Watch both CSS and JS files

## Architecture Overview

This is an Impreza WordPress child theme with custom business logic and API integrations for a Czech e-commerce site.

### SCSS Architecture
Uses modern SCSS with `@use` modules. Main entry point is `src/scss/main.scss`. Never edit `style.css` directly - it's auto-generated.

- `src/scss/base/` - Variables, mixins, reset styles
- `src/scss/components/` - UI component styles  
- `src/scss/layout/` - Grid and layout systems
- `src/scss/utilities/` - Helper classes

### JavaScript Architecture
Uses modern ES6 modules with `@wordpress/scripts` build system. Main entry point is `src/js/main.js`. Never edit `build/js/*` directly - it's auto-generated.

- `src/js/utils/` - Shared utility modules (DOM, API, validation)
- `src/js/modules/` - Feature-specific modules (ARES, file upload)
- `build/js/` - Built/bundled JavaScript ready for WordPress
- Entry point initializes all modules and handles global events

### Key Integrations

**myCred Points System** (`functions.php:20-80`)
- WooCommerce integration for points-based purchasing
- Modifies "Add to Cart" buttons based on user point balance
- Custom affordability calculations

**LeadHub API** (`includes/leadhub.php`)
- OAuth-based lead forwarding from Contact Form 7
- Automatic submission processing for pre-registration campaigns

**ARES Business Registry** (`src/js/ares-handler.js`)
- Czech company data auto-fill using IČO (company registration numbers)
- Real-time API validation and address population

**Enhanced File Upload** (`src/js/file-upload-enhanced.js`)
- Drag-and-drop interface for Contact Form 7
- File preview and client-side validation

### Design System

Comprehensive design tokens in `docs/design/design-tokens.md`:
- Primary brand color: ROCKWOOL red (#DB0626)
- Typography: Avenir Next, General Sans
- Uses CSS custom properties for semantic naming

### Important Files

- `functions.php` - Main theme functions, myCred integration, asset enqueuing
- `impreza-theme-config.json` - Extensive Impreza theme configuration
- `src/scss/main.scss` - SCSS entry point with modular imports
- `src/js/` - JavaScript modules (no bundling, direct inclusion)

### Development Notes

- Always use `npm run watch:css` during development
- Test myCred point calculations carefully in staging
- ARES integration is Czech-specific and requires valid IČO numbers
- File upload features require Contact Form 7 plugin
- Theme inherits heavily from Impreza parent theme