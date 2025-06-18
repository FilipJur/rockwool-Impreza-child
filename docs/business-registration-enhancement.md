# Enhanced Business Registration System Implementation Plan

## Overview

Transform the current simple user registration into a comprehensive business validation system with ARES integration, structured data storage, and modal-based admin approval interface.

## Current State

**Existing Registration Flow:**
1. User registers via SMS OTP → `pending_approval` role + `needs_form` status
2. User submits Contact Form 7 → auto-updates to `awaiting_review` status  
3. Admin manually promotes → `full_member` role

**Current Form (Basic):**
- Name, email, subject, message fields only
- No business validation
- No structured data capture

## Enhanced Registration Flow

**New Business Registration Flow:**
1. User registers via SMS OTP → `pending_approval` role + `needs_form` status
2. User submits **enhanced business registration form** with:
   - ARES validation for IČO
   - Company information
   - Representative details
   - Business acceptance criteria
3. **Server-side validation** → if passes → `awaiting_review` status + structured data storage
4. **Enhanced Admin UI** with modal showing business data for validation
5. Admin reviews business data → manual approval → `full_member` role

## Implementation Plan

### Phase 1: Contact Form 7 Enhancement

**Objective:** Create comprehensive business registration form

**Form Structure:**
```html
<div class="registration-form">
    <h2>Závěrečná registrace</h2>
    
    <!-- Business Criteria Acceptance -->
    <div class="registration-form-confirms">
        [acceptance zateplovani] Věnuji se zateplování rodinných domů do 800 m2 plochy.[/acceptance]
        [acceptance kriteria-obratu] Splňuji kritéria obratu do 50 mil. Kč.[/acceptance]
    </div>
    
    <!-- ARES Validation Section -->
    <div class="ares-input-wrapper">
        <div class="ares-input-wrapper-submit">
            [text* ico 8/8 placeholder "IČO"]
            <button type="button" id="loadAresData">Ověřit</button>
        </div>
        <span id="aresStatus"></span>
    </div>
    
    <!-- Company Information -->
    [text* company-name placeholder "Název společnosti"]
    [text* address placeholder "Adresa společnosti"]
    
    <!-- Representative Information -->
    [text* first-name placeholder "Jméno zástupce"]
    [text* last-name placeholder "Příjmení zástupce"]
    [select* position "Pozice ve společnosti" "jednatel" "ředitel" "majitel" "zástupce"]
    [email* contact-email placeholder "Kontaktní e-mail"]
    
    <!-- Optional Fields -->
    [text promo-code placeholder "Promo kód (volitelné)"]
    
    <!-- Form Actions -->
    [submit "Odeslat registraci"]
    
    <a href="/eshop" class="leave-later-btn">Nechat na později</a>
</div>
```

**Form Fields Mapping:**
- `ico` → IČO with ARES validation
- `company-name` → Auto-filled from ARES or manual entry
- `address` → Auto-filled from ARES or manual entry
- `first-name` + `last-name` → Representative name
- `position` → Business position from select box
- `contact-email` → Business contact email
- `promo-code` → Optional promotional code
- `zateplovani` + `kriteria-obratu` → Business criteria acceptances

### Phase 2: ARES Integration Extension

**Objective:** Extend existing ARES handler for new form context with server-side validation

**Current ARES Handler:** 
- Located in `src/js/features/ares/handler.js`
- Handles client-side IČO validation and company data fetching
- Works with landing page registration form

**Extension Strategy:**
1. **Keep existing handler intact** for current landing page functionality
2. **Create new form context** for business registration form
3. **Add server-side ARES validation** in PHP to block invalid submissions
4. **Cache ARES validation results** in user meta for admin review

**Technical Implementation:**
- Extend `RegistrationHooks::handle_final_registration_submission()`
- Add server-side ARES API call with validation
- Store ARES response data for admin verification
- Block form submission if ARES validation fails

