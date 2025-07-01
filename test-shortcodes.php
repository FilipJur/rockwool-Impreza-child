<?php
/**
 * Test file for relational select shortcodes
 * 
 * Usage: Add this to a test page or run via WP-CLI to verify shortcode registration
 */

// Test shortcode registration
if (shortcode_exists('realizace_construction_types')) {
    echo "✅ [realizace_construction_types] shortcode is registered\n";
} else {
    echo "❌ [realizace_construction_types] shortcode NOT registered\n";
}

if (shortcode_exists('realizace_materials')) {
    echo "✅ [realizace_materials] shortcode is registered\n";
} else {
    echo "❌ [realizace_materials] shortcode NOT registered\n";
}

// Test shortcode output
echo "\n=== Construction Types Shortcode Output ===\n";
echo do_shortcode('[realizace_construction_types name="test-construction"]');

echo "\n\n=== Materials Shortcode Output ===\n";
echo do_shortcode('[realizace_materials name="test-materials"]');

echo "\n\n=== Form Integration Example ===\n";
echo '<form>';
echo do_shortcode('[realizace_construction_types multiple="true" name="construction-types"]');
echo do_shortcode('[realizace_materials multiple="true" name="materials"]');
echo '<input type="submit" value="Test Submit">';
echo '</form>';