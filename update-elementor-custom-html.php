<?php
/**
 * This script will update all Elementor custom HTML widgets to highlight a specific element.
 */
if ($argc < 5) {
   echo "Please run the program like this: php this-script.php element db-prefix folder y|n\n";
   echo "  'element' is the element to update, 'db-prefix' is the database table prefix, folder is the exact folder location for wp-load.php and 'y|n' is either y to run live or n to run in test.\n";
   echo "  Example: php this-script.php yellow-highlight wp sites/thesite/ n\n";
   echo "           This will run the script, search for <yellow-highlight>(text)</yellow-highlight> tags and replace with <span class="yellow-highlight">(text)</span>.\n";
   echo "           We will not actually update the database, just show what would have been updated.\n";
   exit(1);
}

$highlight_term = $argv[1];
$db_prefix = strtolower($argv[2]);
$folder = $argv[3];
$is_run_live = strtolower($argv[4]) === 'y';
$highlight_open_tag = "<" . $highlight_term . ">";
$highlight_close_tag = "</" . $highlight_term . ">";
$dynamic_style = $highlight_term;

require_once $folder . '/wp-load.php';

shell_exec("wp db query \"SELECT meta_id FROM {$db_prefix}_postmeta WHERE meta_value LIKE '%{$highlight_open_tag}%';\" --skip-column-names > meta_ids_to_update.txt");

$meta_ids = file('meta_ids_to_update.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

global $wpdb;

$cntUpdated = 0;
foreach ($meta_ids as $meta_id) {
   // Fetch the meta value by ID and verify it exists.
   $meta_entry = get_metadata_by_mid('post', $meta_id);
   if (!$meta_entry) {
       echo "Meta ID {$meta_id} not found. Skipping.\n";
       continue;
   }

   $meta_value = $meta_entry->meta_value;

   // Decode the JSON to PHP array. Skip if meta_value is not JSON or is empty.
   $meta_data = json_decode($meta_value, true);
   if (json_last_error() !== JSON_ERROR_NONE || !is_array($meta_data)) {
       echo "Meta ID {$meta_id} does not contain valid JSON. Skipping.\n";
       continue;
   }

   // Traverse the JSON data to replace all <yellow-highlight> tags.
   array_walk_recursive($meta_data, function (&$value) use ($highlight_open_tag, $highlight_close_tag, $dynamic_style) {
       $value = preg_replace(
           '/'. preg_quote($highlight_open_tag, '/') . '(.*?)' . preg_quote($highlight_close_tag, '/') . '/i',
           '<span class="' . $dynamic_style . '">$1</span>',
           $value
       );
   });

   // Re-encode the JSON.
   $updated_meta_value = json_encode($meta_data);

   // Update the post meta with the modified JSON.
   if ($is_run_live) {
       echo "Updating meta ID {$meta_id}.\n";
       $wpdb->update(
           "{$db_prefix}_postmeta",
           array('meta_value' => $updated_meta_value),
           array('meta_id' => $meta_id),
           array('%s'),
           array('%d')
       );
       echo "Updated meta ID {$meta_id}.\n";
       $cntUpdated++;
   } else {
       echo "Would have updated meta ID {$meta_id}.\n";
       $cntUpdated++;
   }
}

echo "Replacement complete" . ($is_run_live ? '' : ' (TEST - NO UPDATES ACTUALLY MADE)') . ", made {$cntUpdated} updates.\n";
?>
