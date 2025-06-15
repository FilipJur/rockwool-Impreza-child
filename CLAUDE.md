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

## Architecture Overview

### Feature-Based JavaScript Structure
JavaScript is organized into feature modules under `src/js/features/`:
- `file-upload/` - Enhanced file upload functionality with drag-and-drop, previews, and animations
- `ares/` - Czech company registry (ARES) integration for form auto-completion

### Main Application
- `src/js/main.js` - ThemeApp class manages module initialization and lifecycle
- Modules are conditionally loaded based on DOM elements present
- Supports dynamic content reloading and AJAX compatibility

### SCSS Architecture
- `src/scss/main.scss` - Main stylesheet entry point
- Organized by: base, layout, components, utilities
- Uses modern SCSS module system with `@use`

### WordPress Integration
- Child theme of Impreza theme
- **Enterprise App-centric architecture**: All PHP logic organized in `src/App/` directory
- **Perfect separation of concerns**: App logic vs frontend assets (js/scss)
- **PSR-4 autoloaded**: Full Composer autoloading with `MistrFachman\` → `src/App/` mapping
- **Single bootstrap**: `src/bootstrap.php` orchestrates entire application
- **myCred E-Commerce domain**: `src/App/MyCred/ECommerce/`
  - `Manager`: Component orchestration with dependency injection
  - `CartContext`: Centralized cart state management
  - `BalanceCalculator`: Single source of truth for available points
  - `Purchasability`: Context-aware product affordability logic
  - `UiModifier`: WooCommerce UI modifications
- **Decoupled Shortcode system**: `src/App/Shortcodes/` independent presentation layer
  - `ShortcodeBase`: Abstract base class with validation and templating
  - `ShortcodeManager`: Auto-discovery registry with dependency injection
  - `ProductGridShortcode`: Component with balance filtering and React-like data props

### Service Pattern Architecture
- **Dependency Injection**: Shortcodes receive services via constructor injection, never create dependencies
- **Service Layer**: `src/App/Services/` provides business logic abstraction between shortcodes and core domains
- **ProductService**: Encapsulates WooCommerce product fetching and myCred balance filtering logic
- **Communication Flow**:
  ```
  Shortcode → Service → Domain Manager → Core Logic
  ProductGridShortcode → ProductService → ECommerceManager → CartContext/BalanceCalculator
  ```
- **Pure Functional Components**: Shortcodes are stateless components that receive data and render HTML
- **No Direct Domain Access**: Shortcodes never directly access MyCred/WooCommerce APIs - always through services
- **Testability**: Service layer enables easy mocking and unit testing of shortcode logic

## Key Features

### File Upload Enhancement
- Drag-and-drop interface with visual feedback
- Image preview generation and thumbnail handling
- Staggered arrival animations for file previews
- Integration with WordPress file upload plugins
- Simplified animation system without View Transitions API

### myCred WooCommerce Integration
- **Cart-aware balance calculation**: available = balance - cart_total (Single Source of Truth)
- **Context-aware purchasability**: Different logic for shop vs cart/checkout/API pages to prevent double-counting
- **WooCommerce compatibility**: Handles both traditional and block-based checkout via REST API detection
- **Performance optimized**: Replaced 40+ individual product checks with single calculation
- **Modular architecture**: CartContext → AvailableBalanceCalculator → Purchasability dependency chain
- **Enhanced validation**: Multi-level validation at display, add-to-cart, and checkout stages

### ARES Integration
- Czech company registry lookup
- Auto-completion of company information in forms
- Error handling and validation

## Development Notes

### Modern JavaScript Architecture
- **Functions over classes**: Use classes only for stateful components, functions for utilities
- **ES6 modules**: Clean import/export patterns with object exports for utilities
- **Functional approach**: Most features are implemented as setup functions returning control objects
- **Minimal state management**: Only `FileUpload` uses class pattern (singleton + complex state)
- **Proper cleanup**: Functions return cleanup methods, classes implement destroy/cleanup

#### Current Patterns
- **api** and **validation**: Object exports with utility functions
- **setupAresForm()**: Functional setup returning control object with cleanup
- **setupPreviewObserver()**: Functional DOM observer with proper cleanup
- **Animation utilities**: Pure functions for staggered animations (no view transitions)
- **FileUpload**: Class for complex state management (legitimate singleton pattern)

### WordPress Hooks
- Uses WordPress action hooks for proper initialization timing
- Conditional loading based on plugin availability
- Follows WordPress coding standards

### Design System
- Design tokens and content reference available in `docs/design/`
- Color palette and typography defined in theme configuration
- Component specifications ready for implementation

## Recent Changes
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

## Last Updated
2025-01-15
