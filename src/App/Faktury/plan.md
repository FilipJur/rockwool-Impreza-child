 │ Plan to Complete Faktury Implementation                                │
     │                                                                        │
     │ 1. Fix Template Error (5 min)                                          │
     │                                                                        │
     │ - Create missing                                                       │
     │ templates/admin-cards/domain-details/faktura-details.php               │
     │ - Copy from realizace-details.php and adapt for invoice fields         │
     │                                                                        │
     │ 2. Complete Validation Service (20 min)                                │
     │                                                                        │
     │ Implement the three validation rules in ValidationService.php:         │
     │                                                                        │
     │ a) Monthly Limit Check (300,000 CZK)                                   │
     │ - Query sum of approved faktury for current month                      │
     │ - Include current invoice value in calculation                         │
     │ - Return specific error message if exceeded                            │
     │                                                                        │
     │ b) Current Year Validation                                             │
     │ - Add invoice_date field to FakturaFieldService                        │
     │ - Parse date and validate year matches current year                    │
     │ - Return error if invoice from past/future year                        │
     │                                                                        │
     │ c) Invoice Number Uniqueness                                           │
     │ - Add invoice_number field to FakturaFieldService                      │
     │ - Query for existing invoices with same number for user                │
     │ - Return error if duplicate found                                      │
     │                                                                        │
     │ 3. Add Missing ACF Field Methods (10 min)                              │
     │                                                                        │
     │ Update FakturaFieldService.php to add:                                 │
     │ - getInvoiceDate() / setInvoiceDate()                                  │
     │ - getInvoiceNumber() / setInvoiceNumber()                              │
     │ - Field selector constants for new fields                              │
     │                                                                        │
     │ 4. Wire Up Validation (5 min)                                          │
     │                                                                        │
     │ - Update AdminController's run_pre_publish_validation() to call all    │
     │ validation methods                                                     │
     │ - Add specific error messages for each validation failure              │
     │ - Test validation works in both contexts (admin UI and post editor)    │
     │                                                                        │
     │ 5. Test End-to-End (10 min)                                            │
     │                                                                        │
     │ - Submit invoice via form                                              │
     │ - Test approval/rejection via admin UI                                 │
     │ - Verify points calculation                                            │
     │ - Test validation rules trigger correctly                              │
     │ - Confirm no AJAX errors                                               │
     │                                                                        │
     │ 6. Optional: Add PostTypeManager (5 min)                               │
     │                                                                        │
     │ - Create dedicated PostTypeManager.php extending PostTypeManagerBase   │
     │ - Move post type registration logic from Manager                       │
     │ - Follow Realizace pattern for consistency                             │
     │                                                                        │
     │ Total Time: ~55 minutes                                                │
     │                                                                        │
     │ This plan leverages our strong abstraction to complete Faktury with    │
     │ minimal effort. The base classes handle 80% of the work, we just need  │
     │ to implement the domain-specific validation rules and fix the template │
     │ issue.                                                                 │
     ╰────────────────────────────────────────────────────────────────────────╯

> Big problem we are currently experiencing is that user submits form
  correctly, faktura arrives with pending -> in user dashboard point to
  approves is correctly calculated. -> post detail ACF empty <div
  class="acf-field acf-field-number acf-field-668d4f7c1a2b5 -r0" style="width:
  50%; min-height: 137px;" data-name="faktura_pridelene_body"
  data-type="number" data-key="field_668d4f7c1a2b5" data-width="50">
  <div class="acf-label">
  <label for="acf-field_668d4f7c1a2b4-field_668d4f7c1a2b5">Přidělené body
  (vypočítané)</label><p class="description">Body se vypočítají automaticky z
  hodnoty faktury. Toto pole je pouze pro audit.</p></div>
  <div class="acf-input">
  <div class="acf-input-wrap"><input type="number"
  id="acf-field_668d4f7c1a2b4-field_668d4f7c1a2b5"
  name="acf[field_668d4f7c1a2b4][field_668d4f7c1a2b5]" step="any"></div></div>
  </div>\
  \
  Invoice value and file are filled correctly

⏺ Let me investigate this issue where the calculated points field is empty in
  the post detail ACF editor even though points are correctly calculated in the
   user dashboard.

⏺ Read(src/App/Faktury/NewFakturyFormHandler.php)
  ⎿  Read 103 lines (ctrl+r to expand)

⏺ Read(src/App/Faktury/AdminController.php)
  ⎿  Read 50 lines (ctrl+r to expand)

⏺ Read(src/App/Faktury/FakturaFieldService.php)
  ⎿  Read 60 lines (ctrl+r to expand)

⏺ Search(pattern: "field_668d4f7c1a2b5", path: "src")
  ⎿  Found 0 files

⏺ Read(src/App/Faktury/PointsHandler.php)
  ⎿  Read 30 lines (ctrl+r to expand)

⏺ Now I understand the issue! Looking at the ACF field structure in the HTML
  you provided:

  <input type="number" id="acf-field_668d4f7c1a2b4-field_668d4f7c1a2b5"
  name="acf[field_668d4f7c1a2b4][field_668d4f7c1a2b5]">

  The field is nested within a group field (field_668d4f7c1a2b4), but the
  FakturaFieldService is using the wrong field selector. It's using just the
  field name without the group prefix.

  Let me check what the correct field structure should be:

