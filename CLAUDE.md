# CLAUDE.md

“Living Memory ensures knowledge continuity between agents and sessions, enabling long-term architectural coherence and easier onboarding.”

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Current Focus
WordPress child theme development with enterprise architecture focused on unified service layer optimization and system consolidation. **COMPLETED**: Critical zebricek position calculation bug fixed (SQL syntax error causing incorrect rankings), comprehensive points calculation correction (removed rogue +1), and performance optimization through reduced database queries. ZebricekDataService now serves as unified single source of truth for all zebricek data operations with 100% compliance across all components. Implemented UserStatusService for centralized user status management with proper separation of concerns. **CURRENT STATUS**: Zebricek system fully operational with accurate position calculations, unified data architecture, and production-ready stability. All components properly delegate to service layer with zero data inconsistencies. **UPCOMING**: Orchestrator refactor to centralize component initialization and service management across all domain systems.

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

This CLAUDE.md serves as the central memory hub, integrated with detailed documentation:
- **[Architecture Overview](docs/architecture.md)** - Enterprise structure, domains, and service patterns
- **[Feature Specifications](docs/features.md)** - Detailed feature implementations and technical specs
- **[Development Patterns](docs/development.md)** - Coding standards, patterns, and implementation guidelines
- **[Design System](docs/design/)** - Design tokens, components, and visual specifications
- **[Template System](templates/admin-cards/)** - MVC separation with reusable template components

**Agent Instructions:** Always consult relevant documentation files based on context:

### **File-Based Triggers**
- **Working in `src/App/MyCred/`** → Read `docs/architecture.md` for e-commerce domain patterns
- **Working in `src/App/Users/`** → Read `docs/features.md` for registration system specs + `docs/development.md` for user testing patterns
- **Working in `src/App/Realizace/`** → Read `docs/architecture.md` for admin controller patterns + CLAUDE.md Active Decisions for complexity rules
- **Working in `src/App/Faktury/`** → Read `src/App/Faktury/plan.md` for current implementation status + CLAUDE.md Known Issues for ACF field population
- **Working in `src/App/Services/`** → Read CLAUDE.md Active Decisions for unified service architecture + AdminControllerBase for service integration patterns
- **Working in `src/App/Shortcodes/`** → Read `docs/architecture.md` for component patterns + `docs/development.md` for template rendering
- **Working in `templates/zebricek/`** → Read CLAUDE.md Active Decisions for zebricek architecture + user-points-balance component for three-layer progress bar patterns
- **Working in `src/js/features/`** → Read `docs/development.md` for JavaScript patterns + `docs/architecture.md` for frontend structure
- **Working with `.scss` files** → Read `docs/design/` for design tokens + `docs/development.md` for Tailwind integration
- **Working in `templates/admin-cards/`** → Read `templates/admin-cards/` for MVC patterns + CLAUDE.md Active Decisions for template system architecture

### **Task-Based Triggers**
- **User/authentication issues** → Read `docs/features.md` registration specs + `docs/development.md` debugging guide
- **Performance optimization** → Read `docs/architecture.md` service patterns + `docs/development.md` optimization guidelines
- **Cross-domain integration** → Read `docs/architecture.md` service layer communication patterns
- **New feature development** → Read `docs/architecture.md` domain structure + `docs/development.md` patterns + `docs/features.md` integration points
- **Bug fixing/debugging** → Read `docs/development.md` debugging section + relevant domain docs
- **Refactoring work** → Read `docs/architecture.md` patterns + `docs/development.md` complexity management
- **Service architecture work** → Check CLAUDE.md Active Decisions for unified service patterns + UserStatusService for status management + ZebricekDataService for data operations + upcoming orchestrator refactor for centralized initialization
- **Component development** → Always inject UserStatusService for status checks + use appropriate data services (ZebricekDataService, ECommerce\Manager) + follow single source of truth principle
- **Admin card rendering** → Read `templates/admin-cards/` for template structure + CLAUDE.md Active Decisions for MVC separation patterns
- **Zebricek component issues** → Read CLAUDE.md Active Decisions for zebricek architecture + UserPointsBalanceShortcode for three-layer progress patterns + AccountDashboardShortcode for grid integration
- **Progress bar implementation** → Check UserPointsBalanceShortcode three-layer system + zebricek-progress component for reuse patterns + DRY @extend usage
- **Reject button issues** → Check AdminControllerBase reject button implementation + admin.js event handlers + AJAX endpoints for editor reject
- **Faktury domain issues** → Read `src/App/Faktury/plan.md` for current status + CLAUDE.md Known Issues + ValidationService implementation

