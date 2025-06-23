# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Current Focus
WordPress child theme development with enterprise-grade architecture. **REALIZACE SYSTEM FULLY STABILIZED** - All critical bugs resolved: status revert bug eliminated, bulk approval properly processes UI points data, and "No Debt" policy implemented to prevent negative balances. Architecture consolidated into cohesive AdminController. System is production-ready and follows controlled complexity patterns.

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
- **Working in `src/App/Realizace/`** → Read `docs/architecture.md` for admin controller patterns + CLAUDE.md Active Decisions for complexity rules
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
- **"Realizace status problems"** → Check CLAUDE.md Active Decisions + verify AdminController consolidation + review PointsHandler hooks
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
- **2025-06-23**: [refactor] Create domain abstraction architecture with fixed points - Created base classes (PostTypeManagerBase, AdminControllerBase, PointsHandlerBase, FormHandlerBase) and refactored Realizace domain to use them. Implemented fixed 2500 points default for realizace with admin override capability. Simplified bulk approval workflow by removing UI points collection since defaults are automatically applied.
- **2025-06-23**: [update] Improve admin interface enhancements - Added pending realizace column to wp-admin/users.php table, improved MyCred notices to show realizace titles instead of IDs, implemented dynamic grid layout for realizace cards (1 item = full width, 2 items = 50% each, 3+ items = standard grid)
- **2025-06-23**: [fix] Add permanent deletion handler with No Debt policy - Complete point revocation system now handles permanent deletion while preventing negative balances
- **2025-06-23**: [refactor] Realizace admin architecture consolidation - Merged AdminSetup, AdminUIManager, and AdminAjaxHandler into unified AdminController, fixed status revert bug, improved bulk approve logic
- **2025-06-23**: [fix] Remove Tailwind CSS from WordPress admin - Fixed admin table layout issues caused by Tailwind .fixed class conflicting with WordPress core table behavior
- **2025-06-23**: [fix] Implement "No Debt" policy in PointsHandler - Points revocation now respects user balance limits to prevent negative balances, maintaining business rule integrity
- **2025-06-19**: [update] Remove dependency passthrough in AdminCardRenderer - Direct BusinessDataManager injection eliminates unnecessary RegistrationHooks intermediary
- **2025-06-19**: Functional JavaScript modernization complete - All feature modules converted to functional patterns with isolated state and proper cleanup
- **2025-06-19**: Access control system complete - Defense-in-depth access control with IČO uniqueness validation and role-based page access
- **2025-06-18**: Business registration system complete - Full ARES integration with structured data storage and modal admin interface
- **2025-06-18**: User management system complete - Three-stage approval workflow with bidirectional role management

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
- **Enterprise architecture**: Domain-driven structure with PSR-4 autoloading and single bootstrap
- **Abstract base class architecture** (2025-06-23): Created PostTypeManagerBase, AdminControllerBase, PointsHandlerBase, and FormHandlerBase for domain abstraction. Enables rapid creation of new domains (Faktury, Certifikace) by extending proven patterns
- **Fixed default points system** (2025-06-23): Realizace awards fixed 2500 points with admin override capability. Single source of truth through ACF fields with automatic population. Simplifies admin workflow while maintaining flexibility
- **Functional JavaScript patterns**: setupFunction() approach for all feature modules with isolated state
- **Component-based shortcodes**: React-style reusable components with auto-discovery
- **Single Source of Truth**: Centralized balance calculation and status management
- **Direct dependency injection**: Eliminate unnecessary passthroughs, inject concrete dependencies
- **Tailwind CSS only**: No inline styling, semantic wrapper classes for targeted styling
- **Three-stage user approval**: Modular services with AJAX session handling via cookies
- **Living memory system**: CLAUDE.md hub with context-aware documentation triggers
- **Cohesive controllers over fragmentation** (2025-06-23): Single AdminController with clear responsibility sections instead of over-decomposed classes - line count is secondary to preventing mixed responsibilities and tight coupling
- **"No Debt" business policy** (2025-06-23): Point revocation never creates negative balances - revoke only up to current user balance to maintain financial integrity
- **Frontend-only Tailwind CSS** (2025-06-23): Tailwind CSS only loaded on frontend via wp_enqueue_scripts to prevent admin class conflicts with WordPress core

## Known Issues
*No current known issues. System is production-ready with complete No Debt policy.*

## Archive

### Archived Recent Changes (2025-06-17 to 2025-06-18)
- **SHORTCODE ARCHITECTURE SIMPLIFICATION** - Streamlined rendering with direct template output
- **REGISTRATION SYSTEM DEVELOPMENT** - Complete three-stage user approval workflow
- **ARES INTEGRATION** - Server-side validation with business data sync
- **ADMIN INTERFACE ENHANCEMENTS** - Card-based UI with design token integration
- **DEPENDENCY MANAGEMENT** - Service extraction and modular architecture improvements

### Archived Recent Changes (2025-01-15)
- **REVOLUTIONARY ARCHITECTURE TRANSFORMATION** - App-centric enterprise structure
- **PSR-4 AUTOLOADING** - Modern namespace organization
- **CART-AWARE BALANCE SYSTEM** - Single Source of Truth implementation
- **REACT-LIKE SHORTCODES** - Component-based architecture with dependency injection

## Bundle Management Protocol

### Repomix Bundle Maintenance
- **MANDATORY**: When creating any new file, immediately add it to the appropriate bundle in `.repomix/bundles.json`
- **Domain-based organization**: Group files by business domain (ECommerce, Shortcodes, etc.) not technical type
- **Comprehensive coverage**: The `app-comprehensive` bundle contains the complete `src/App/` directory for full architecture analysis
- **Bundle updates**: Update `lastUsed` timestamp when modifying files within a bundle
- **New domains**: Create new bundles for new business domains or major features


## Last Updated
2025-06-23 (stabilized admin architecture)
