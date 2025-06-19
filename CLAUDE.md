# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Current Focus
WordPress child theme development with enterprise-grade architecture. **FUNCTIONAL JAVASCRIPT MODERNIZATION COMPLETE** - Converted class-based modules to modern functional patterns for better reusability and maintainability. All feature modules (FileUpload, BusinessModal, AccessControl) now follow setupAresForm pattern with isolated state and proper cleanup.

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

## Living Memory System

**🧠 This CLAUDE.md serves as the central memory hub, intertwined with detailed documentation:**
- **[Architecture Overview](docs/architecture.md)** - Enterprise structure, domains, and service patterns
- **[Feature Specifications](docs/features.md)** - Detailed feature implementations and technical specs
- **[Development Patterns](docs/development.md)** - Coding standards, patterns, and implementation guidelines
- **[Design System](docs/design/)** - Design tokens, components, and visual specifications

**Agent Instructions:** Always consult relevant documentation files based on context:

### **File-Based Triggers**
- **Working in `src/App/MyCred/`** → Read `docs/architecture.md` for e-commerce domain patterns
- **Working in `src/App/Users/`** → Read `docs/features.md` for registration system specs + `docs/development.md` for user testing patterns
- **Working in `src/App/Shortcodes/`** → Read `docs/architecture.md` for component patterns + `docs/development.md` for template rendering
- **Working in `src/js/features/`** → Read `docs/development.md` for JavaScript patterns + `docs/architecture.md` for frontend structure
- **Working with `.scss` files** → Read `docs/design/` for design tokens + `docs/development.md` for Tailwind integration

### **Task-Based Triggers**
- **User/authentication issues** → Read `docs/features.md` registration specs + `docs/development.md` debugging guide
- **Performance optimization** → Read `docs/architecture.md` service patterns + `docs/development.md` optimization guidelines
- **Cross-domain integration** → Read `docs/architecture.md` service layer communication patterns
- **New feature development** → Read `docs/architecture.md` domain structure + `docs/development.md` patterns + `docs/features.md` integration points
- **Bug fixing/debugging** → Read `docs/development.md` debugging section + relevant domain docs
- **Refactoring work** → Read `docs/architecture.md` patterns + `docs/development.md` complexity management

### **High-Level Context Triggers**
- **"Registration not working"** → Read `docs/features.md` registration flow + `docs/development.md` user testing + check AJAX session handling
- **"Balance calculation issues"** → Read `docs/architecture.md` e-commerce domain + Single Source of Truth pattern + context-aware logic
- **"Shortcode rendering problems"** → Read `docs/architecture.md` component patterns + `docs/development.md` template rendering + semantic CSS
- **"User permissions wrong"** → Read `docs/features.md` role system + `docs/development.md` user domain patterns + service layer usage
- **"Frontend build issues"** → Read `docs/architecture.md` frontend structure + `docs/development.md` JavaScript patterns + build commands
- **"Styling inconsistencies"** → Read `docs/design/` design system + `docs/development.md` Tailwind patterns + semantic naming

### **Maintenance Triggers**
- **File >200 lines** → Read `docs/development.md` complexity management + splitting guidelines
- **Adding new domain** → Read `docs/architecture.md` domain organization + PSR-4 patterns + service layer design
- **WordPress integration** → Read `docs/development.md` WordPress standards + `docs/architecture.md` integration patterns
- **Third-party API integration** → Read `docs/development.md` error handling + `docs/architecture.md` service patterns

## Recent Changes
- **2025-06-19**: 🚀 **FUNCTIONAL JAVASCRIPT MODERNIZATION COMPLETE** - Converted all feature modules from classes to functional patterns following setupAresForm approach for better reusability
- **2025-06-19**: ✅ **MODULAR REUSABILITY ACHIEVED** - FileUpload, BusinessModal, and AccessControl now support multiple instances per page with isolated state and proper cleanup
- **2025-06-19**: 🎯 **IMPROVED MAINTAINABILITY** - Each functional module returns control object with cleanup methods, following proven functional patterns from ARES handler
- **2025-06-19**: 📦 **BETTER TESTING ISOLATION** - Functional modules are easier to test independently without complex class hierarchies and singleton patterns
- **2025-06-19**: 🛡️ **BULLETPROOF ACCESS CONTROL IMPLEMENTED** - Defense-in-depth access control with server-side redirects and client-side validation for registration pages
- **2025-06-19**: ✅ **IČO UNIQUENESS VALIDATION** - Prevents duplicate registrations with same IČO, clear error messages for users
- **2025-06-19**: 🎯 **ROLE-BASED PAGE ACCESS** - Full members blocked from registration pages, pending users guided through proper flow
- **2025-06-19**: 🚀 **FRONTEND ACCESS CONTROL** - JavaScript module provides immediate redirects with server-side data injection
- **2025-06-18**: 🔐 **STRICT ARES ENFORCEMENT COMPLETE** - Server-side forced use of exact ARES data when IČO validates, with discrepancy logging and comprehensive user profile sync
- **2025-06-18**: 👤 **ENHANCED USER PROFILE SYNC** - Added WordPress nickname and complete WooCommerce billing fields population from registration data
- **2025-06-18**: 🔒 **ARES FIELD LOCKING COMPLETE** - ARES-validated fields (company name, address) become read-only after successful fetch, unlock for manual editing on ARES failure
- **2025-06-18**: ✅ **DEPENDENCY INJECTION FIX** - Fixed BusinessDataManager injection in Users Manager for proper AdminCardRenderer functionality
- **2025-06-18**: 🎯 **USER ACCOUNT INTEGRATION COMPLETE** - Bidirectional sync between business data and WordPress user profile fields (first_name, last_name, email, display_name)
- **2025-06-18**: 🎨 **DESIGN SYSTEM COMPLIANCE** - Updated SCSS with proper ROCKWOOL design tokens, .mistr- prefixed CSS classes to avoid WordPress admin conflicts
- **2025-06-18**: 🚀 **REALIZACE-READY ARCHITECTURE** - Data type abstraction in BusinessDataManager, myCred integration hooks (mistr_fachman_realizace_approved), future-ready admin cards
- **2025-06-18**: ✅ **ADMIN UI ISOLATION** - All admin styles properly scoped with .mistr- prefixes using only _variables.scss design tokens
- **2025-06-18**: 🎨 **ADMIN INTERFACE ENHANCED** - Card-based management system with design tokens, bidirectional promotion/revocation, persistent business data access
- **2025-06-18**: ✅ **USER MANAGEMENT FLEXIBILITY** - Added revoke functionality to return full members back to pending status for admin control
- **2025-06-18**: 🎯 **DESIGN TOKEN INTEGRATION** - Implemented proper ROCKWOOL brand colors, typography, and spacing throughout admin interface
- **2025-06-18**: 🚀 **FUTURE-READY ARCHITECTURE** - Prepared admin cards for realizace management and project workflows
- **2025-06-18**: 🎉 **BUSINESS REGISTRATION SYSTEM COMPLETE** - Enhanced registration with comprehensive ARES validation, structured business data storage, and modal-based admin review
- **2025-06-18**: ✅ **SERVER-SIDE ARES VALIDATION** - IČO validation with ARES API integration, business criteria checking, and robust error handling
- **2025-06-18**: ✅ **STRUCTURED BUSINESS DATA STORAGE** - Complete user meta schema for company info, representative details, and validation status
- **2025-06-18**: ✅ **MODAL-BASED ADMIN INTERFACE** - Professional business data review modal with AJAX loading and comprehensive data display
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

