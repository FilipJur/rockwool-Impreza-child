# Feature Specifications

This document details the implemented features and their technical specifications. Works hand-in-hand with .agent.md and CLAUDE.md for living memory management.

## User Registration System ✅ PRODUCTION READY

### Three-Stage Registration Flow
- **Stage 1**: Guest user enters phone number → SMS OTP verification → becomes `pending_approval` user
- **Stage 2**: User fills Contact Form 7 (ID: 292) → status changes to `awaiting_review`
- **Stage 3**: Admin manually approves → user promoted to `full_member` role

### Technical Implementation
- **SMS OTP Integration**: WordPress user registration with phone verification
- **Contact Form 7 Integration**: Form ID 292 with AJAX session handling and cookie fallback
- **Role Management**: Custom roles `pending_approval` and `full_member` with permission checking
- **Admin Interface**: WordPress admin integration for user approval workflow
- **Session Handling**: Cookie parsing fallback for AJAX submissions that lose session context

### Critical Features
- **AJAX Session Recovery**: Cookie fallback prevents user loss during CF7 AJAX submissions
- **Role-Based E-Commerce**: `pending_approval` users blocked from purchases until approved
- **Status Validation**: Centralized status checking prevents unauthorized access
- **Admin Workflow**: Simple one-click user promotion with secure nonce validation

### Integration Points
- **E-Commerce Domain**: Role checking integrated with cart and checkout validation
- **Shortcode System**: User status displayed in UserHeaderShortcode and UserDebugShortcode
- **WordPress Core**: Seamless integration with WordPress user system and capabilities

## File Upload Enhancement

### Core Functionality
- Drag-and-drop interface with visual feedback
- Image preview generation and thumbnail handling
- Staggered arrival animations for file previews
- Integration with WordPress file upload plugins
- Simplified animation system without View Transitions API

### Technical Implementation
- **FileUpload class**: Singleton pattern for complex state management
- **Animation Manager**: Pure functions for staggered animations
- **Preview Manager**: Image preview and thumbnail generation
- **Removal Handler**: File removal functionality with cleanup
- **Constants**: Configuration and settings management

### File Structure
```
src/js/features/file-upload/
├── index.js              # Main FileUpload class
├── animation-manager.js  # Staggered animation utilities
├── preview-manager.js    # Image preview handling
├── thumbnail-handler.js  # Thumbnail generation
├── removal-handler.js    # File removal functionality
└── constants.js          # Configuration constants
```

## myCred WooCommerce Integration

### Core Features
- **Cart-aware balance calculation**: available = balance - cart_total (Single Source of Truth)
- **Context-aware purchasability**: Different logic for shop vs cart/checkout/API pages to prevent double-counting
- **WooCommerce compatibility**: Handles both traditional and block-based checkout via REST API detection
- **Performance optimized**: Replaced 40+ individual product checks with single calculation
- **Enhanced validation**: Multi-level validation at display, add-to-cart, and checkout stages

### Architecture
- **Modular design**: CartContext → AvailableBalanceCalculator → Purchasability dependency chain
- **Single Source of Truth**: One calculation point for available balance
- **Context awareness**: Different behavior for shop vs cart pages
- **REST API detection**: Automatic detection of block-based checkout

### User Experience
- Czech language UI messages
- Context-aware messaging (cart vs balance scenarios)
- Real-time balance updates
- Clear affordability indicators
- **Role-Based Access**: Pending users see registration prompts instead of purchase options

## ARES Integration

### Functionality
- Czech company registry lookup
- Auto-completion of company information in forms
- Error handling and validation
- Real-time data fetching

### Technical Details
- **Handler.js**: Main ARES integration logic
- **API Integration**: Direct connection to Czech business registry
- **Form Enhancement**: Automatic field population
- **Error Management**: Comprehensive error handling and user feedback

## Realizace Domain (Project Submissions) ✅ PRODUCTION READY

### Core Functionality
- **Project Submission**: Contact Form 7 integration for project submissions
- **Fixed Points System**: 2500 points per approved realizace with admin override capability
- **Admin Interface**: Complete approval/rejection workflow with user-friendly admin cards
- **Template System**: MVC-separated rendering with reusable template components