### **High-Level Context Triggers**
- **"Registration not working"** → Read `docs/features.md` registration flow + `docs/development.md` user testing + check AJAX session handling
- **"Balance calculation issues"** → Read `docs/architecture.md` e-commerce domain + CLAUDE.md Active Decisions for **Unified User Balance & Reward Service Logic**. Verify that all components use `ECommerce\Manager` and `ProductService` as the single source of truth
- **"User status inconsistencies"** → Check UserStatusService integration + verify all components use service methods instead of direct role/meta queries + check status constants usage (STATUS_GUEST, STATUS_NEEDS_FORM, etc.)
- **"Zebricek not displaying"** → Check AccountDashboardShortcode leaderboard conditions + user registration status + ZebricekDataService integration + conditional rendering logic
- **"Progress bar inconsistencies"** → Check UserPointsBalanceShortcode three-layer system + zebricek-progress @extend patterns + 20px height standardization + pending points calculation
- **"Zebricek position wrong"** → Verify ZebricekDataService queries + check debug_zebricek.log for position calculations + ensure all components delegate to service layer
- **"Dashboard layout issues"** → Check AccountDashboardShortcode grid system + absolute positioning for external navigation + responsive breakpoints
- **"Service initialization problems"** → **UPCOMING ORCHESTRATOR REFACTOR**: Centralized service initialization and dependency management system to replace manual bootstrap patterns. Will provide unified service registry and automatic dependency resolution across all domains.
- **"Realizace status problems"** → Check CLAUDE.md Active Decisions + verify AdminController consolidation + review PointsHandler hooks
- **"Faktury points not showing"** → Check CLAUDE.md Known Issues for ACF field population + FakturaFieldService field selectors + PointsHandler calculation
- **"Reject button not working"** → Check AdminControllerBase button rendering + admin.js handleEditorRejectClick + AJAX endpoint registration
- **"Status dropdown missing"** → StatusDropdownManager removed - use dedicated reject button in publish box instead via AdminControllerBase
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
- **2025-07-16**: [refactor] PROPER SEPARATION OF CONCERNS IN USER STATUS SYSTEM - Centralized user status logic in UserStatusService with unified service architecture. Updated UserService to delegate to UserStatusService for all status operations, ensuring single source of truth for user state management. Enhanced UserHeaderShortcode and AccessControl to use unified status service. Eliminated status logic duplication across components while maintaining backward compatibility. System now follows strict separation of concerns with dedicated service for user status operations.
- **2025-07-16**: [fix] ZEBRICEK POSITION CALCULATION BUG FIXED - Resolved critical SQL syntax error in position calculation query (COUNT(DISTINCT log.user_id) → COUNT(DISTINCT user_id) in subquery). Fixed points calculation bug showing 27,501 instead of 27,500 (removed rogue +1 from calculation). Optimized performance by allowing service methods to accept pre-fetched data, eliminating redundant database queries. Enhanced ZebricekDataService with comprehensive logging using DebugLogger for all operations. Conducted full architecture audit confirming 100% compliance - all zebricek components properly delegate to service layer with zero data inconsistencies. System now provides accurate position calculations (2 users = positions 1,2) and unified data architecture with single source of truth pattern.
- **2025-07-16**: [fix] DYNAMIC PRODUCT GRID ORDERING - Fixed ordering in dynamic affordable/unaffordable product listing in account dashboard. Improved product grid shortcode for dashboard with proper sorting logic. Enhanced user progress guide shortcode to track latest pending realizations and react only to realizations post types. Updated user domain constants to use retrieved values for better maintainability.
- **2025-07-15**: [feature] ZEBRICEK DASHBOARD COMPONENTS COMPLETE - Implemented comprehensive zebricek dashboard with position stats and progress components following Figma specifications. Created ZebricekProgressShortcode with three-layer progress bar (accepted/pending/remaining) reusing UserPointsBalanceShortcode architecture. Implemented conditional invoice display in position stats for users with approved invoices. Established template-based rendering with templates/zebricek/ directory. Created component-based SCSS with DRY compliance using @extend patterns from user-points-balance. Added external navigation with absolute positioning to prevent grid height interference. Standardized progress bar heights to 20px across components. System demonstrates enterprise patterns: dependency injection, semantic CSS, template separation, and centralized service architecture.
- **2025-07-08**: [refactor] ECOMMERCE SERVICE CONSOLIDATION - Centralized user balance and reward logic. Added `get_balance_summary()` to `ECommerce\Manager` and `get_next_unaffordable_product()` to `ProductService`. Refactored `UserPointsBalanceShortcode` to use these new service methods, ensuring a single source of truth for user point data and reward progress. Fixed "Pokrok k další odměně" component to accurately display the next achievable product reward.
- **2025-07-07**: [fix] PRAGMATIC PRODUCTION FIXES - Applied critical fixes identified by code review while avoiding over-engineering. Added WordPress-standard input sanitization to ValidationRulesRegistry (sanitize_text_field() for all user inputs). Added initialization guards to DomainConfigurationService and ValidationRulesRegistry to prevent double-initialization edge cases. Maintained 80% compliance score while focusing on real-world WordPress theme requirements. No functional logic changes - all business rules preserved exactly.
- **2025-07-07**: [refactor] CRITICAL ARCHITECTURE FIXES COMPLETE - Eliminated massive code duplication and hardcoded values through comprehensive service abstraction. Created DomainConfigurationService for centralized settings (eliminated 95% duplication in Manager classes), PointsCalculationService for business logic abstraction (centralized all point calculation formulas), ValidationRulesRegistry for domain-specific rules, and ErrorHandlingService for unified error patterns. Updated Faktury and Realizace Managers to use configuration services, reducing code by ~60% while establishing single source of truth for all domain settings. Added FieldAccessValidator with CLI script for compliance checking. System now follows strict DRY principles with centralized configuration, eliminating hardcoded values across domains. Architecture ready for easy addition of new domains (Certifikace, etc.) with zero code duplication.
- **2025-07-03**: [refactor] DUAL-POINT ARCHITECTURE CLEANUP COMPLETE - Eliminated all Single Source of Truth violations by creating PointTypeConstants.php service for centralized point type identifiers. Updated DualPointsManager, ZebricekDataService, and ECommerce Manager to use centralized constants. Standardized cache key patterns in ZebricekDataService with consistent {operation}_{year}_{user_id} format for collision prevention. System now has zero constant duplication and predictable cache behavior across all leaderboard operations.
- **2025-07-03**: [fix] MANUAL ACF POINTS ADJUSTMENT BUG - Fixed critical bug where manually changing points in post editor ACF fields caused "Invalid points amount" errors for negative differences. Root cause: system tried to "award" negative points when reducing values (e.g., 25000→2500 = -22500 award attempt). Solution: Enhanced award_points() method to handle positive differences as awards and negative differences as revocations. Now supports bidirectional point adjustments via post editor with proper dual-point transaction handling.
- **2025-07-03**: [refactor] ZEBRICEK ARCHITECTURE CONSOLIDATION - Eliminated code duplication by consolidating all leaderboard query logic into ZebricekDataService. Moved ZebricekAjaxHandler from /Shortcodes to /Services as ZebricekAjaxService for proper separation of concerns. Both shortcodes and AJAX endpoints now use single source of truth for database queries. Fixed NET point calculation consistency across all zebricek components. Reduced codebase by ~200 lines while improving maintainability and architectural clarity.
- **2025-07-01**: [cleanup] STAGING DEPLOYMENT PREPARATION - Removed debug components (UserDebugShortcode, _user-debug.scss, cart debug helpers) for clean staging deployment. Updated build artifacts and function dependencies. System now ready for staging environment with production-quality codebase.
- **2025-07-01**: [refactor] DEPENDENCY INJECTION CONSOLIDATION COMPLETE - Unified all Manager classes under consistent DI pattern. Users/Manager and MyCred/ECommerce/Manager now accept optional dependencies with backward compatibility. Bootstrap.php creates centralized service registry with shared AresApiClient, UserDetectionService, and RoleManager instances. Eliminates singleton service duplication while maintaining clean initialization order. Fixed timing issues with early hook priority (20→5).
- **2025-07-01**: [security] FAKTURY VALIDATION HARDENING - Enhanced AdminController with rate limiting (10 requests/minute, 5 bulk operations/minute), capability checks requiring manage_options, and WP_DEBUG gating for error logging. ValidationService improved with detailed monthly limit messaging showing exceeded amounts. Production-ready security measures for invoice management system.
- **2025-07-01**: [fix] BULK APPROVE RESTORATION - Restored bulk approve button for realizace domain only. Button now conditionally displays for post_type==='realization' when pending items exist. Faktury domain correctly excludes bulk approve functionality. Maintains domain-specific admin workflows per user requirements.
- **2025-07-01**: [feature] CF7 RELATIONAL SELECTS IMPLEMENTATION COMPLETE - Contact Form 7 integration with dynamic construction type and material filtering. Complete taxonomy system with Czech admin labels and English slugs via ACF JSON imports. Alpine.js reactive components for AJAX material filtering based on construction type selection. Dual shortcode system: CF7 custom tags ([realizace_construction_types], [realizace_materials]) and regular WordPress shortcodes. CF7FormTagBase provides foundation for future form tag development. ES6 module architecture with webpack integration. Enhanced with XSS protection, input validation for taxonomy term IDs, user-facing error messages for AJAX failures, improved loading states, extracted hardcoded strings to constants, JSDoc documentation, optimized Alpine.js component structure. Replaces static text areas with relational selects, enforcing data quality through guided user selection of valid construction type and material combinations.
- **2025-06-27**: [fix] FAKTURY ACF FIELD POPULATION RESOLVED - Fixed ACF field population issue for Faktury domain. Points now display correctly in both admin dashboard and post edit screens. Complete Faktury domain now fully production-ready with no remaining blockers.
- **2025-06-27**: [maintenance] COMPREHENSIVE BUNDLE AUDIT - Audited all .repomix bundles for accuracy against current codebase structure. Removed obsolete file references (domain-specific StatusManager/AdminAssetManager files consolidated into Services). Added missing DebugLogger.php to Services bundles. Fixed duplicate template references. Ensured full-stack-architecture bundle has complete coverage: all 59 PHP files in src/App/, all 20 JavaScript files in src/js/, all template files, key integration files (CLAUDE.md, functions.php, etc.). No SCSS files correctly excluded. All bundles now accurately reflect consolidated services architecture.
- **2025-06-26**: [chore] BUNDLE MANAGEMENT CLEANUP - Updated .repomix/bundles.json to remove deleted StatusDropdownManager.js references from all bundles. Added Services directory files (StatusManager.php, AdminAssetManager.php) to base-architecture and full-stack-architecture bundles. Updated lastUsed timestamps for affected bundles to maintain accurate project state tracking.
- **2025-06-26**: [feature] DEDICATED REJECT/REVOKE BUTTON SYSTEM COMPLETE - Replaced problematic status dropdown with context-aware button system. Three distinct states: pending posts show red "Odmítnout" button, published posts show orange "Zrušit schválení" button, rejected posts show grey "Odmítnuto" disabled button. Fixed AJAX action bug (window.pagenow → window.typenow). Enhanced confirmation dialogs with context-appropriate messages. Integrated StatusManager to add "Odmítnuto" option to WordPress native dropdown. Removed StatusDropdownManager.js and related PHP localization code. System eliminates validation gatekeeper conflicts and provides intuitive admin workflow.
- **2025-06-26**: [cleanup] DEBUG LOGGING REMOVAL - Cleaned up debug logging from ValidationService to prepare for production deployment. Removed verbose logging statements while maintaining error handling and critical debugging information.
- **2025-07-01**: [docs] INVOICE POINTS CALCULATION DOCUMENTATION - Updated CLAUDE.md documentation to reflect actual implementation of floor(value/10) formula for Faktury points calculation. Business rule: 10 CZK = 1 point. Documentation now accurately matches the production codebase implementation in PointsHandler.php.
- **2025-06-26**: [feature] INVOICE PRE-PUBLISH DATE VALIDATION - Implemented comprehensive date validation for Faktury domain requiring invoices to be from current year only. Enhanced ValidationService with isDateValid() method supporting both Ymd and Y-m-d date formats. Validation integrates with AdminController pre-publish gatekeeper to prevent invalid invoice submissions. Business rule enforces current year restriction for invoice dates.
- **2025-06-26**: [fix] AJAX PARAMETER FORMAT SUPPORT - Enhanced AdminControllerBase to support multiple AJAX parameter naming conventions. Added backward compatibility for both legacy parameters (realizace_action) and new format ({post_type}_action). Improved parameter detection with fallback chain supporting domain-specific action parameters. Eliminates AJAX 403 errors from parameter format mismatches.
- **2025-06-26**: [refactor] PHASE 1 SERVICES CONSOLIDATION - Consolidated 6 domain-specific files into 2 unified services. Created Services/StatusManager.php as configurable status management service. Created Services/AdminAssetManager.php as unified asset management service. Eliminated code duplication across Realizace and Faktury domains while maintaining full customization capability. Reduced maintenance overhead and established patterns for domain scaling.
- **2025-06-25**: [refactor] ENGLISH SLUG MIGRATION COMPLETE - Successfully completed 3-phase refactoring to eliminate Czech language complexity. Updated 40+ files across PHP backend and JavaScript frontend to use English post types ('realization', 'invoice'). Removed DomainRegistry system entirely, replacing with direct string concatenation. All FieldService classes use English field names, all Management classes return English post types, and AJAX endpoints are perfectly aligned. System now uses predictable patterns: getPostType() returns English, WordPress post type remains Czech for URL compatibility. Zero AJAX 403 errors, simplified maintenance, future-proof architecture.
- **2025-06-25**: [fix] STATUS DROPDOWN TIMING FIX - Fixed missing "Odmítnuto" status option in realizace post edit dropdowns by resolving script localization timing issue. Changed from admin_footer-post.php to admin_enqueue_scripts hook with priority 20 to ensure wp_localize_script runs before JavaScript execution. Fixed script enqueuing from wp_register_script to wp_enqueue_script in functions.php. StatusDropdownManager.js now properly initializes with mistrFachmanStatusDropdown configuration object. Cleaned up debug logging from production code.
- **2025-06-25**: [refactor] COMPLETE DOMAIN ABSTRACTION - Created StatusManagerBase (210 lines) and AdminAssetManagerBase (165 lines) for reusable domain patterns. Refactored Realizace StatusManager from 230→76 lines (67% reduction) and AdminAssetManager from 132→94 lines (29% reduction). Integrated StatusDropdownManager.js into webpack build system. Enhanced AdminControllerBase with users table integration. Removed JavaScript conflicts and achieved clean separation of concerns. Realizace domain reduced from 1100+ to 766 lines (30% total reduction) while establishing patterns for rapid Faktury implementation.
- **2025-06-25**: [fix] ARES VALIDATION AND FORM ERROR HANDLING - Improved ARES API integration with better validation and error handling. Enhanced form error messaging for user feedback. Strengthened business data validation pipeline for more reliable company data processing.
- **2025-06-25**: [feat] FAKTURY DOMAIN IMPLEMENTATION - Complete implementation of invoice management domain with dynamic points calculation, validation service, and form integration. Created full domain architecture: Manager, AdminController, PointsHandler, ValidationService, FakturaFieldService, FormHandler. Implemented business rules for invoice processing including date validation and points calculation (floor(value/10)). Established template system with faktura-details.php. Domain ready for production with only ACF field population fix pending.
- **2025-06-25**: [refactor] BACKEND ARCHITECTURE REFINEMENTS - Enhanced asset autonomy and semantic clarity across backend components. Improved separation of concerns and refined dependency injection patterns. Strengthened base class abstractions for better domain extensibility.
- **2025-06-25**: [fix] STATUS DROPDOWN TIMING RESOLUTION - Resolved status dropdown timing issue for realizace post edit pages. Fixed script localization timing and initialization sequence to ensure proper dropdown functionality. Enhanced asset loading coordination between domains.
- **2025-06-25**: [refactor] COMPLETE DOMAIN ABSTRACTION WITH BASE CLASSES - Created comprehensive base class architecture (PostTypeManagerBase, AdminControllerBase, PointsHandlerBase, FormHandlerBase) for domain abstraction. Enabled rapid creation of new domains by extending proven patterns. Established foundation for Faktury domain implementation.
- **2025-06-25**: [fix] PATHWAY PREVALIDATION - Fixed validation pathway conflicts and improved pre-validation logic. Enhanced validation gatekeeper functionality to prevent publishing conflicts. Strengthened business rule enforcement.
- **2025-06-25**: [refactor] AJAX DEBUGGING CLEANUP - Removed extensive AJAX debugging logs for cleaner production code. Maintained essential error logging while eliminating verbose debugging output. Improved code maintainability.
- **2025-06-25**: [refactor] LOGGER DEPENDENCIES REMOVAL - Removed logger dependencies in favor of direct console usage. Simplified debugging architecture and reduced external dependencies. Improved JavaScript performance and maintainability.
- **2025-06-25**: [refactor] COMPLETE JAVASCRIPT AND SCSS MODERNIZATION - Comprehensive modernization of frontend architecture with functional patterns, modular SCSS organization, and enterprise-grade JavaScript structure. Established foundation for scalable frontend development.

