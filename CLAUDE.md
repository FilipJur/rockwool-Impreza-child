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

- `src/js/utils/` - Shared utility modules (API, validation)
- `src/js/modules/` - Feature-specific modules (ARES, file upload)
- `build/js/` - Built/bundled JavaScript ready for WordPress
- Uses vanilla JS DOM APIs instead of jQuery for better performance
- Entry point initializes all modules and handles global events

### Key Integrations

**myCred Points System** (`src/includes/mycred/`)
- **Architecture**: Object-oriented singleton pattern with dependency injection
- **Entry Point**: `src/includes/mycred-integration.php` (loaded from functions.php)
- **Core Classes**:
  - `MyCred_Manager` - Main orchestrator and singleton instance
  - `MyCred_Cart_Calculator` - All point calculations and user balance logic
  - `MyCred_Purchasability` - Product affordability filtering with cart awareness
  - `MyCred_UI_Modifier` - WooCommerce button modifications and styling
- **Functionality**: Prevents users from purchasing products if total cart + new product exceeds point balance
- **Integration**: Hooks into `woocommerce_is_purchasable` and `woocommerce_loop_add_to_cart_link` filters

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

- `functions.php` - Main theme functions, asset enqueuing, loads myCred integration
- `src/includes/mycred-integration.php` - myCred system bootstrap and initialization
- `src/includes/mycred/` - Object-oriented myCred classes (Manager, Calculator, Purchasability, UI)
- `impreza-theme-config.json` - Extensive Impreza theme configuration
- `src/scss/main.scss` - SCSS entry point with modular imports
- `src/js/` - JavaScript modules (no bundling, direct inclusion)

### Development Notes

- Always use `npm run watch:css` during development
- **myCred Development**: Follow object-oriented patterns, use dependency injection, maintain singleton integrity
- Test myCred point calculations carefully in staging environment
- myCred classes use WordPress coding standards with comprehensive documentation
- ARES integration is Czech-specific and requires valid IČO numbers
- File upload features require Contact Form 7 plugin
- Theme inherits heavily from Impreza parent theme

### myCred Development Guidelines

**Class Structure Standards:**
- Use singleton pattern for Manager class only
- Implement dependency injection between components
- Follow WordPress coding standards and PHPDoc documentation
- Prefix all classes with `MyCred_` to avoid conflicts
- Use type hints and return type declarations where possible

**Testing myCred Features:**
- Test with various point balances (zero, insufficient, exact, surplus)
- Verify cart calculations with multiple products and quantities
- Test product variations and different product types
- Ensure UI buttons update correctly based on affordability
- Test with myCred disabled/enabled states

**Extending myCred Functionality:**
- Add new methods to appropriate existing classes rather than creating new files
- Maintain backward compatibility with existing point calculation logic
- Use the Manager class to coordinate between components
- Follow the established error handling and validation patterns

## Cursor Rules
- Always use the header for cursor rules when creating cursor like mentioned before