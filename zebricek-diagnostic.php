<?php
/**
 * Simple diagnostic to check myCred log table
 * Add to functions.php temporarily:
 * add_action('init', function() { if (isset($_GET['zebricek-diag'])) { include get_stylesheet_directory() . '/zebricek-diagnostic.php'; exit; }});
 */

global $wpdb;

// Set content type
header('Content-Type: text/plain; charset=utf-8');

echo "=== ZEBRICEK DIAGNOSTIC ===\n\n";

// 1. Find the actual table
echo "1. Finding myCred log table:\n";
$tables = $wpdb->get_col("SHOW TABLES");
$log_table = null;

foreach ($tables as $table) {
    if (stripos($table, 'mycred') !== false && stripos($table, 'log') !== false) {
        echo "   Found: $table\n";
        $log_table = $table;
        break;
    }
}

if (!$log_table) {
    echo "   ERROR: No myCred log table found!\n";
    echo "   All tables: " . implode(', ', $tables) . "\n";
    exit;
}

// 2. Check table structure
echo "\n2. Table structure for $log_table:\n";
$structure = $wpdb->get_results("SHOW COLUMNS FROM $log_table");
foreach ($structure as $col) {
    echo "   {$col->Field} - {$col->Type}\n";
}

// 3. Raw data check
echo "\n3. Sample data (last 5 entries):\n";
$sample = $wpdb->get_results("SELECT * FROM $log_table ORDER BY time DESC LIMIT 5");
foreach ($sample as $row) {
    echo "   User: {$row->user_id}, Points: {$row->creds}, Time: {$row->time}, Ref: {$row->ref}\n";
}