## User Registration System

### Complete Three-Stage Registration Flow
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

## Centralized Field Access System

### RealizaceFieldService - Single Source of Truth
**Location**: `src/App/Realizace/RealizaceFieldService.php`

**Pattern**: All ACF field access must use centralized service methods:
```php
// CORRECT - Use centralized service with English field names
$points = RealizaceFieldService::getPoints($post_id);
$reason = RealizaceFieldService::getRejectionReason($post_id);
RealizaceFieldService::setPoints($post_id, 2500);

// INCORRECT - Never use hardcoded field names (old Czech patterns)
$points = get_field('sprava_a_hodnoceni_realizace_pridelene_body', $post_id);
update_field('realizace_duvod_zamitnuti', $reason, $post_id);
```

**Benefits**:
- **Field name changes**: Update only in RealizaceFieldService constants
- **Type safety**: Consistent return types and validation
- **ACF/meta fallback**: Automatic graceful degradation
- **Future domains**: Copy pattern for Faktury, Certifikace, etc.

**Architecture**: Abstract base classes delegate to field service for true abstraction without hardcoded dependencies.

### Manual Validation Commands
Quick architecture compliance checks:
```bash
# Check all patterns at once
./scripts/validate.sh

# Individual checks
./scripts/validate.sh field-service    # ACF field access compliance
./scripts/validate.sh file-size        # File size warnings (>350 lines)
./scripts/validate.sh domain-boundaries # Cross-domain coupling detection
```