## User Registration System

### **Complete Three-Stage Registration Flow** ✅
1. **Stage 1: Guest → Prospect (Automated)**
   - User registers via SMS OTP on `/prihlaseni` page
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
- **Single Source of Truth balance system**: available = balance - cart_total with context-aware purchasability (shop vs cart/checkout)
- **Modern namespaced classes**: `MistrFachman\MyCred\ECommerce\Manager` replaces legacy underscore naming
- **Single bootstrap file**: One require_once for autoloader, eliminates dependency management complexity
- **Functional module pattern for features**: All feature modules (FileUpload, BusinessModal, AccessControl) use setupFunction() pattern for reusability and isolation
- **Classes for application coordinators only**: ThemeApp and AdminApp remain classes for single-instance lifecycle management and module coordination
- **Tailwind CSS for component styling**: Utility-first CSS framework for consistent, maintainable component styling
- **NO INLINE STYLING IN SHORTCODES**: Shortcodes are functional smart components - styling handled via Tailwind classes
- **Semantic CSS naming**: Wrapper elements include context classes for targeted styling (e.g., `filter-affordable`, `filter-unavailable`)
- **Template simplification**: Direct `ob_start()`/`ob_get_clean()` output over complex method abstraction layers
- **Administrative feature simplification**: Remove boilerplate admin documentation and permission checking systems
- **Registration system architecture**: Three-stage user approval with modular services (UserDetectionService, RegistrationStatus, RegistrationConfig)
- **AJAX session handling**: Cookie fallback ensures form submissions work across all browser contexts
- **Living memory system architecture**: CLAUDE.md as central hub with context-aware documentation consultation
- **Documentation integration protocol**: Agent must read relevant docs/ files based on task context (architecture, development, features, design)

## Known Issues
*No current known issues. System is production-ready.*

## Archive

### Archived Recent Changes (2025-01-15)
- **REVOLUTIONARY ARCHITECTURE TRANSFORMATION** - Completed App-centric enterprise structure
- **Created `src/App/` directory** - Housing all PHP application logic with perfect separation
- **Achieved domain decoupling** - Independent ECommerce and Shortcodes domains
- **Transformed bootstrap** - Eliminated vestigial `includes/` dir, renamed to `src/bootstrap.php`
- **Implemented React-like shortcode components** - With dependency injection
- **PSR-4 autoloading** - `MistrFachman\` → `src/App/` with optimized class mapping
- **Modern namespaces** - `MistrFachman\MyCred\ECommerce\Manager`, `MistrFachman\Shortcodes\ShortcodeManager`
- **Completed cart-aware balance calculation** - Single Source of Truth architecture
- **Replaced 40+ individual product checks** - With unified calculation system
- **Added context-aware purchasability** - Preventing double-counting in cart/checkout
- **Enhanced WooCommerce REST API detection** - For block-based checkout compatibility
- **Simplified file upload animations** - Removing View Transitions API complexity (2025-01-14)
- **Modernized JavaScript** - From classes to functional patterns (2025-01-14)

## Bundle Management Protocol

### Repomix Bundle Maintenance
- **MANDATORY**: When creating any new file, immediately add it to the appropriate bundle in `.repomix/bundles.json`
- **Domain-based organization**: Group files by business domain (ECommerce, Shortcodes, etc.) not technical type
- **Comprehensive coverage**: The `app-comprehensive` bundle contains the complete `src/App/` directory for full architecture analysis
- **Bundle updates**: Update `lastUsed` timestamp when modifying files within a bundle
- **New domains**: Create new bundles for new business domains or major features


## Last Updated
2025-06-19
