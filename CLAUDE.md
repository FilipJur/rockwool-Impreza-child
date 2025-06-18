# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Current Focus
WordPress child theme development with enterprise-grade architecture. **REGISTRATION SYSTEM COMPLETE** - Three-stage user registration flow fully functional with SMS OTP, Contact Form 7 integration, and admin approval workflow. System ready for production deployment and advanced feature development.

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
- **2025-06-18**: 🚀 **PHASE 1 REFACTORING COMPLETE** - Extracted UserDetectionService, RegistrationStatus, and RegistrationConfig
- **2025-06-18**: ✅ **IMPROVED MODULARITY** - RegistrationHooks reduced from 260 to 190 LOC with better separation of concerns
- **2025-06-18**: ✅ **SINGLE SOURCE OF TRUTH** - Status constants and form configuration centralized in dedicated classes
- **2025-06-18**: ✅ **ENHANCED MAINTAINABILITY** - User detection logic extracted to reusable service with comprehensive fallback methods
- **2025-06-18**: 🎉 **REGISTRATION SYSTEM COMPLETE** - Three-stage user flow fully functional and production-ready
- **2025-06-18**: ✅ **AJAX SESSION ISSUE RESOLVED** - Cookie fallback successfully handles incognito/session edge cases
- **2025-06-18**: ✅ **USER FLOW VERIFIED** - SMS OTP → Form Submission → Admin Approval workflow working perfectly
- **2025-06-18**: Fixed all namespace mismatches: Services and Shortcodes now use correct `MistrFachman\` prefix
- **2025-06-18**: Enhanced user detection with cookie parsing for Contact Form 7 AJAX submissions
- **2025-06-18**: Simplified final registration form to "fire and forget" - automatic status updates
- **2025-06-18**: Fixed ShortcodeManager auto-discovery to use correct namespaces
- **2025-06-18**: Resolved bootstrap dependency injection to use proper service instances
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

## User Registration System

### **Complete Three-Stage Registration Flow** ✅
1. **Stage 1: Guest → Prospect (Automated)**
   - User registers via SMS OTP on `/registrace` page
   - System assigns `pending_approval` role + `needs_form` meta status
   - User can browse products but cannot purchase

2. **Stage 2: Form Submission (Automated)**
   - Logged-in pending user submits Contact Form 7 (ID 292)
   - System auto-updates meta status to `awaiting_review`
   - Role remains `pending_approval` - no manual validation required

3. **Stage 3: Admin Approval (Manual)**
   - Admin reviews form submission via email notification
   - Admin promotes user via WordPress dashboard button
   - User role changes to `full_member` with full e-commerce access

### **Technical Implementation**
- **AJAX Session Handling**: Cookie fallback for Contact Form 7 submissions via UserDetectionService
- **Role-Based Access Control**: Pending users blocked from purchasing
- **Cart-Aware Balance System**: Prevents overcommitment during approval process
- **Admin Interface**: One-click promotion with security nonces
- **Modular Architecture**: Extracted services for user detection, status management, and configuration
- **Single Source of Truth**: Centralized constants and configuration classes

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
- **Registration system production-ready**: Three-stage user approval workflow complete with SMS OTP, form submission, and admin approval
- **AJAX session handling**: Cookie fallback ensures form submissions work across all browser contexts (including incognito)
- **Modular refactoring complete**: Phase 1 improvements implemented - UserDetectionService, RegistrationStatus, RegistrationConfig extracted
- **File size optimization**: RegistrationHooks reduced from 260 to 190 LOC through service extraction

## Bundle Management Protocol

### Repomix Bundle Maintenance
- **MANDATORY**: When creating any new file, immediately add it to the appropriate bundle in `.repomix/bundles.json`
- **Domain-based organization**: Group files by business domain (ECommerce, Shortcodes, etc.) not technical type
- **Comprehensive coverage**: The `app-comprehensive` bundle contains the complete `src/App/` directory for full architecture analysis
- **Bundle updates**: Update `lastUsed` timestamp when modifying files within a bundle
- **New domains**: Create new bundles for new business domains or major features


## Last Updated
2025-06-18
