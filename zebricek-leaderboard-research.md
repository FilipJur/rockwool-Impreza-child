# Žebříček (Leaderboard) System - Research & Requirements Documentation

## Project Context
WordPress child theme with enterprise architecture implementing a points-based leaderboard (žebříček) system for ROCKWOOL craftsman competition.

## Current System Architecture

### User Access Control System
- **Full Members** (`full_member` role): Complete access to all features
- **Pending Users** (`pending_approval` role) with meta statuses:
  - `needs_form`: Completed SMS registration, needs to fill form
  - `awaiting_review`: Submitted form, waiting for admin approval
- Centralized user status checking via `UserService::get_user_registration_status()`
- Existing conditional display patterns throughout codebase

### myCred Integration
- Points system already implemented via myCred plugin
- Existing point calculation for realizace (2500 points) and faktury (floor(value/10))
- Balance management with cart-aware system

## Business Requirements

### Leaderboard Features
- Display ~30 top users ranked by **total points acquired** (lifetime earnings)
- **Access-controlled content**:
  - **Pending users**: See names only, points hidden
  - **Full members**: See complete leaderboard with points
- Real-time position updates in user accounts ("Vaše aktuální pozice: 17. místo")
- User info per row: name, surname, company, view realizations button, points
- Archive system for previous years
- Future certification status integration
- Pagination with "Více" (More) button

### Technical Architecture Requirements

#### Page Structure Approach
- **Impreza Theme + WP Bakery Builder**: Static page layouts and structure
- **Shortcodes**: Dynamic content only (leaderboard data rows)
- **No SCSS styling**: Wireframe phase, minimal Tailwind for basic layout
- **Focused scope**: Shortcode returns only leaderboard content, no wrapper elements

#### Template Strategy
Impreza/WP Bakery handles:
- Page headers, navigation, static content
- Overall page layout and structure

Shortcodes handle:
- Dynamic leaderboard data only
- User-specific content (position, access control)
- Pagination logic

## myCred Research Findings

### Key Shortcodes Available
1. **`[mycred_leaderboard]`** - Main leaderboard with extensive parameters:
   - `total=1` - Use total balance (lifetime points) vs current balance
   - `number=30` - Limit display to 30 users
   - `template` - Custom row formatting
   - `offset` - Pagination support
   - Custom templates with variables: `%position%`, `%display_name%`, `%cred_f%`

2. **`[mycred_leaderboard_position]`** - Individual user position tracking
   - Shows current user's position in leaderboard
   - Perfect for "Vaše aktuální pozice: X. místo" requirement

3. **`[mycred_show_if]` / `[mycred_hide_if]`** - Conditional content display
   - Role-based content control
   - Balance/rank requirements
   - Perfect for access-controlled leaderboard views

### Critical Parameters
- **`total=1`**: Essential for ranking by lifetime points earned vs current spendable balance
- **`template`**: Custom formatting for different user access levels
- **`timeframe`**: Archive system support ("this-month", custom dates)
- **`offset`**: Pagination implementation

## Implementation Strategy

### Shortcode Architecture
Following existing codebase patterns:
- Extend `ShortcodeBase` class
- Auto-registration via `ShortcodeManager`
- Template-based rendering with MVC separation
- Integration with existing `UserService` for access control

### Access Control Implementation
```php
// Two template variations based on user status
if (UserService::get_user_registration_status() === 'full_member') {
    // Show: %position% %display_name% %cred_f%
} else {
    // Show: %position% %display_name% (points hidden)
}
```

### Key Shortcodes to Implement
1. `[zebricek_leaderboard]` - Main leaderboard with access control
2. `[zebricek_position]` - User position display
3. `[zebricek_archive]` - Historical leaderboards

## Figma Wireframe Reference
- Wireframe exists at specific node: 411-10836 in file outw2vznYSPnugaGVVrsYF
- Contains leaderboard layout structure
- Shows pagination and "more" button requirements
- Note: Figma MCP tool needed for detailed wireframe analysis

## Next Steps
1. Access Figma wireframe details via proper MCP tool
2. Implement shortcode wrappers around myCred functionality
3. Create access-controlled templates
4. Add pagination and archive functionality
5. Integration testing with existing user access control system

## Technical Notes
- Zero myCred core modifications - pure wrapper approach
- Leverage myCred's optimized queries and built-in caching
- Maintain all existing functionality and performance
- Use existing conditional display patterns from codebase