**Server-Side ARES Validation:**
```php
// Validate IČO format and fetch ARES data
$ico = sanitize_text_field($_POST['ico']);
$ares_data = $this->validate_ico_with_ares($ico);

if (!$ares_data['valid']) {
    // Block submission and return error
    wp_die('IČO validation failed: ' . $ares_data['error']);
}

// Store validated data for admin review
$this->store_business_data($user_id, $form_data, $ares_data);
```

### Phase 3: Data Architecture & Storage

**Objective:** Design structured user meta schema for business registration data

**User Meta Structure:**
```php
// Meta key: _mistr_fachman_business_data
$business_data = [
    'ico' => '12345678',
    'company_name' => 'Zateplování s.r.o.',
    'address' => 'Prague, Czech Republic',
    'representative' => [
        'first_name' => 'Jan',
        'last_name' => 'Novák',
        'email' => 'jan@company.cz',
        'position' => 'jednatel'
    ],
    'acceptances' => [
        'zateplovani' => true,
        'kriteria_obratu' => true
    ],
    'promo_code' => 'MULTI2024',
    'validation' => [
        'ares_verified' => true,
        'ares_data' => [
            'obchodniJmeno' => 'Zateplování s.r.o.',
            'sidlo' => ['textovaAdresa' => 'Prague...'],
            'verified_at' => '2025-06-18 14:30:00'
        ]
    ],
    'submitted_at' => '2025-06-18 14:30:00'
];
```

**Storage Methods:**
```php
// Store business data
public function store_business_data(int $user_id, array $form_data, array $ares_data): bool

// Retrieve business data  
public function get_business_data(int $user_id): ?array

// Check if user has business data
public function has_business_data(int $user_id): bool
```

### Phase 4: Modal-Based Admin Interface

**Objective:** Create enhanced admin interface with modal-based business data review

**Admin UI Enhancement:**
1. **Users Overview Page:** Add "Pending Review" button for users with `awaiting_review` status
2. **Modal Dialog:** Display structured business data in organized sections
3. **Action Buttons:** Approve/Reject with proper security and feedback
4. **Data Display:** Company info, representative details, ARES validation status

**Modal Structure:**
```html
<div id="business-data-modal" class="modal">
    <div class="modal-content">
        <h2>Business Registration Review</h2>
        
        <!-- Company Section -->
        <section class="company-info">
            <h3>Company Information</h3>
            <div class="data-row">
                <label>IČO:</label>
                <span class="ico-value">12345678</span>
                <span class="ares-status verified">✓ ARES Verified</span>
            </div>
            <div class="data-row">
                <label>Company Name:</label>
                <span>Zateplování s.r.o.</span>
            </div>
            <div class="data-row">
                <label>Address:</label>
                <span>Prague, Czech Republic</span>
            </div>
        </section>
        
        <!-- Representative Section -->
        <section class="representative-info">
            <h3>Representative</h3>
            <div class="data-row">
                <label>Name:</label>
                <span>Jan Novák</span>
            </div>
            <div class="data-row">
                <label>Position:</label>
                <span>jednatel</span>
            </div>
            <div class="data-row">
                <label>Email:</label>
                <span>jan@company.cz</span>
            </div>
        </section>
        
        <!-- Business Criteria -->
        <section class="business-criteria">
            <h3>Business Criteria</h3>
            <div class="criteria-item">
                <span class="status accepted">✓</span>
                <span>Věnuje se zateplování rodinných domů do 800 m2 plochy</span>
            </div>
            <div class="criteria-item">
                <span class="status accepted">✓</span>
                <span>Splňuje kritéria obratu do 50 mil. Kč</span>
            </div>
        </section>
        
        <!-- Actions -->
        <div class="modal-actions">
            <button class="approve-btn" data-user-id="123">Approve User</button>
            <button class="reject-btn" data-user-id="123">Reject</button>
            <button class="close-btn">Close</button>
        </div>
    </div>
</div>
```

