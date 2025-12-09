<?php

/**
 * This script MODIFIES view access to allow all roles on every book page node
 * using the View Access Per Node (VAPN) module.
 *
 * Assumptions:
 * - The content type for book pages is 'book'.
 * - The VAPN field machine name is 'vapn'.
 * - This script should be run using Drush: Save it as a file (e.g., set_book_access.php)
 * and run 'drush php:script set_book_access.php' from your Drupal site root.
 * - Ensure VAPN is enabled and configured for the 'book' content type.
 * - Backup your database before running this script!
 */

// Load all roles.
$role_storage = \Drupal::entityTypeManager()->getStorage('user_role');
$roles = $role_storage->loadMultiple();
$role_ids = array_keys($roles);

echo "✅ Target Roles to Set VAPN For:\n";
foreach ($role_ids as $role) {
  echo "- " . $role . "\n";
}

// 1. Prepare the VAPN field value array to include ALL roles.
// VAPN fields typically expect an array structure like:
// [['target_id' => 'role_id_1'], ['target_id' => 'role_id_2'], ...]
$vapn_roles_to_set = [];
foreach ($role_ids as $role_id) {
    // Skip setting 'anonymous' role if 'authenticated' is present and site policy
    // is to allow all by default, but including it is safest for VAPN.
    $vapn_roles_to_set[] = ['target_id' => $role_id];
}


// Query all book nodes.
 $node_storage = \Drupal::entityTypeManager()->getStorage('node');
 $query = \Drupal::entityQuery('node')
   ->condition('type', 'book')
   ->accessCheck(FALSE); // Bypass access checks to load all nodes.

 // Execute query and get nids.
 $nids = $query->execute();

 // Count for reporting
 $count_updated = 0;

 echo "\nProcessing " . count($nids) . " book nodes...\n";

 // Process each node.
 foreach ($nids as $nid) {
   // Load the node.
   $node = $node_storage->load($nid);

    if ($node) {
     $title = $node->getTitle();

     echo "\nNode $nid: **$title**\n";

     try {
       // 2. Set the VAPN field to the new value (all roles).
       $node->set('vapn', $vapn_roles_to_set);

       // 3. Save the node to apply the change.
       $status = $node->save();

       if ($status === SAVED_UPDATED) {
           echo "  ➡️ VAPN field updated successfully to ALL roles.\n";
           $count_updated++;
       } elseif ($status === SAVED_NEW) {
           // This shouldn't happen for an existing node, but is good for debugging.
           echo "  ⚠️ Error: Node was saved as NEW, not UPDATED.\n";
       }

     } catch (\Exception $e) {
         echo "  ❌ ERROR saving node $nid: " . $e->getMessage() . "\n";
     }
  } else {
      echo "  ❌ ERROR: Could not load node with NID $nid.\n";
  }
}

echo "\n--- Summary ---\n";
echo "Total nodes found: " . count($nids) . "\n";
echo "Nodes successfully updated: $count_updated\n";
echo "\nScript completed.\n";

?>
