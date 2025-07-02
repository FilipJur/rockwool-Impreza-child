# Žebříček (Leaderboard) Debug Instructions

## Summary of Changes

The žebříček leaderboard system has been updated to:
1. Query the `wp_mycred_log` table for current year points (not lifetime balance)
2. Remove non-existent `mycred_get_leaderboard()` function calls
3. Add proper debug logging to `debug_zebricek.log`
4. Fix table name case sensitivity (`myCRED_log` → `mycred_log`)

## How to Debug

1. **Enable WordPress Debug Mode**
   Add to `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

2. **Run the Test Script**
   Create a test page template or add to functions.php:
   ```php
   // Add this temporarily to test
   add_action('init', function() {
       if (isset($_GET['test-zebricek'])) {
           require_once get_stylesheet_directory() . '/test-zebricek.php';
           exit;
       }
   });
   ```
   Then visit: `yoursite.com/?test-zebricek=1`

3. **Check Debug Log**
   Look for `/wp-content/themes/Impreza-child/debug_zebricek.log`
   This will show:
   - Database queries being executed
   - Number of users found
   - Any errors

4. **Common Issues to Check**

   a) **No myCred log entries for current year**
      - The system only shows points earned in the current year (2025)
      - Check if there are any entries in `wp_mycred_log` table for 2025

   b) **Table name case sensitivity**
      - Some MySQL setups are case-sensitive
      - We've updated to use lowercase `mycred_log`
      - Check actual table name: `SHOW TABLES LIKE '%mycred%'`

   c) **User permissions**
      - Pending users see names only (no points)
      - Only full members see points
      - Check user status with UserService

5. **Manual Database Check**
   ```sql
   -- Check if table exists
   SHOW TABLES LIKE '%mycred%';
   
   -- Check current year data
   SELECT 
       user_id,
       SUM(creds) as total_points,
       COUNT(*) as transactions
   FROM wp_mycred_log
   WHERE YEAR(time) = 2025
       AND creds > 0
   GROUP BY user_id
   ORDER BY total_points DESC
   LIMIT 10;
   ```

## Shortcode Usage

```
[zebricek_leaderboard limit="30" offset="0"]
[zebricek_position]
```

## Key Files Modified

1. `src/App/Shortcodes/ZebricekLeaderboardShortcode.php`
   - Simplified from ~286 to ~200 lines
   - Now queries current year points from mycred_log table

2. `src/App/Shortcodes/ZebricekAjaxHandler.php`
   - Updated to use same current year logic
   - Fixed pagination queries

3. `src/App/Shortcodes/ZebricekPositionShortcode.php`
   - Updated position calculation for current year
   - Added debug logging

## Next Steps if Still Not Working

1. Check if myCred plugin is active
2. Verify table name (might need uppercase on some systems)
3. Check if there are any points logged for current year
4. Review debug log for specific errors
5. Test with a user who definitely has points in current year