### Admin Features
- **Reject/Revoke Button System**: Context-aware buttons replace status dropdown
  - Pending: Red "Odmítnout" button
  - Published: Orange "Zrušit schválení" button  
  - Rejected: Grey "Odmítnuto" disabled state
- **User Profile Integration**: Admin cards show in user edit screen
- **Bulk Operations**: Bulk approve functionality with automatic point awards
- **Custom Columns**: Points and status columns in admin list view

### Technical Implementation
- **AdminController**: Unified admin interface with AJAX endpoints
- **PointsHandler**: Dual-pathway architecture (ACF save + AJAX direct)
- **RealizaceFieldService**: Centralized ACF field access with English field names
- **RealizaceCardRenderer**: Template-based rendering with zero HTML in PHP
- **No Debt Policy**: Point revocation respects user balance limits

### Field Access Pattern
```php
// CORRECT - Centralized field access
$points = RealizaceFieldService::getPoints($post_id);
$reason = RealizaceFieldService::getRejectionReason($post_id);
RealizaceFieldService::setPoints($post_id, 2500);
```

## Faktury Domain (Invoice Management) ✅ 95% COMPLETE

### Core Functionality
- **Invoice Submission**: Contact Form 7 integration for invoice submissions
- **Dynamic Points System**: Calculated as floor(invoice_value/30) with automatic calculation
- **Pre-Publish Validation**: Date validation requiring current year invoices
- **Admin Interface**: Complete approval/rejection workflow with admin cards

### Business Rules
- **Date Validation**: Invoices must be from current year only
- **Points Calculation**: floor(value/30) formula for points award
- **Monthly Limits**: Architecture prepared for 300,000 CZK monthly limit validation
- **Invoice Number Uniqueness**: Architecture prepared for per-user uniqueness validation

### Admin Features
- **Reject/Revoke Button System**: Same context-aware interface as Realizace
- **Validation Gatekeeper**: Pre-publish validation prevents invalid submissions  
- **User Profile Integration**: Admin cards show calculated points and invoice details
- **Template System**: Dedicated faktura-details.php template

### Technical Implementation
- **AdminController**: Admin interface with pre-publish validation
- **ValidationService**: Business rules validation (date validation implemented)
- **PointsHandler**: Dynamic points calculation with dual-pathway architecture
- **FakturaFieldService**: Centralized ACF field access for invoice fields
- **FakturyCardRenderer**: Template-based rendering for invoice admin cards

### Known Issues
- **ACF Field Population**: Points calculated correctly but ACF editor shows empty field
  - Root Cause: Grouped ACF field structure requires proper field selectors
  - Field Structure: Points field (field_668d4f7c1a2b5) nested in group field (field_668d4f7c1a2b4)
  - Current Status: Points calculated correctly in dashboard, empty in ACF editor
  - Solution: Update FakturaFieldService field selectors or add ACF load_value hook

## Admin Interface System ✅ PRODUCTION READY

### Dedicated Reject/Revoke Button System
- **Revolutionary UX**: Replaced problematic status dropdown with context-aware buttons
- **Three States**: Pending (red reject), Published (orange revoke), Rejected (grey disabled)
- **WordPress Integration**: StatusManager adds "Odmítnuto" to native dropdown
- **Conflict Resolution**: Eliminates validation gatekeeper conflicts
- **Security**: Dedicated AJAX endpoints with proper nonce validation

### Template System Architecture
- **Complete MVC Separation**: Zero HTML generation in PHP classes
- **Reusable Components**: 8 template files in `templates/admin-cards/`
- **Data Preparation**: Clean data arrays provided to templates
- **Domain-Specific Details**: Separate templates for realizace and faktura details
- **84% Code Reduction**: From monolithic renderers to lean data providers

### Admin Card Features
- **Responsive Grid**: Dynamic layout based on item count
- **Status Indicators**: Clear visual status with color coding
- **Action Buttons**: Approve/reject with confirmation dialogs
- **Gallery Integration**: Image gallery rendering for project submissions
- **Business Data**: Company information and ARES integration

### Base Class Architecture
- **AdminControllerBase**: Foundation for all domain admin interfaces
- **AdminCardRendererBase**: Template loading and data preparation patterns
- **PointsHandlerBase**: Unified myCred points management
- **FieldServiceBase**: Consistent ACF field access patterns