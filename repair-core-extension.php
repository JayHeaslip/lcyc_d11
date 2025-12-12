<?php
// repair-core-extension.php  –  fixes broken core.extension config on Drupal 11
use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

$autoloader = require __DIR__ . '/vendor/autoload.php';
$kernel = DrupalKernel::createFromRequest(Request::createFromGlobals(), $autoloader, 'prod', FALSE);
$kernel->boot();

echo "Fixing corrupted core.extension config...\n";

$config = \Drupal::configFactory()->getEditable('core.extension');

// If it's broken it will be an object instead of an array – fix it
$data = $config->getRawData();
if (is_object($data)) {
    echo "  → core.extension was an object → converting to array\n";
    $config->setData((array) $data);
    $config->save();
    echo "  → Fixed and saved.\n";
} else {
    echo "  → core.extension is already an array – nothing to fix.\n";
}

echo "Rebuilding caches safely...\n";
// Safe cache clear that works even with broken core.extension
foreach (\Drupal::cache()->listBins() as $bin) {
    \Drupal::cache($bin)->deleteAll();
}
\Drupal::service('asset.css.collection.optimizer')->deleteAll();
\Drupal::service('asset.js.collection.optimizer')->deleteAll();

echo "\nDone! Log out and log back in. The admin toolbar username will appear again.\n";
