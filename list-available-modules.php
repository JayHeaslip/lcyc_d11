<?php
// list-available-modules.php

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

$app_root = '/home/lcyc/d11.lcyc.info/web';
chdir($app_root);

$autoloader = require $app_root . '/../vendor/autoload.php';

$request = Request::createFromGlobals();
$kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod', FALSE);
$kernel->boot();

$container = $kernel->getContainer();

echo "Drupal kernel successfully booted. Site Name: " . $container->get('config.factory')->get('system.site')->get('name') . "\n\n";

// Reset the module extension list to ensure fresh discovery
$container->get('extension.list.module')->reset();

echo "Fresh module discovery completed.\n\n";

// Get all discovered modules
$modules = $container->get('extension.list.module')->getList();

echo "Available modules (" . count($modules) . " total):\n";
echo str_repeat("-", 50) . "\n";

foreach ($modules as $name => $extension) {
  $path = $extension->getPath();
  $type = str_contains($path, 'core') ? 'core' : 'contrib/custom';
  echo sprintf("%-30s (%s)\n", $name, $type);
}

echo str_repeat("-", 50) . "\n";
echo "Listing complete — no changes were made to core.extension.\n";
echo "Current enabled modules (from config): " . count($container->get('config.factory')->get('core.extension')->get('module') ?: []) . "\n";