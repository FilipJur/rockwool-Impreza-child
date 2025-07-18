# Development Patterns & Guidelines

This document outlines the development patterns, coding standards, and implementation guidelines for the project. Synchronized with .agent.md and CLAUDE.md for comprehensive living memory.

## Modern JavaScript Architecture

### Dual JavaScript Structure
- **Frontend**: `src/js/main.js` - ThemeApp class for public-facing functionality
- **Admin**: `src/js/admin.js` - AdminApp class for WordPress admin interface functionality
- **Clean Separation**: Admin functionality completely separate from frontend code
- **Event-Driven Architecture**: Global event delegation for admin interactions

### Core Principles
- **Functions over classes**: Use classes only for stateful components, functions for utilities
- **ES6 modules**: Clean import/export patterns with object exports for utilities
- **Functional approach**: Most features are implemented as setup functions returning control objects
- **Minimal state management**: Only `FileUpload` uses class pattern (singleton + complex state)
- **Proper cleanup**: Functions return cleanup methods, classes implement destroy/cleanup

### Current Implementation Patterns

#### Utility Objects
- **api** and **validation**: Object exports with utility functions
- Pure functions for reusable logic
- No state management in utilities

#### Functional Setup Pattern
- **setupAresForm()**: Functional setup returning control object with cleanup
- **setupPreviewObserver()**: Functional DOM observer with proper cleanup
- Returns control objects with cleanup methods

#### Animation Utilities
- **Pure functions**: Staggered animations without view transitions
- **CSS-based**: Use CSS animations instead of View Transitions API
- **Performance-focused**: Minimal JavaScript animation logic

#### Singleton Pattern (Limited Use)
- **FileUpload**: Class for complex state management (legitimate singleton pattern)
- Only used when state complexity justifies the pattern
- Implements proper cleanup and destroy methods

#### Admin App Architecture
- **AdminApp Class**: Manages admin-specific module initialization and lifecycle
- **Reject Button System**: Centralized handling of reject/revoke button interactions
  - Context-aware confirmation messages
  - AJAX error recovery and button state management
  - Proper form data handling with nonce security
- **Module Conditional Loading**: Modules loaded based on DOM elements and admin context

## WordPress Integration Standards

### Hook Usage
- Uses WordPress action hooks for proper initialization timing
- Conditional loading based on plugin availability
- Follows WordPress coding standards
- Proper enqueuing of scripts and styles

### Plugin Compatibility
- Defensive programming for plugin availability
- Graceful degradation when plugins unavailable
- Clean error handling and fallbacks

## User Registration Development Patterns

### Working with Users Domain
- **Always use Manager singleton**: `\MistrFachman\Users\Manager::get_instance()`
- **Status checks via UserService**: `$user_service->get_user_registration_status($user_id)`
- **Role checks via RoleManager**: `$role_manager->is_pending_user($user_id)`
- **Never direct user meta access**: Use service layer for all user data operations

### Adding User Features
1. **Business logic**: Add to appropriate domain class (Manager, RoleManager, etc.)
2. **Status/permission checks**: Update UserService methods
3. **WordPress integration**: Add hooks in RegistrationHooks if needed
4. **Test user states**: logged out, pending_approval, full_member, awaiting_review

### Testing User Registration
- **⚠️ Incognito Mode**: May cause session issues - test in regular browser tabs
- **✅ Cookie Fallback**: System handles AJAX session loss automatically
- **✅ Role Verification**: System blocks purchases for pending users
- **Form Testing**: Use Contact Form 7 ID 292 for final registration

### Debugging User Issues
- **Check logs**: Look for `[USERS:*]` entries in debug logs
- **Verify user meta**: Check `_mistr_fachman_registration_status` meta key
- **Confirm roles**: Ensure proper `pending_approval` vs `full_member` assignment
- **AJAX testing**: Test CF7 submissions in non-incognito browsers

## PHP Architecture Patterns