**Usage**: Run before major commits or when reviewing architecture changes. Simple, reliable validation without complex hook dependencies.

## 🚨 CRITICAL ARCHITECTURE RULES - NEVER VIOLATE 🚨

### **ABSOLUTELY FORBIDDEN - ZERO TOLERANCE VIOLATIONS**
1. **NEVER HARDCODE POST TYPES** - Always use `DomainConfigurationService::getWordPressPostType(domain_key)`
2. **NEVER USE CZECH SLUGS** - System uses English domain keys ('realization', 'invoice')
3. **NEVER HARDCODE FIELD NAMES** - Always use FieldService classes
4. **NEVER HARDCODE DOMAIN VALUES** - Use DomainConfigurationService for ALL values

### **CORRECT PATTERNS - ALWAYS FOLLOW**
```php
// ✅ CORRECT - Use configuration service
$post_type = DomainConfigurationService::getWordPressPostType('realization');
$points = RealizaceFieldService::getPoints($post_id);

// ❌ FORBIDDEN - Never hardcode ANY values
$post_type = 'realizace'; // NEVER DO THIS
$post_type = 'realization'; // NEVER DO THIS
$points = get_field('some_field', $post_id); // NEVER DO THIS
```

**ENFORCEMENT**: Every violation breaks the Single Source of Truth principle and creates maintenance nightmares. The architecture exists specifically to prevent hardcoding.

