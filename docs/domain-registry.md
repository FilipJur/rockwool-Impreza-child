# Domain Registry System

## Overview

The Domain Registry system solves a fundamental issue with Czech language WordPress development: **singular vs plural form mismatches** that cause AJAX and nonce validation failures.

## The Problem

Czech language has different singular and plural forms for many words, creating inconsistencies:

```
✅ Realizace: realizace (singular) = realizace (plural) - NO ISSUE
❌ Faktury: faktura (singular) ≠ faktury (plural) - CAUSES PROBLEMS
❌ Certifikáty: certifikát (singular) ≠ certifikáty (plural) - FUTURE ISSUE
```

### Before Domain Registry

**The Old Way (Broken):**
```php
// PHP: Creates nonce with script prefix (plural)
wp_create_nonce('mistr_fachman_faktury_action');

// PHP: Registers AJAX hook with post type (singular)  
add_action('wp_ajax_mistr_fachman_faktura_quick_action', [...]);

// JavaScript: Sends nonce for plural, but PHP expects singular
// Result: 403 Forbidden Error
```

## The Solution

The **Domain Registry** provides a centralized mapping that ensures consistency:

```php
// src/App/Services/DomainRegistry.php
private static array $domains = [
    'faktury' => [
        'post_type' => 'faktura',        // WordPress post type (singular)
        'script_prefix' => 'faktury',    // Script handle prefix (plural) 
        'js_slug' => 'faktury',          // JavaScript domain slug (plural)
        'display_name' => 'Faktura',     // Display name (singular)
        'display_name_plural' => 'Faktury' // Display name (plural)
    ]
];
```

### Key Methods

```php
// Always returns post type for AJAX consistency
DomainRegistry::getAjaxPrefix('faktury'); // Returns: 'faktura'

// Generates correct AJAX actions
DomainRegistry::getAjaxAction('faktury', 'quick_action'); 
// Returns: 'mistr_fachman_faktura_quick_action'

// Generates correct nonce actions  
DomainRegistry::getNonceAction('faktury', 'quick_action');
// Returns: 'mistr_fachman_faktura_action'
```

## Implementation

### 1. PHP Domain Classes

Each domain's `AdminAssetManager` must implement:

```php
class AdminAssetManager extends AdminAssetManagerBase {
    
    protected function getDomainKey(): string {
        return 'faktury'; // Domain registry key
    }
    
    // Nonces are automatically generated using DomainRegistry
    // No need to override generateNonces() anymore
}
```

### 2. JavaScript Endpoints

JavaScript classes should use post type (singular) for endpoints:

```javascript
class FakturyManagement extends AdminManagementBase {
    
    getQuickActionEndpoint() {
        return 'mistr_fachman_faktura_quick_action'; // Uses post type
    }
    
    getBulkActionEndpoint() {
        return 'mistr_fachman_bulk_approve_faktura'; // Uses post type
    }
}
```

### 3. AJAX Hook Registration

PHP controllers register hooks using post type:

```php
// AdminControllerBase automatically uses getPostType()
add_action("wp_ajax_mistr_fachman_{$post_type}_quick_action", [...]);
add_action("wp_ajax_mistr_fachman_bulk_approve_{$post_type}", [...]);
```

## Adding New Domains

### Step 1: Register the Domain

Add to `DomainRegistry.php`:

```php
private static array $domains = [
    // ... existing domains
    'certifikaty' => [
        'post_type' => 'certifikat',
        'script_prefix' => 'certifikaty', 
        'js_slug' => 'certifikaty',
        'display_name' => 'Certifikát',
        'display_name_plural' => 'Certifikáty'
    ]
];
```

### Step 2: Implement AdminAssetManager

```php
class AdminAssetManager extends AdminAssetManagerBase {
    protected function getDomainKey(): string {
        return 'certifikaty';
    }
    
    protected function getScriptHandlePrefix(): string {
        return 'certifikaty'; // Plural for UI consistency
    }
}
```

### Step 3: JavaScript Management Class

```javascript
class CertifikatyManagement extends AdminManagementBase {
    getDomainSlug() {
        return 'certifikaty'; // Plural for UI
    }
    
    getQuickActionEndpoint() {
        return 'mistr_fachman_certifikat_quick_action'; // Singular!
    }
}
```

### Step 4: Validate Configuration

```php
// Test the new domain
$results = DomainRegistryValidator::testDomain('certifikaty');
if (!$results['valid']) {
    foreach ($results['errors'] as $error) {
        error_log("Domain validation error: {$error}");
    }
}
```

## Validation

### Automatic Validation

Run validation tests:

```php
// Test all domains
$report = DomainRegistryValidator::generateReport();
error_log($report);

// Test specific domain
$results = DomainRegistryValidator::testDomain('faktury');
```

### Common Issues

1. **AJAX Prefix Mismatch**: Ensure `getAjaxPrefix()` returns post type
2. **JavaScript Endpoint Wrong**: Use singular (post type) in JS endpoints
3. **Nonce Validation Fails**: Verify nonces use post type, not script prefix

## Czech Language Reference

### Common Patterns

| Singular | Plural | WordPress Post Type | Script Prefix | Notes |
|----------|--------|-------------------|---------------|--------|
| realizace | realizace | realizace | realizace | ✅ Same form - no issue |
| faktura | faktury | faktura | faktury | ❌ Different - use registry |
| certifikát | certifikáty | certifikat | certifikaty | ❌ Different - use registry |
| lokalizace | lokalizace | lokalizace | lokalizace | ✅ Same form - no issue |

### Guidelines

1. **Post Type**: Always use singular form for WordPress post types
2. **Script Prefix**: Use plural form for better UI/UX
3. **AJAX Actions**: Always use post type (singular) for consistency
4. **Display Names**: Store both singular and plural forms

## Benefits

✅ **Prevents 403 Errors**: Consistent nonce generation
✅ **Future-Proof**: New domains automatically work correctly
✅ **Self-Documenting**: Clear mapping of all identifiers  
✅ **Validation Built-in**: Automatic error detection
✅ **Czech Language Aware**: Handles plural/singular differences properly

## Troubleshooting

### 403 Forbidden Errors

1. Check domain is registered in `DomainRegistry.php`
2. Verify `getDomainKey()` returns correct domain
3. Ensure JavaScript uses post type in endpoints
4. Run `DomainRegistryValidator::testDomain()` for the domain

### Nonce Validation Failures

1. Verify nonce action uses post type: `mistr_fachman_{post_type}_action`
2. Check JavaScript sends correct nonce from localized data
3. Ensure AJAX hook registration uses same post type

### New Domain Not Working

1. Add domain to registry with all required fields
2. Implement `getDomainKey()` in AdminAssetManager
3. Use post type (singular) in JavaScript endpoints
4. Run validation tests to catch configuration errors