### PSR-4 Autoloading
- **Namespace mapping**: `MistrFachman\` → `src/App/`
- **Modern class loading**: Composer autoloader replaces manual require_once
- **Optimized class mapping**: Efficient autoloading for all classes

### Base Class Architecture
- **AdminControllerBase**: Foundation for all domain admin interfaces
  - Reject/revoke button system implementation
  - Pre-publish validation gatekeeper
  - User profile card integration
  - Custom admin columns and AJAX endpoints
- **PointsHandlerBase**: Dual-pathway points management (ACF save + AJAX direct)
- **FieldServiceBase**: Consistent ACF field access with meta fallback
- **AdminCardRendererBase**: Template-based rendering with MVC separation

### Dependency Injection
- **Constructor injection**: Services injected, never created within components
- **Service location**: Avoid service locator anti-pattern
- **Clean dependencies**: Clear dependency graphs
- **Services Directory**: StatusManager and AdminAssetManager provide reusable patterns

### Template System
- **Complete MVC Separation**: Zero HTML generation in PHP classes
- **Template Directory**: `templates/admin-cards/` with 8 reusable components
- **Data Preparation**: AdminCardRendererBase provides clean data arrays to templates
- **84% Code Reduction**: From monolithic renderers to lean data providers

## Code Quality Standards

### Complexity Management
- **Eliminate unnecessary abstraction**: Remove methods that don't add value
- **Simplify administrative features**: Avoid complex admin systems without business value
- **Method threshold**: Files >200 lines suggest splitting opportunities

### Styling Separation
- **NO INLINE STYLING IN SHORTCODES**: Functional components only
- **Semantic CSS naming**: Context classes for targeted styling (e.g., `filter-affordable`)
- **SCSS organization**: Base, layout, components, utilities structure

### Performance Optimization
- **Single Source of Truth**: One calculation point for critical data
- **Context-aware logic**: Different behavior for different page contexts
- **Efficient queries**: Minimize database calls and API requests

## Testing & Validation

### Service Layer Benefits
- **Easy mocking**: Service layer enables unit testing
- **Clean interfaces**: Clear contracts between layers
- **Isolated testing**: Components can be tested independently
- **Cross-domain integration**: Users and E-Commerce domains communicate through services
- **Session handling**: UserDetectionService provides robust user identification with fallbacks

### Error Handling
- **Graceful degradation**: System continues working with reduced functionality
- **User feedback**: Clear error messages in appropriate language (Czech for user-facing)
- **Defensive programming**: Check for plugin/service availability
- **Registration flow resilience**: Cookie fallback prevents user loss during AJAX submissions
- **Role-based fallbacks**: Pending users get appropriate messaging instead of errors

## Admin Interface Development

### Reject/Revoke Button System
- **Three-State Logic**: Pending (red reject), Published (orange revoke), Rejected (grey disabled)
- **Context-Aware UX**: Different confirmation messages and button behaviors per state
- **AJAX Implementation**: Dedicated endpoints with proper nonce validation
- **WordPress Integration**: StatusManager adds custom status to native dropdown
- **Error Recovery**: Button state management with proper error handling

### Template Development
- **File Structure**: `templates/admin-cards/` directory with component separation
- **Data Flow**: PHP classes prepare data arrays, templates handle presentation
- **Reusability**: Templates designed for cross-domain usage (Realizace, Faktury)
- **Domain Details**: Separate `domain-details/` subdirectory for domain-specific templates

### Troubleshooting Admin Features
- **AJAX Errors**: Check nonce validation and proper action registration
- **Button States**: Verify post status and proper JavaScript event handling
- **Template Issues**: Ensure template files exist and data arrays are properly structured
- **Field Access**: Use FieldService classes, never hardcode ACF field selectors

## Development Workflow

### New Domain Implementation
1. **Extend Base Classes**: Use AdminControllerBase, PointsHandlerBase, FieldServiceBase
2. **Create Field Service**: Centralized ACF field access with English field names
3. **Configure Services**: StatusManager and AdminAssetManager with domain-specific settings
4. **Create Templates**: Domain-specific detail template in `templates/admin-cards/domain-details/`
5. **Test Reject System**: Verify three-state button behavior and AJAX endpoints

### Field Access Pattern
```php
// CORRECT - Use centralized field service
$points = RealizaceFieldService::getPoints($post_id);
$reason = FakturaFieldService::getRejectionReason($post_id);

// INCORRECT - Never hardcode field selectors
$points = get_field('realizace_pridelene_body', $post_id);
```

### Known Issues & Solutions
- **ACF Grouped Fields**: Use proper field selectors or add ACF load_value hooks
- **AJAX Action Bugs**: Ensure correct typenow/pagenow usage in JavaScript
- **Template Errors**: Verify template file paths and data structure
- **Field Synchronization**: Always use FieldService classes for consistency