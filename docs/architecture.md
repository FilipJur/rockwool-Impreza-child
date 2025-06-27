# Architecture Overview

This document provides detailed technical architecture information for the WordPress child theme with enterprise-grade structure.

## Enterprise App-centric Architecture

### Core Structure
- **Enterprise App-centric architecture**: All PHP logic organized in `src/App/` directory
- **Perfect separation of concerns**: App logic vs frontend assets (js/scss)
- **PSR-4 autoloaded**: Full Composer autoloading with `MistrFachman\` → `src/App/` mapping
- **Single bootstrap**: `src/bootstrap.php` orchestrates entire application

### Domain Organization

#### Base Architecture (`src/App/Base/`)
- **AdminControllerBase**: Foundation for domain admin interfaces with reject/revoke button system
- **AdminCardRendererBase**: Template-based rendering with MVC separation
- **PostTypeManagerBase**: Domain post type registration and management patterns
- **PointsHandlerBase**: Unified myCred points management with dual-pathway architecture
- **FormHandlerBase**: Contact Form 7 integration patterns with user detection
- **FieldServiceBase**: ACF field access with meta fallback for consistent data access

#### myCred E-Commerce Domain (`src/App/MyCred/ECommerce/`)
- **Manager**: Component orchestration with dependency injection
- **CartContext**: Centralized cart state management
- **BalanceCalculator**: Single source of truth for available points
- **Purchasability**: Context-aware product affordability logic
- **UiModifier**: WooCommerce UI modifications

#### Realizace Domain (`src/App/Realizace/`)
- **Manager**: Project submission orchestrator with form integration
- **AdminController**: Unified admin interface with reject/revoke button system
- **RealizaceCardRenderer**: Template-based admin card rendering
- **PointsHandler**: Fixed 2500 points with dual-pathway architecture (ACF save + AJAX direct)
- **RealizaceFieldService**: Centralized ACF field access with English field names
- **NewRealizaceFormHandler**: Contact Form 7 integration with user detection

#### Faktury Domain (`src/App/Faktury/`) - 95% Complete
- **Manager**: Invoice submission orchestrator with dynamic points calculation
- **AdminController**: Admin interface with pre-publish validation gatekeeper
- **FakturyCardRenderer**: Template-based admin card rendering for invoices
- **PointsHandler**: Dynamic points calculation (floor(value/30)) with dual-pathway architecture
- **FakturaFieldService**: Centralized ACF field access for invoice fields
- **ValidationService**: Business rules validation (date validation, monthly limits preparation)
- **NewFakturyFormHandler**: Contact Form 7 integration for invoice submissions

#### Shortcode System (`src/App/Shortcodes/`)
- **ShortcodeBase**: Abstract base class with validation and templating
- **ShortcodeManager**: Auto-discovery registry with dependency injection
- **ProductGridShortcode**: Component with balance filtering and React-like data props
- **MyRealizaceShortcode**: User-facing project management interface

#### Users Domain (`src/App/Users/`)
- **Manager**: Singleton orchestrator for complete user registration system
- **RegistrationHooks**: SMS OTP and Contact Form 7 integration with session handling
- **RoleManager**: Custom role management (`pending_approval`, `full_member`)
- **AccessControl**: Permission checking and user state validation
- **AdminInterface**: Admin approval workflow and user promotion
- **BusinessDataManager**: ARES integration and company data management
- **AdminCardRenderer**: Business data modal and user management interface

#### Unified Services Layer (`src/App/Services/`)
- **StatusManager**: Configurable custom post status management for any domain
- **AdminAssetManager**: Unified admin asset loading with domain-specific configuration
- **ProductService**: WooCommerce product fetching and myCred balance filtering logic
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

### Admin Interface Architecture

#### Dedicated Reject/Revoke Button System
- **Context-Aware UX**: Three distinct button states replacing problematic status dropdown
  - **Pending Posts**: Red "Odmítnout" button for rejection
  - **Published Posts**: Orange "Zrušit schválení" button for approval revocation  
  - **Rejected Posts**: Grey "Odmítnuto" disabled button (visual indicator only)
- **WordPress Integration**: StatusManager adds "Odmítnuto" option to native post status dropdown
- **Validation Gatekeeper**: Eliminates conflicts with pre-publish validation system
- **AJAX Endpoints**: Dedicated `mistr_fachman_{post_type}_editor_reject` actions with proper nonce security

#### Template System Architecture
- **Complete MVC Separation**: Zero HTML generation in PHP classes
- **Template Directory**: `templates/admin-cards/` with reusable components
  - `dashboard-wrapper.php` - Main container with responsive grid
  - `stats-header.php` - Statistics and summary display
  - `status-section.php` - Post status indicators and counts
  - `post-card-full.php` - Detailed post cards with actions
  - `post-card-compact.php` - Summary post cards for bulk views
  - `post-gallery.php` - Image gallery rendering
  - `post-actions.php` - Admin action buttons (approve/reject)
  - `domain-details/` - Domain-specific detail templates
- **Data Preparation**: AdminCardRendererBase provides clean data arrays to templates
- **Template Loading**: Dynamic template loading with fallback mechanisms

#### Base Class Architecture Patterns
- **AdminControllerBase**: Common patterns for all domain admin interfaces
  - User profile card integration
  - Custom admin columns (points, status)
  - AJAX endpoint registration and security
  - Reject/revoke button rendering and handling
  - Pre-publish validation gatekeeper
- **PointsHandlerBase**: Dual-pathway points management
  - ACF save_post hooks for editor context
  - Direct award_points() for AJAX context
  - No Debt policy enforcement
- **FieldServiceBase**: Consistent ACF field access with meta fallback

### User Registration Architecture
- **Three-Stage Flow**: Guest → SMS OTP → Prospect → Form Submission → Awaiting Review → Admin Approval → Full Member
- **Session Management**: Cookie fallback handles Contact Form 7 AJAX session loss
- **Role-Based Access**: `pending_approval` users blocked from purchases until promoted to `full_member`
- **Single Source of Truth**: RegistrationStatus centralizes all status constants and validation logic

### Domain Validation Architecture
- **Pre-Publish Gatekeeper**: AdminControllerBase validation system prevents invalid submissions
- **Domain-Specific Rules**: Each domain implements `run_pre_publish_validation()`
  - **Realizace**: No specific validation (always passes)
  - **Faktury**: Date validation (current year only), monthly limits preparation
- **Validation Service Pattern**: Separate ValidationService classes for complex business rules
- **Error Handling**: Transient-based admin notices with specific error messages

## Frontend Architecture

### Dual JavaScript Architecture
- **Frontend**: `src/js/main.js` - ThemeApp class for public-facing functionality
- **Admin**: `src/js/admin.js` - AdminApp class for WordPress admin interface functionality

### Feature-Based JavaScript Structure
JavaScript is organized into feature modules under `src/js/features/`:
- `file-upload/` - Enhanced file upload functionality with drag-and-drop, previews, and animations
- `ares/` - Czech company registry (ARES) integration for form auto-completion
- `admin/` - Admin-specific functionality
  - `RealizaceManagement.js` - Realizace admin interface interactions
  - `FakturyManagement.js` - Faktury admin interface interactions
  - `business-data-modal.js` - User business data management modal

### Admin Application Architecture
- **AdminApp Class**: Manages admin-specific module initialization and lifecycle
- **Event-Driven Architecture**: Global event delegation for admin interactions
- **Reject Button System**: Centralized handling of reject/revoke button interactions
  - Context-aware confirmation messages
  - AJAX error recovery and button state management
  - Proper form data handling with nonce security
- **Module Conditional Loading**: Modules loaded based on DOM elements and admin context
- **Clean Separation**: Admin functionality completely separate from frontend code

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