⏺ Read(src/App/Realizace/RealizaceFieldService.php)
  ⎿  Read 40 lines (ctrl+r to expand)

⏺ Problem Analysis: Empty ACF Points Field

  I've identified the root cause of the issue:

  The Problem:
  1. The ACF field for points is nested within a group field
  2. From the HTML: The points field (field_668d4f7c1a2b5) is inside a group
  field (field_668d4f7c1a2b4)
  3. The FakturaFieldService is using the wrong field selector:
  sprava_a_hodnoceni_faktury_pridelene_body
  4. But it should be using the group notation:
  sprava_a_hodnoceni_faktury_pridelene_body (which might need to be the full
  field key or group_field notation)

  Why It's Happening:
  - When form is submitted, points are calculated correctly in the dashboard
  (using PHP calculation)
  - But when saving to ACF, the field selector doesn't match the actual ACF
  field structure
  - ACF expects either the field key (field_668d4f7c1a2b5) or proper group
  notation

  Current Flow:
  1. ✅ Form submission creates faktura post with pending status
  2. ✅ Invoice value and file are saved correctly
  3. ✅ Points are calculated correctly (floor(value/30))
  4. ❌ Points are NOT saved to ACF field (wrong field selector)
  5. ✅ Points still show in dashboard (calculated on-the-fly)
  6. ❌ ACF editor shows empty points field
  ⎿  User rejected Claude's plan:
     ╭────────────────────────────────────────────────────────────────────────╮
     │ Plan to Fix Empty ACF Points Field                                     │
     │                                                                        │
     │ 1. Diagnose ACF Field Structure (5 min)                                │
     │                                                                        │
     │ - Check if points field is inside a group field (appears to be from    │
     │ HTML)                                                                  │
     │ - Compare with Realizace to see if it has similar structure            │
     │ - Determine correct field selector format                              │
     │                                                                        │
     │ 2. Update FakturaFieldService (10 min)                                 │
     │                                                                        │
     │ Update the POINTS_FIELD constant in FakturaFieldService.php:           │
     │                                                                        │
     │ Option A - If it's a group field:                                      │
     │ private const POINTS_FIELD =                                           │
     │ 'sprava_a_hodnoceni_faktury_pridelene_body';                           │
     │ private const POINTS_GROUP = 'sprava_a_hodnoceni_faktury'; // Add      │
     │ group field                                                            │
     │                                                                        │
     │ Option B - Use field key directly:                                     │
     │ private const POINTS_FIELD = 'field_668d4f7c1a2b5'; // Use ACF field   │
     │ key                                                                    │
     │                                                                        │
     │ 3. Modify Field Access Methods (10 min)                                │
     │                                                                        │
     │ Update the setPoints() method to handle group fields properly:         │
     │                                                                        │
     │ public static function setPoints(int $post_id, int $points): bool {    │
     │     // If using group field structure                                  │
     │     if (self::POINTS_GROUP) {                                          │
     │         $group_data = get_field(self::POINTS_GROUP, $post_id) ?: [];   │
     │         $group_data['pridelene_body'] = $points;                       │
     │         return update_field(self::POINTS_GROUP, $group_data,           │
     │ $post_id);                                                             │
     │     }                                                                  │
     │     // Otherwise use direct field                                      │
     │     return self::setFieldValueInternal(self::POINTS_FIELD, $post_id,   │
     │ $points);                                                              │
     │ }                                                                      │
     │                                                                        │
     │ 4. Add Points Population Hook (5 min)                                  │
     │                                                                        │
     │ Add an ACF load_value hook to populate calculated points when editing: │
     │                                                                        │
     │ add_filter('acf/load_value/name=faktura_pridelene_body',               │
     │ function($value, $post_id, $field) {                                   │
     │     if (empty($value) && get_post_type($post_id) === 'faktura') {      │
     │         $points_handler = new PointsHandler();                         │
     │         return $points_handler->getCalculatedPoints($post_id);         │
     │     }                                                                  │
     │     return $value;                                                     │
     │ }, 10, 3);                                                             │
     │                                                                        │
     │ 5. Test the Fix (5 min)                                                │
     │                                                                        │
     │ - Edit an existing faktura post - points should show                   │
     │ - Approve a faktura - points should save                               │
     │ - Create new faktura - points should calculate and save                │
     │                                                                        │
     │ 6. Alternative Quick Fix (2 min)                                       │
     │                                                                        │
     │ If the above is complex, add a simple admin hook to populate on edit:  │
     │                                                                        │
     │ add_action('acf/input/admin_head', function() {                        │
     │     if (get_post_type() === 'faktura') {                               │
     │         $post_id = get_the_ID();                                       │
     │         $current_points = FakturaFieldService::getPoints($post_id);    │
     │         if ($current_points === 0) {                                   │
     │             $calculated = (new                                         │
     │ PointsHandler())->getCalculatedPoints($post_id);                       │
     │             // Populate via JavaScript if needed                       │
     │         }                                                              │
     │     }                                                                  │
     │ });                                                                    │
     │                                                                        │
     │ Total Time: ~35 minutes                                                │
     │                                                                        │
     │ This should fix the empty points field issue while maintaining the     │
     │ correct calculation logic.                                             │
     ╰────────────────────────────────────────────────────────────────────────╯
