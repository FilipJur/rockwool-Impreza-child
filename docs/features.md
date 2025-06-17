# Feature Specifications

This document details the implemented features and their technical specifications.

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