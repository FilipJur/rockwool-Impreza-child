# Architecture Overview

This document provides detailed technical architecture information for the WordPress child theme with enterprise-grade structure.

## Enterprise App-centric Architecture

### Core Structure
- **Enterprise App-centric architecture**: All PHP logic organized in `src/App/` directory
- **Perfect separation of concerns**: App logic vs frontend assets (js/scss)
- **PSR-4 autoloaded**: Full Composer autoloading with `MistrFachman\` → `src/App/` mapping
- **Single bootstrap**: `src/bootstrap.php` orchestrates entire application

### Domain Organization

#### myCred E-Commerce Domain (`src/App/MyCred/ECommerce/`)
- **Manager**: Component orchestration with dependency injection
- **CartContext**: Centralized cart state management
- **BalanceCalculator**: Single source of truth for available points
- **Purchasability**: Context-aware product affordability logic
- **UiModifier**: WooCommerce UI modifications

#### Shortcode System (`src/App/Shortcodes/`)
- **ShortcodeBase**: Abstract base class with validation and templating
- **ShortcodeManager**: Auto-discovery registry with dependency injection
- **ProductGridShortcode**: Component with balance filtering and React-like data props

#### Service Layer (`src/App/Services/`)
- **ProductService**: Encapsulates WooCommerce product fetching and myCred balance filtering logic

### Service Pattern Architecture
- **Dependency Injection**: Shortcodes receive services via constructor injection, never create dependencies
- **Service Layer**: Provides business logic abstraction between shortcodes and core domains
- **Communication Flow**:
  ```
  Shortcode → Service → Domain Manager → Core Logic
  ProductGridShortcode → ProductService → ECommerceManager → CartContext/BalanceCalculator
  ```
- **Pure Functional Components**: Shortcodes are stateless components that receive data and render HTML
- **No Direct Domain Access**: Shortcodes never directly access MyCred/WooCommerce APIs - always through services
- **Testability**: Service layer enables easy mocking and unit testing of shortcode logic

## Frontend Architecture

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
- Built JavaScript is output to `build/js/main.js` with WordPress asset file
- CSS is compiled to `style.css` in the theme root
- Assets are automatically enqueued in `functions.php` with dependency management