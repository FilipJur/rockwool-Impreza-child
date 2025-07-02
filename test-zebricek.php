<?php
/**
 * Test file for debugging žebříček leaderboard
 * 
 * Usage: Include this file in a WordPress page template or run via WP-CLI
 */

// Check if myCred is active
echo "=== myCred Plugin Check ===\n";
if (function_exists('mycred')) {
    echo "✓ myCred is active\n";
} else {
    echo "✗ myCred is NOT active\n";
}

// Check if myCred functions exist
echo "\n=== myCred Functions Check ===\n";
$functions = [
    'mycred_get_users_balance',
    'mycred_get_leaderboard', // This should NOT exist
    'mycred',
    'mycred_add',
    'mycred_subtract'
];

foreach ($functions as $func) {
    echo sprintf("%-30s: %s\n", $func, function_exists($func) ? '✓ exists' : '✗ does not exist');
}

// Check myCred log table
echo "\n=== Database Table Check ===\n";
global $wpdb;
$log_table = $wpdb->prefix . 'mycred_log';
$table_exists = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM information_schema.tables 
     WHERE table_schema = %s AND table_name = %s",
    DB_NAME,
    $log_table
));

echo "Table {$log_table}: " . ($table_exists ? '✓ exists' : '✗ does not exist') . "\n";

if ($table_exists) {
    // Check table structure
    $columns = $wpdb->get_results("DESCRIBE {$log_table}");
    echo "\nTable columns:\n";
    foreach ($columns as $column) {
        echo "  - {$column->Field} ({$column->Type})\n";
    }
    
    // Check if there's any data
    $total_entries = $wpdb->get_var("SELECT COUNT(*) FROM {$log_table}");
    $current_year_entries = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$log_table} WHERE YEAR(time) = %d",
        date('Y')
    ));
    
    echo "\nData check:\n";
    echo "  Total entries: {$total_entries}\n";
    echo "  Current year entries: {$current_year_entries}\n";
    
    // Get sample data for current year
    $sample_data = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id, SUM(creds) as total_points 
         FROM {$log_table} 
         WHERE YEAR(time) = %d AND creds > 0
         GROUP BY user_id 
         ORDER BY total_points DESC 
         LIMIT 5",
        date('Y')
    ));
    
    if ($sample_data) {
        echo "\nTop 5 users for current year:\n";
        foreach ($sample_data as $user) {
            $user_info = get_userdata($user->user_id);
            $name = $user_info ? $user_info->display_name : "User #{$user->user_id}";
            echo "  - {$name}: {$user->total_points} points\n";
        }
    } else {
        echo "\nNo users with points found for current year.\n";
    }
}

// Test shortcode output
echo "\n=== Shortcode Test ===\n";
if (shortcode_exists('zebricek_leaderboard')) {
    echo "✓ zebricek_leaderboard shortcode is registered\n";
    
    // Try to render it
    $output = do_shortcode('[zebricek_leaderboard limit="5"]');
    echo "\nShortcode output length: " . strlen($output) . " characters\n";
    
    if (strpos($output, 'Žebříček zatím neobsahuje žádné uživatele') !== false) {
        echo "⚠️  Shortcode returns 'no users' message\n";
    }
} else {
    echo "✗ zebricek_leaderboard shortcode is NOT registered\n";
}

// Check debug log
echo "\n=== Debug Log Check ===\n";
$log_path = get_stylesheet_directory() . '/debug_zebricek.log';
if (file_exists($log_path)) {
    echo "✓ Debug log exists at: {$log_path}\n";
    $log_size = filesize($log_path);
    echo "  Size: " . number_format($log_size) . " bytes\n";
    
    // Show last 5 lines
    $lines = file($log_path);
    $last_lines = array_slice($lines, -10);
    if ($last_lines) {
        echo "\nLast few log entries:\n";
        foreach ($last_lines as $line) {
            echo "  " . trim($line) . "\n";
        }
    }
} else {
    echo "✗ Debug log does not exist at: {$log_path}\n";
}