<?php
/**
 * @file
 * fix-toolbar.php
 *
 * Place this file in the Drupal root (same folder as composer.json)
 * Run with:   php fix-toolbar.php
 */

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

// Correct way to bootstrap Drupal 11 (and Drupal 10.3+) when autoloader.php is gone
$autoloader = require __DIR__ . '/vendor/autoload.php';

$kernel = DrupalKernel::createFromRequest(Request::createFromGlobals(), $autoloader, 'prod', FALSE);
$kernel->boot();
$kernel->preHandle(Request::createFromGlobals());

// Now we are fully inside Drupal
echo "Removing leftover menu link overrides …\n";

$config = \Drupal::configFactory()->getEditable('core.menu.static_menu_link_overrides');

$keys_removed = 0;
$keys = [
  'overrides.user.logout',
  'overrides.my_module.user_admin_email_link',
  'overrides.my_module.user_main_email_link',
  'overrides.my_module.user_email_link',           // just in case
];

foreach ($keys as $key) {
  if ($config->get($key) !== NULL) {
    $config->clear($key);
    echo "  Removed: $key\n";
    $keys_removed++;
  }
}

if ($keys_removed > 0) {
  $config->save();
  echo "$keys_removed override(s) removed and config saved.\n";
}
else {
  echo "No matching overrides found (already clean or different keys).\n";
}

echo "Rebuilding all caches …\n";
drupal_flush_all_caches();

echo "\nDone! The admin toolbar username should now appear again.\n";
echo "→ Log out and log back in (or use an incognito window) to see the fix.\n";
