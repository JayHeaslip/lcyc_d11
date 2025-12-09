<?php

/**
 * This script sets view access for all roles on every book page node using the View Access Per Node (VAPN) module.
 * Assumptions:
 * - The content type for book pages is 'book'. If different, replace 'book' with your content type machine name.
 * - The VAPN field machine name is 'vapn'. If it's different (e.g., 'field_vapn'), check your node's edit form or database and update accordingly.
 * - This script should be run using Drush: Save it as a file (e.g., set_book_access.php) and run 'drush php:script set_book_access.php' from your Drupal site root.
 * - Ensure VAPN is enabled and configured for the 'book' content type.
 * - Backup your database before running this script.
 */

// Load all roles.
$role_storage = \Drupal::entityTypeManager()->getStorage('user_role');
$roles = $role_storage->loadMultiple();
$role_ids = array_keys($roles);

foreach ($role_ids as $role) {
  echo "- " . $role . "\n";
}

// Query all book nodes.
 $node_storage = \Drupal::entityTypeManager()->getStorage('node');
 $query = \Drupal::entityQuery('node')
   ->condition('type', 'book')
   ->accessCheck(FALSE); // Bypass access checks to load all nodes.
 
 // Execute query and get nids.
 $nids = $query->execute();
 
 // Process each node.
 foreach ($nids as $nid) {
   $node = $node_storage->load($nid);
    if ($node) {
     // 1. Get the title and ID for reporting
     $title = $node->getTitle(); 

     // 2. Get the VAPN field value
     $vapn_field = $node->get('vapn')->getValue();

     echo "\nNode $nid: **$title**\n";
    
     // 3. Check if the VAPN field has any roles set
     if (!empty($vapn_field)) {
       echo "  Allowed Roles:\n";
      
      // 4. Loop through the field items (which contain target_id)
      foreach ($vapn_field as $item) {
        $allowed_role_id = $item['target_id'];
        echo "    - " . $allowed_role_id . "\n";
      }
    } else {
      echo "  **No VAPN roles explicitly set (Default access applies).**\n";
    }
  }
}

echo "\nScript completed.\n";

?>
