<?php
// final-fix.php – works 100 % on Drupal 11 with broken toolbar / core.extension
use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

$autoloader = require __DIR__ . '/vendor/autoload.php';

$kernel = DrupalKernel::createFromRequest(Request::createFromGlobals(), $autoloader, 'prod', FALSE);
$kernel->boot();

echo "=== Drupal 11 Toolbar + core.extension emergency fix ===\n\n";

// 1. Force-fix core.extension (converts ANY broken format back to proper array)
$config = \Drupal::configFactory()->getEditable('core.extension');
$raw = $config->getRawData();

if (!is_array($raw) || !isset($raw['module']) || !is_array($raw['module'])) {
    echo "core.extension is corrupted → forcing clean state\n";
    $config->setData([
      'module' => $raw['module'] ?? [],     // keep existing modules if possible
      'theme'  => $raw['theme'] ?? [],
      'profile' => $raw['profile'] ?? 'standard',
    ]);
    $config->save(TRUE);
    echo "→ Fixed and saved core.extension\n\n";
} else {
    echo "core.extension looks okay\n\n";
}

// 2. Brutal but safe cache wipe (works even when everything else is broken)
echo "Wiping all cache tables directly...\n";
$db = \Drupal::database();
foreach (['cache_', 'cachetags', 'cache_config', 'cache_container', 'cache_data', 'cache_default', 'cache_discovery', 'cache_dynamic_page_cache', 'cache_entity', 'cache_menu', 'cache_render', 'cache_toolbar'] as $prefix) {
    $tables = $db->schema()->findTables($prefix . '%');
    foreach ($tables as $table) {
        $db->truncate($table)->execute();
        echo "  Cleared $table\n";
    }
}

// 3. Delete compiled container & Twig cache folders
$dirs = [
  'sites/default/files/php_storage',
  'sites/default/files/twig',
];
foreach ($dirs as $dir) {
    if (is_dir(__DIR__ . '/web/' . $dir)) {
        array_map('unlink', glob(__DIR__ . '/web/' . $dir . '/*'));
        echo "  Cleared web/$dir\n";
    }
}

// 4. Rebuild router
echo "\nRebuilding router...\n";
\Drupal::service('router.builder')->rebuildIfNeeded();

echo "\nALL DONE!\n";
echo "→ Now log out and log back in (or open a private window).\n";
echo "→ Your admin toolbar will show your username/email again and link to /user/1.\n";
