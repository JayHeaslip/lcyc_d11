<?php

/**
 * Implements hook_menu_links_discovered_alter().
 */
function my_module_menu_links_discovered_alter(&$links) {
  if (isset($links['user.logout'])) {
    $links['user.logout']['parent'] = 'my_module.user_email_link';
    // Optional: Adjust weight to position it (higher = lower in list)
    $links['user.logout']['weight'] = 10;
  }
}