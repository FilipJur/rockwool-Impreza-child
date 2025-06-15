# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Current Focus
WordPress child theme development for Impreza theme with custom features and integrations. Recently completed major myCred architecture refactor implementing cart-aware balance calculation to replace flawed individual product checking system.

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
- **myCred integration**: Cart-aware balance system with dependency injection pattern
- **Modular PHP architecture**: Clear separation of concerns in `src/includes/mycred/pricing/`
  - `MyCred_Pricing_CartContext`: Centralized cart state management
  - `MyCred_Pricing_BalanceCalculator`: Single source of truth for available points
  - `MyCred_Pricing_Purchasability`: Context-aware product affordability logic
  - `MyCred_Pricing_Manager`: Component orchestration and convenience methods

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
- **2025-01-15**: Restructured myCred pricing files into organized directory structure (mycred/pricing/)
- **2025-01-15**: Updated class names with Pricing namespace for better organization and future scalability
- **2025-01-15**: Completed major myCred architecture refactor implementing cart-aware balance calculation
- **2025-01-15**: Replaced flawed individual product checking (40+ calculations) with Single Source of Truth approach
- **2025-01-15**: Implemented BalanceCalculator and CartContext classes with proper dependency injection
- **2025-01-15**: Added context-aware purchasability logic to prevent double-counting in cart/checkout pages
- **2025-01-15**: Enhanced context detection to include WooCommerce REST API requests for block-based checkout compatibility
- **2025-01-15**: Fixed fundamental cart-awareness flaw where products showed as available despite insufficient points
- **2025-01-15**: Removed caching infrastructure following "Make it work → Make it right → Make it fast" principle
- **2025-01-14**: Simplified file upload animations by removing View Transitions API complexity
- **2025-01-14**: Modernized JavaScript architecture from classes to functional patterns
- **2025-01-14**: Refactored AresHandler, AnimationManager, PreviewManager to utility functions
- **2025-01-14**: Implemented proper event listener cleanup across all modules

## Active Decisions
- **Cart-aware balance calculation**: available = balance - cart_total replaces individual product checking (Single Source of Truth)
- **Context-aware purchasability**: Shop pages check can_afford_product(), cart/checkout/API pages check cart_total <= user_balance
- **Performance optimization deferred**: Following "Make it work → Make it right → Make it fast" - caching removed until Phase 3
- **Organized myCred structure**: Pricing functionality separated into dedicated directory with clear class naming
- **Modular myCred architecture**: CartContext → BalanceCalculator → Purchasability dependency chain
- **Use functional patterns over classes**: Classes only for stateful components (FileUpload singleton), functions for everything else
- **Proper cleanup required**: All setup functions must return cleanup methods, all classes must implement destroy/cleanup
- **No auto-generated commit messages**: Keep commit messages clean without Claude Code attribution
- **Avoid View Transitions API**: Causes browser compatibility issues and animation conflicts - use CSS animations instead
- **Keep arrival animations, avoid removal animations**: Staggered arrivals work well, but removal conflicts with plugin display:none behavior

## Last Updated
2025-01-15