// 4. Year check
echo "\n4. Entries by year (from Unix timestamps):\n";
$years = $wpdb->get_results("
    SELECT 
        YEAR(FROM_UNIXTIME(time)) as year, 
        COUNT(*) as count,
        SUM(creds) as total_points
    FROM $log_table 
    WHERE creds > 0
    GROUP BY YEAR(FROM_UNIXTIME(time)) 
    ORDER BY year DESC
");

foreach ($years as $year) {
    echo "   {$year->year}: {$year->count} entries, {$year->total_points} total points\n";
}

// 5. Current year check with Unix timestamp query
$current_year = date('Y');
$year_start = strtotime($current_year . '-01-01 00:00:00');
$year_end = strtotime(($current_year + 1) . '-01-01 00:00:00');

echo "\n5. Testing the exact query for year $current_year:\n";
echo "   Unix timestamp range: $year_start to $year_end\n";
echo "   Date range: " . date('Y-m-d H:i:s', $year_start) . " to " . date('Y-m-d H:i:s', $year_end) . "\n\n";

// Test achievement-based filtering
$achievement_refs = [
    'approval_of_realization',
    'revoke_realization_points', 
    'approval_of_invoice',
    'revoke_invoice_points'
];
$ref_placeholders = implode(',', array_fill(0, count($achievement_refs), '%s'));

echo "   Testing ACHIEVEMENT-BASED scoring (only realizace/faktury transactions)\n";
echo "   Achievement refs: " . implode(', ', $achievement_refs) . "\n\n";

$query = $wpdb->prepare("
    SELECT 
        log.user_id,
        u.display_name,
        SUM(log.creds) as total_points
    FROM $log_table log
    INNER JOIN {$wpdb->users} u ON log.user_id = u.ID
    WHERE log.time >= %d
        AND log.time < %d
        AND log.ref IN ({$ref_placeholders})
    GROUP BY log.user_id
    HAVING total_points > 0
    ORDER BY total_points DESC
    LIMIT 5
", array_merge([$year_start, $year_end], $achievement_refs));

echo "Query: " . str_replace("\n", " ", $query) . "\n\n";

$results = $wpdb->get_results($query);

if ($wpdb->last_error) {
    echo "SQL Error: " . $wpdb->last_error . "\n";
}

if ($results) {
    echo "Results (NET points - positive + negative):\n";
    foreach ($results as $user) {
        echo "   {$user->display_name} (ID: {$user->user_id}): {$user->total_points} NET points\n";
        
        // Show breakdown of positive vs negative points for this user
        $breakdown = $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(CASE WHEN creds > 0 THEN creds ELSE 0 END) as positive_points,
                SUM(CASE WHEN creds < 0 THEN creds ELSE 0 END) as negative_points,
                COUNT(CASE WHEN creds > 0 THEN 1 END) as positive_entries,
                COUNT(CASE WHEN creds < 0 THEN 1 END) as negative_entries
            FROM {$log_table}
            WHERE user_id = %d 
                AND time >= %d 
                AND time < %d
        ", $user->user_id, $year_start, $year_end));
        
        if ($breakdown) {
            echo "     → Earned: {$breakdown->positive_points} in {$breakdown->positive_entries} entries\n";
            echo "     → Revoked: {$breakdown->negative_points} in {$breakdown->negative_entries} entries\n";
        }
    }
} else {
    echo "No results found.\n";
}

// 6. Test breakdown by transaction types
echo "\n6. Breakdown of all 2025 transactions by ref type:\n";
$ref_breakdown = $wpdb->get_results($wpdb->prepare("
    SELECT 
        ref,
        COUNT(*) as count,
        SUM(CASE WHEN creds > 0 THEN creds ELSE 0 END) as positive_points,
        SUM(CASE WHEN creds < 0 THEN creds ELSE 0 END) as negative_points,
        SUM(creds) as net_points
    FROM $log_table 
    WHERE time >= %d AND time < %d
    GROUP BY ref
    ORDER BY net_points DESC
", $year_start, $year_end));

echo "   Transaction Reference Breakdown:\n";
foreach ($ref_breakdown as $ref_data) {
    echo "   {$ref_data->ref}: {$ref_data->count} entries, Net: {$ref_data->net_points} (+{$ref_data->positive_points} / {$ref_data->negative_points})\n";
}

// 7. Compare ALL transactions vs ACHIEVEMENT-only for sample users
echo "\n7. Comparison: ALL vs ACHIEVEMENT transactions for sample users:\n";
$sample_users = $wpdb->get_results($wpdb->prepare("
    SELECT DISTINCT user_id 
    FROM $log_table 
    WHERE time >= %d AND time < %d
    LIMIT 3
", $year_start, $year_end));

foreach ($sample_users as $user) {
    $user_id = $user->user_id;
    $user_info = get_userdata($user_id);
    echo "   User: {$user_info->display_name} (ID: $user_id)\n";
    
    // ALL transactions
    $all_points = $wpdb->get_var($wpdb->prepare("
        SELECT COALESCE(SUM(creds), 0) 
        FROM $log_table 
        WHERE user_id = %d AND time >= %d AND time < %d
    ", $user_id, $year_start, $year_end));
    
    // ACHIEVEMENT transactions only
    $achievement_points = $wpdb->get_var($wpdb->prepare("
        SELECT COALESCE(SUM(creds), 0) 
        FROM $log_table 
        WHERE user_id = %d AND time >= %d AND time < %d AND ref IN ({$ref_placeholders})
    ", array_merge([$user_id, $year_start, $year_end], $achievement_refs)));
    
    echo "     → ALL transactions: $all_points points\n";
    echo "     → ACHIEVEMENT only: $achievement_points points\n";
    echo "     → Difference: " . ($all_points - $achievement_points) . " points (purchases/other)\n\n";
}

// 8. Test native shortcode
echo "\n8. Testing native [mycred_leaderboard] shortcode:\n";

if (shortcode_exists('mycred_leaderboard')) {
    echo "Shortcode exists. Testing with timeframe...\n";
    
    $shortcode = '[mycred_leaderboard number=3 based_on="total" timeframe="' . $current_year . '-01-01,00:00:00"]';
    echo "Shortcode: $shortcode\n";
    
    $output = do_shortcode($shortcode);
    
    if (strlen($output) > 0) {
        echo "Output length: " . strlen($output) . " characters\n";
        if (strpos($output, '<li') !== false) {
            preg_match_all('/<li/', $output, $matches);
            echo "Found " . count($matches[0]) . " list items\n";
        } else {
            echo "No list items found in output\n";
        }
    } else {
        echo "No output from shortcode\n";
    }
} else {
    echo "mycred_leaderboard shortcode not found!\n";
}

echo "\n=== END DIAGNOSTIC ===\n";