# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Current Focus
WordPress child theme development with enterprise-grade architecture. Completed revolutionary transformation to modern App-centric PSR-4 structure with perfect separation of concerns. Ready for advanced feature development on solid architectural foundation.

## Build & Development Commands

### CSS/SCSS Development
- `npm run build:css` - Compile SCSS to CSS with source maps (development)
- `npm run build:css:prod` - Compile SCSS to CSS for production (compressed, no source maps)
- `npm run watch:css` - Watch SCSS files and compile on changes

### JavaScript Development
- `npm run build:js` - Build JavaScript with WordPress Scripts (production)
- `npm run build:js:dev` - Build JavaScript for development
- `npm run watch:js` - Watch JavaScript files and rebuild on changes

### Combined Development
- `npm run build` - Build both CSS and JS for production
- `npm run dev` - Build both CSS and JS for development
- `npm run watch` - Watch both CSS and JS files

### WordPress Integration
- Built JavaScript is output to `build/js/main.js` with WordPress asset file
- CSS is compiled to `style.css` in the theme root
- Assets are automatically enqueued in `functions.php` with dependency management

## Architecture & Features

**📋 For detailed technical information, see:**
- **[Architecture Overview](docs/architecture.md)** - Enterprise structure, domains, and service patterns
- **[Feature Specifications](docs/features.md)** - Detailed feature implementations and technical specs
- **[Development Patterns](docs/development.md)** - Coding standards, patterns, and implementation guidelines
- **[Design System](docs/design/)** - Design tokens, components, and visual specifications

## Recent Changes
- **2025-06-17**: SHORTCODE ARCHITECTURE SIMPLIFICATION - Two major optimization commits
- **2025-06-17**: Streamlined ProductGridShortcode to inline template rendering (74 insertions, 145 deletions)
- **2025-06-17**: Replaced complex method-based rendering with direct template output using `ob_start()`/`ob_get_clean()`
- **2025-06-17**: Eliminated unnecessary abstraction layers: removed `render_builtin_grid()`, `render_balance_info()`, `render_product_item()`
- **2025-06-17**: Simplified shortcode system by removing admin documentation and complex permission checking (151 total deletions)
- **2025-06-17**: Removed 76-line admin documentation system from ShortcodeManager
- **2025-06-17**: Streamlined attribute sanitization in ShortcodeBase using inline validation
- **2025-06-17**: Maintained React-like component benefits while reducing boilerplate complexity
- **2025-06-17**: Created domain-based Repomix bundles for better code organization (10 logical bundles including app-comprehensive)
- **2025-06-17**: Added comprehensive App directory bundle for complete enterprise architecture analysis
- **2025-06-17**: Established mandatory bundle maintenance protocol for all new files
- **2025-01-15**: REVOLUTIONARY ARCHITECTURE TRANSFORMATION - Completed App-centric enterprise structure
- **2025-01-15**: Created `src/App/` directory housing all PHP application logic with perfect separation
- **2025-01-15**: Achieved domain decoupling: independent ECommerce and Shortcodes domains
- **2025-01-15**: Transformed bootstrap: eliminated vestigial `includes/` dir, renamed to `src/bootstrap.php`
- **2025-01-15**: Implemented React-like shortcode components with dependency injection
- **2025-01-15**: PSR-4 autoloading: `MistrFachman\` → `src/App/` with optimized class mapping
- **2025-01-15**: Modern namespaces: `MistrFachman\MyCred\ECommerce\Manager`, `MistrFachman\Shortcodes\ShortcodeManager`
- **2025-01-15**: Completed cart-aware balance calculation (Single Source of Truth architecture)
- **2025-01-15**: Replaced 40+ individual product checks with unified calculation system
- **2025-01-15**: Added context-aware purchasability preventing double-counting in cart/checkout
- **2025-01-15**: Enhanced WooCommerce REST API detection for block-based checkout compatibility
- **2025-01-14**: Simplified file upload animations removing View Transitions API complexity
- **2025-01-14**: Modernized JavaScript from classes to functional patterns

## Active Decisions
- **PSR-4 autoloading for entire codebase**: Composer autoloader replaces all manual require_once statements
- **Domain-driven architecture**: Code organized by business domain (ECommerce, Shortcodes) not implementation features
- **Component-based shortcodes**: React/Next.js-style reusable components with data injection and templating
- **Auto-discovery pattern**: Shortcodes automatically registered by scanning directory structure
- **Cart-aware balance calculation**: available = balance - cart_total replaces individual product checking (Single Source of Truth)
- **Context-aware purchasability**: Shop pages check can_afford_product(), cart/checkout/API pages check cart_total <= user_balance
- **Namespaced classes**: `MistrFachman\MyCred\ECommerce\Manager` replaces `MyCred_Pricing_Manager`
- **Single bootstrap file**: One require_once for autoloader, eliminates dependency management complexity
- **Use functional patterns over classes**: Classes only for stateful components (FileUpload singleton), functions for everything else
- **Proper cleanup required**: All setup functions must return cleanup methods, all classes must implement destroy/cleanup
- **Avoid View Transitions API**: Causes browser compatibility issues and animation conflicts - use CSS animations instead
- **NO INLINE STYLING IN SHORTCODES**: Shortcodes are functional smart components - styling is handled separately in SCSS/CSS files
- **Semantic CSS naming for shortcodes**: Wrapper elements include context classes (e.g., `filter-affordable`, `filter-unavailable`) for targeted styling
- **Inline template rendering over method abstraction**: Use `ob_start()`/`ob_get_clean()` for direct template output instead of complex method chains
- **Eliminate unnecessary abstraction layers**: Remove intermediate methods when they don't add value (e.g., `render_builtin_grid()`)
- **Simplify administrative features**: Remove complex admin documentation systems and permission checking that add boilerplate without business value
- **Domain-based code bundles**: Organize code analysis and documentation by business domains rather than technical file types
- **Bundle maintenance required**: Every new file created must be added to appropriate Repomix bundle for code organization and analysis

## Bundle Management Protocol

### Repomix Bundle Maintenance
- **MANDATORY**: When creating any new file, immediately add it to the appropriate bundle in `.repomix/bundles.json`
- **Domain-based organization**: Group files by business domain (ECommerce, Shortcodes, etc.) not technical type
- **Comprehensive coverage**: The `app-comprehensive` bundle contains the complete `src/App/` directory for full architecture analysis
- **Bundle updates**: Update `lastUsed` timestamp when modifying files within a bundle
- **New domains**: Create new bundles for new business domains or major features


## Last Updated
2025-06-17