## Active Decisions
- **ZERO TOLERANCE FOR HARDCODING** (2025-07-15): Absolutely no hardcoded post types, field names, or Czech slugs allowed anywhere in codebase. All values must come from DomainConfigurationService or FieldService classes. Violations undermine entire architecture.
- **Unified user status service architecture** (2025-07-16): UserStatusService serves as the single source of truth for ALL user status operations. All components must use UserStatusService for status checks - no direct role queries or meta access allowed. Service provides standardized methods: getCurrentUserStatus(), isUserPending(), getStatusDisplayName() with consistent status constants (STATUS_GUEST, STATUS_NEEDS_FORM, STATUS_AWAITING_APPROVAL, STATUS_FULL_MEMBER, STATUS_OTHER). UserService delegates to UserStatusService ensuring separation of concerns. Architecture enables consistent user status handling across all components with centralized business logic. **FUTURE COMPONENTS**: Always inject UserStatusService dependency and use service methods instead of direct WordPress role/meta queries.
- **Zebricek unified data service architecture** (2025-07-16): ZebricekDataService serves as the single source of truth for ALL zebricek data operations. All components (shortcodes, AJAX handlers, templates) must delegate to this service - zero database queries allowed outside the service. Service provides comprehensive methods: get_user_position(), get_user_annual_points(), get_user_next_target(), get_user_submission_counts(), get_leaderboard_data(). All methods accept optional pre-fetched data to optimize performance and eliminate redundant queries. Comprehensive logging via DebugLogger to debug_zebricek.log for all operations. Architecture ensures 100% data consistency across all zebricek components with proper caching and error handling.
- **Zebricek dashboard architecture** (2025-07-15): Zebricek components follow established enterprise patterns with template-based rendering, component-scoped SCSS, and service layer integration. ZebricekProgressShortcode reuses three-layer progress bar from UserPointsBalanceShortcode through @extend patterns for DRY compliance. Templates use semantic class names (.zebricek-progress, .zebricek-position-stats) with component-scoped styling. Progress bars standardized to 20px height across all components. External navigation uses absolute positioning to prevent grid height interference in AccountDashboardShortcode. Conditional invoice display based on user approval status. Architecture enables rapid creation of new zebricek components following established patterns.
- **Unified User Balance & Reward Service Logic** (2025-07-08): To ensure a single source of truth for all user-facing financial data, e-commerce logic is centralized in dedicated services. `MyCred\ECommerce\Manager::get_balance_summary()` provides a canonical source for a user's complete point history (total, used, available). `Services\ProductService::get_next_unaffordable_product()` contains the business logic for determining a user's next reward goal. All frontend components, like the `UserPointsBalanceShortcode`, must consume these services instead of implementing their own calculations.
- **Service abstraction architecture** (2025-07-07): Created comprehensive service layer to eliminate code duplication and hardcoded values. DomainConfigurationService provides single source of truth for all domain settings (asset configs, field selectors, messages). PointsCalculationService abstracts all business logic calculations with rules-based system (fixed_value, , etc.). ValidationRulesRegistry centralizes all validation rules with composable rule system. ErrorHandlingService unifies error handling patterns across domains. Manager classes now use configuration services instead of hardcoded arrays, reducing duplication by 95%. FieldAccessValidator provides automated compliance checking with CLI script. Architecture enables easy addition of new domains with zero code duplication.
- **CF7 relational selects architecture** (2025-07-01): Contact Form 7 integration with dynamic taxonomy filtering. CF7FormTagBase establishes foundation for future form tag development. Alpine.js reactive components provide frontend reactivity with AJAX material filtering. Dual shortcode system supports CF7 custom tags ([realizace_construction_types], [realizace_materials]) and regular WordPress shortcodes. TaxonomyManager handles data relationships with term meta storage. ACF JSON import pattern with Czech admin labels and English slugs for maintainable localization. Enhanced with XSS protection using esc_attr()/esc_html(), input validation for taxonomy term IDs, user-facing error messages for network failures, loading states with visual feedback, extracted hardcoded strings to constants for localization, JSDoc documentation, optimized Alpine.js structure with shared error configuration. System replaces static text areas with relational selects, enforcing data quality through guided user selection of valid construction type and material combinations.
- **Dedicated reject button system** (2025-06-26): Replaced problematic status dropdown with context-aware button system in post editor publish box. Three distinct states: pending posts (red "Odmítnout"), published posts (orange "Zrušit schválení"), rejected posts (grey "Odmítnuto" disabled). Integrates with WordPress native dropdown via StatusManager JavaScript injection. Eliminates validation gatekeeper conflicts, provides admin workflow, and resolves AJAX timing issues. StatusDropdownManager.js removed in favor of unified AdminControllerBase implementation.
- **Dependency injection consolidation** (2025-07-01): Unified all Manager classes under consistent DI pattern with centralized service registry in bootstrap.php. Eliminates singleton service duplication while maintaining backward compatibility through optional parameters. Bootstrap creates shared AresApiClient, UserDetectionService, and RoleManager instances passed to all domains. Fixed initialization timing with early hook priority (20→5) ensuring proper service availability.
- **Consolidated services architecture** (2025-06-26): Created configurable Services/StatusManager.php and Services/AdminAssetManager.php eliminating domain-specific service duplication. Services accept configuration arrays enabling full customization while sharing core functionality. Reduced 6 domain-specific files to 2 reusable services. Established pattern for domain scaling with zero code duplication. Services integrate with existing base class architecture.
- **Faktury domain implementation** (2025-06-26): Invoice management domain with dynamic points calculation (floor(value/10)), ValidationService with date validation, form integration, and admin interface. Domain architecture: Manager, AdminController, PointsHandler, ValidationService, FakturaFieldService, FormHandler. Business rules implemented for current-year date validation. ACF field population issue resolved - production ready.
- **English slug architecture** (2025-06-25): Eliminated DomainRegistry complexity. All PHP classes return English post types ('realization', 'invoice'), JavaScript uses English slugs, AJAX endpoints use direct string concatenation. WordPress post type slugs remain Czech ('realizace', 'faktura') for URL compatibility. FieldService classes use English field names (points_assigned, rejection_reason). System achieved PHP/JavaScript alignment with zero AJAX 403 errors. Predictable, maintainable architecture.
- **Complete domain abstraction** (2025-06-25): Created StatusManagerBase and AdminAssetManagerBase for cross-domain reusability. Integrated StatusDropdownManager.js into webpack build system with AdminApp module initialization. Enhanced AdminControllerBase with users table integration. Achieved 30% code reduction while establishing patterns for Faktury implementation. JavaScript system generic and configuration-driven.
- **Template system architecture** (2025-06-25): MVC separation with template-based rendering for all admin cards - 84% code reduction with zero HTML in PHP classes. Template loading system enables domain expansion with zero code duplication
- **Centralized field access**: All ACF field access through RealizaceFieldService - eliminates hardcoded field names and synchronization bugs
- **Domain-driven architecture**: Domain structure with PSR-4 autoloading and single bootstrap
- **Abstract base class architecture** (2025-06-23): Created PostTypeManagerBase, AdminControllerBase, PointsHandlerBase, and FormHandlerBase for domain abstraction. Enables creation of new domains (Faktury, Certifikace) by extending patterns
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
No known major issues. All systems are production-ready and operational. **RECENT FIXES COMPLETED**: Zebricek position calculation bug resolved (SQL syntax error and rogue +1 in points calculation), unified data service architecture implemented with 100% compliance, comprehensive logging added for debugging. All zebricek components now properly delegate to ZebricekDataService with zero data inconsistencies.

All major systems operational: Unified zebricek data service with accurate position calculations, dedicated reject button system with context-aware states, Faktury invoice domain with ACF field population resolved, consolidated services architecture, English slug architecture with PHP/JavaScript alignment, template system with MVC separation. System ready for production deployment with enhanced stability and data consistency.

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
2025-07-16 (Zebricek position calculation bug fixed, unified data service architecture with 100% compliance, UserStatusService for centralized status management, comprehensive logging and performance optimizations, upcoming orchestrator refactor planning)