**AdminInterface Enhancement:**
```php
// Add modal trigger button to user profile
public function add_business_data_review_button(\WP_User $user): void

// Render modal with business data
public function render_business_data_modal(int $user_id): void

// Handle AJAX requests for modal data
public function handle_modal_data_request(): void

// Process approval/rejection actions
public function handle_business_approval_action(): void
```

### Phase 5: Integration & Testing

**Objective:** Connect all components and ensure robust workflow

**Integration Points:**
1. **Form Submission:** CF7 → RegistrationHooks → Data Storage
2. **Admin Review:** User List → Modal → Approval Action
3. **Data Flow:** Form Data → User Meta → Admin Display
4. **Validation:** Client ARES → Server ARES → Storage → Admin Review

**Testing Scenarios:**
- Valid IČO submission with successful ARES validation
- Invalid IČO handling and error messaging
- Form submission without ARES validation (blocked)
- Admin modal display with various data combinations
- Approval/rejection workflow with proper redirects
- Edge cases: ARES API failures, network issues, malformed data

**Error Handling:**
- ARES API failures with graceful fallback
- Invalid form data sanitization and validation
- Network timeout handling for ARES requests
- Modal state management and error display
- User feedback for validation failures

## Technical Decisions

### Data Storage Strategy
**Decision:** Use structured user meta instead of custom post types
**Rationale:** 
- Data belongs with user throughout lifecycle
- Simpler architecture without redundant tables
- WordPress standard for user-related data
- Easy cleanup when users are promoted/deleted

### ARES Integration Approach
**Decision:** Extend existing handler.js while keeping server-side validation
**Rationale:**
- Maintain existing functionality for landing page
- Add robust server-side validation for security
- Cache validation results for admin review
- Block invalid submissions at source

### Admin Interface Design
**Decision:** Modal-based review interface
**Rationale:**
- Non-intrusive user list interface
- Contextual data display when needed
- Scalable for future enhancements
- Follows WordPress admin UI patterns

### Form Enhancement Strategy
**Decision:** Enhance existing Contact Form 7 with business fields
**Rationale:**
- Maintain existing form processing infrastructure
- Leverage CF7's built-in validation and security
- Integrate seamlessly with current registration flow
- Familiar form structure for users

## Implementation Requirements

### PHP Requirements
- Extend `RegistrationHooks` class for business data handling
- Add server-side ARES validation methods
- Enhance `AdminInterface` with modal functionality
- Create data storage and retrieval methods

### JavaScript Requirements
- Extend ARES handler for new form context
- Add modal management functionality
- Implement AJAX for admin actions
- Handle form validation feedback

### CSS Requirements
- Style enhanced registration form
- Design modal interface layout
- Add responsive design for admin interface
- Create status indicators and visual feedback

### WordPress Integration
- Update Contact Form 7 configuration
- Add admin menu items if needed
- Implement proper nonce security
- Add user capability checks

## Future Enhancements

### Email Notifications
- Admin notification on form submission
- User notification on approval/rejection
- Automated follow-up sequences

### Advanced Validation
- Document upload verification
- Additional business criteria validation
- Integration with external business databases

### Reporting & Analytics
- Registration success/failure metrics
- Admin approval workflow analytics
- Business criteria compliance reporting

### API Integration
- External CRM integration
- Business intelligence data export
- Third-party validation services

## Maintenance Considerations

### Code Organization
- Keep business logic in dedicated classes
- Maintain clear separation of concerns
- Document data structures thoroughly
- Add comprehensive error logging

### Performance
- Cache ARES validation results
- Optimize modal loading performance
- Minimize database queries
- Consider pagination for large user lists

### Security
- Validate all form inputs server-side
- Implement proper nonce verification
- Sanitize stored business data
- Add user capability checks

### Scalability
- Design for increasing user volume
- Plan for additional business fields
- Consider multi-step form workflow
- Prepare for advanced approval processes

---

**Status:** Ready for implementation
**Priority:** High - Core business registration enhancement
**Estimated Effort:** 5-7 development sessions
**Dependencies:** Current registration system (stable)