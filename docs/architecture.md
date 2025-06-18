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

#### Users Domain (`src/App/Users/`)
- **Manager**: Singleton orchestrator for complete user registration system
- **RegistrationHooks**: SMS OTP and Contact Form 7 integration with session handling
- **RoleManager**: Custom role management (`pending_approval`, `full_member`)
- **AccessControl**: Permission checking and user state validation
- **AdminInterface**: Admin approval workflow and user promotion
- **RegistrationStatus**: Centralized status constants and validation
- **RegistrationConfig**: Form configuration and system settings
- **ThemeIntegration**: WordPress theme integration hooks

#### Service Layer (`src/App/Services/`)
- **ProductService**: Encapsulates WooCommerce product fetching and myCred balance filtering logic
- **UserService**: User status checking, permission logic, and registration state management
- **UserDetectionService**: Robust user detection with cookie fallback for AJAX scenarios

### Service Pattern Architecture
- **Dependency Injection**: All components receive services via constructor injection, never create dependencies
- **Service Layer**: Provides business logic abstraction between components and core domains
- **Communication Flow**:
  ```
  Component → Service → Domain Manager → Core Logic
  ProductGridShortcode → ProductService → ECommerceManager → CartContext/BalanceCalculator
  UserHeaderShortcode → UserService → UsersManager → RegistrationStatus/RoleManager
  ```
- **Pure Functional Components**: Shortcodes are stateless components that receive data and render HTML
- **Cross-Domain Integration**: Users domain integrates with E-Commerce for role-based purchasing
- **No Direct Domain Access**: Components never directly access WordPress/plugin APIs - always through services
- **Testability**: Service layer enables easy mocking and unit testing of all business logic

### User Registration Architecture
- **Three-Stage Flow**: Guest → SMS OTP → Prospect → Form Submission → Awaiting Review → Admin Approval → Full Member
- **Session Management**: Cookie fallback handles Contact Form 7 AJAX session loss
- **Role-Based Access**: `pending_approval` users blocked from purchases until promoted to `full_member`
- **Single Source of Truth**: RegistrationStatus centralizes all status constants and validation logic

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
- `src/scss/main.scss` - Main stylesheet entry point compiled to `build/css/style-temp.css`
- **Two-step compilation**: SCSS → temp file → PostCSS → final `style.css`
- Organized by: base, layout, components, utilities
- Uses modern SCSS module system with `@use`
- **PostCSS integration**: Autoprefixer and other PostCSS plugins process compiled SCSS

### WordPress Integration
- Child theme of Impreza theme
- Built JavaScript is output to `build/js/main.js` with WordPress asset file
- CSS is compiled to `style.css` in the theme root
- Assets are automatically enqueued in `functions.php` with dependency management