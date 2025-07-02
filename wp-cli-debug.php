<?php
/**
 * WP-CLI Debug Commands for Žebříček
 *
 * Usage: wp eval-file wp-cli-debug.php
 */

global $wpdb;

echo "=== myCred Database Diagnostics ===\n\n";

// 1. Check table name variations
echo "1. Checking for myCred log table variants:\n";
$possible_tables = [
    $wpdb->prefix . 'mycred_log',
    $wpdb->prefix . 'myCRED_log',
    $wpdb->prefix . 'mycred_Log',
    $wpdb->prefix . 'MYCRED_LOG'
];

$actual_table = null;
foreach ($possible_tables as $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if ($exists) {
        echo "   ✓ Found: $table\n";
        $actual_table = $table;
        break;
    } else {
        echo "   ✗ Not found: $table\n";
    }
}

if (!$actual_table) {
    echo "\n❌ No myCred log table found!\n";
    exit;
}

echo "\n2. Table structure for $actual_table:\n";
$columns = $wpdb->get_results("DESCRIBE $actual_table");
foreach ($columns as $col) {
    echo "   - {$col->Field} ({$col->Type})\n";
}

// 3. Check data by year
echo "\n3. Points data by year:\n";
$years_query = "
    SELECT
        YEAR(FROM_UNIXTIME(time)) as year,
        COUNT(DISTINCT user_id) as unique_users,
        COUNT(*) as total_entries,
        SUM(creds) as total_points
    FROM $actual_table
    WHERE creds > 0
    GROUP BY YEAR(FROM_UNIXTIME(time))
    ORDER BY year DESC
";
$years_data = $wpdb->get_results($years_query);

if ($years_data) {
    foreach ($years_data as $year_data) {
        echo "   Year {$year_data->year}: {$year_data->unique_users} users, {$year_data->total_entries} entries, {$year_data->total_points} total points\n";
    }
} else {
    echo "   No data found\n";
}

// 4. Check current year specifically
$current_year = date('Y');
echo "\n4. Current year ($current_year) detailed check:\n";

$current_year_query = "
    SELECT
        user_id,
        SUM(creds) as total_points,
        COUNT(*) as entries,
        MIN(time) as first_entry,
        MAX(time) as last_entry
    FROM $actual_table
    WHERE YEAR(FROM_UNIXTIME(time)) = $current_year
        AND creds > 0
    GROUP BY user_id
    ORDER BY total_points DESC
    LIMIT 10
";

$current_year_data = $wpdb->get_results($current_year_query);

if ($current_year_data) {
    echo "   Top 10 users for $current_year:\n";
    foreach ($current_year_data as $user) {
        $user_info = get_userdata($user->user_id);
        $name = $user_info ? $user_info->display_name : "User #{$user->user_id}";
        echo "   - $name (ID: {$user->user_id}): {$user->total_points} points in {$user->entries} entries\n";
        echo "     First: {$user->first_entry}, Last: {$user->last_entry}\n";
    }
} else {
    echo "   ❌ No users with points in $current_year\n";

    // Check if there's ANY data in the table
    $any_data = $wpdb->get_var("SELECT COUNT(*) FROM $actual_table");
    echo "   Total entries in table: $any_data\n";

    // Get most recent entry
    $recent = $wpdb->get_row("SELECT * FROM $actual_table ORDER BY time DESC LIMIT 1");
    if ($recent) {
        echo "   Most recent entry: {$recent->time} (User: {$recent->user_id}, Points: {$recent->creds})\n";
    }
}

// 5. Test native myCred shortcodes
echo "\n5. Testing native myCred shortcodes:\n";

// Check if shortcodes exist
$shortcodes = ['mycred_leaderboard', 'mycred_stats', 'mycred_leaderboard_position'];
foreach ($shortcodes as $sc) {
    echo "   - [$sc]: " . (shortcode_exists($sc) ? '✓ exists' : '✗ not found') . "\n";
}

// Test leaderboard with timeframe
if (shortcode_exists('mycred_leaderboard')) {
    echo "\n6. Testing [mycred_leaderboard] with timeframe:\n";

    // Try different timeframe options
    $timeframes = [
        'today' => '[mycred_leaderboard number=5 timeframe="today"]',
        'this-week' => '[mycred_leaderboard number=5 timeframe="this-week"]',
        'this-month' => '[mycred_leaderboard number=5 timeframe="this-month"]',
        'custom' => '[mycred_leaderboard number=5 timeframe="' . date('Y') . '-01-01,00:00:00"]'
    ];

    foreach ($timeframes as $tf => $shortcode) {
        echo "\n   Testing timeframe '$tf':\n";
        $output = do_shortcode($shortcode);
        if (strlen($output) > 0 && strpos($output, 'mycred-leaderboard') !== false) {
            echo "   ✓ Output generated (" . strlen($output) . " chars)\n";
            // Check if it has actual entries
            if (strpos($output, '<li') !== false) {
                $matches = [];
                preg_match_all('/<li/', $output, $matches);
                echo "   Found " . count($matches[0]) . " entries\n";
            }
        } else {
            echo "   ✗ No output or error\n";
        }
    }
}

// 7. Check myCred settings
echo "\n7. myCred Configuration:\n";
$mycred = mycred();
if ($mycred) {
    echo "   Point type: " . $mycred->singular() . " / " . $mycred->plural() . "\n";
    echo "   Format: " . $mycred->format_creds(1000) . "\n";

    // Check if log module is enabled
    $modules = get_option('mycred_pref_core');
    if (isset($modules['modules']['solo']['log'])) {
        echo "   Log module: " . ($modules['modules']['solo']['log'] ? '✓ enabled' : '✗ disabled') . "\n";
    }
}
