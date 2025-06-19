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
- **2025-06-19**: [update] Remove dependency passthrough in AdminCardRenderer - Direct BusinessDataManager injection eliminates unnecessary RegistrationHooks intermediary
- **2025-06-19**: Functional JavaScript modernization complete - All feature modules converted to functional patterns with isolated state and proper cleanup
- **2025-06-19**: Access control system complete - Defense-in-depth access control with IČO uniqueness validation and role-based page access
- **2025-06-18**: Business registration system complete - Full ARES integration with structured data storage and modal admin interface
- **2025-06-18**: User management system complete - Three-stage approval workflow with bidirectional role management
- **2025-06-18**: Design system integration complete - ROCKWOOL design tokens with proper CSS isolation using .mistr- prefixes

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
- **Functional JavaScript patterns**: setupFunction() approach for all feature modules with isolated state
- **Component-based shortcodes**: React-style reusable components with auto-discovery
- **Single Source of Truth**: Centralized balance calculation and status management
- **Direct dependency injection**: Eliminate unnecessary passthroughs, inject concrete dependencies
- **Tailwind CSS only**: No inline styling, semantic wrapper classes for targeted styling
- **Three-stage user approval**: Modular services with AJAX session handling via cookies
- **Living memory system**: CLAUDE.md hub with context-aware documentation triggers

## Known Issues
*No current known issues. System is production-ready.*

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
2025-06-19 (cleaned and refocused)
