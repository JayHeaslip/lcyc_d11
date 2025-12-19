<?php

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

// 1. Define the app root and change directory
$app_root = '/home/lcyc/d11.lcyc.info/web'; // web directory contains index.php
chdir($app_root);

// 2. Load the autoloader
$autoloader = require $app_root . '/../vendor/autoload.php';  // Standard Composer layout

// 3. Create the request and kernel
$request = Request::createFromGlobals();
$kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod', FALSE);

// 4. Boot the kernel
$kernel->boot();

// 5. Success Check
$container = $kernel->getContainer();
echo "Drupal kernel successfully booted. Site Name: " . $container->get('config.factory')->get('system.site')->get('name') . "\n";

// ... Your custom logic ...
$trusted = $container->get('settings')->get('trusted_host_patterns') ?: [];
echo "Effective trusted_host_patterns: " . print_r($trusted, TRUE) . "\n";

// Light recovery nudge
//$container->get('module_handler')->reload();
//$container->get('router.builder')->rebuildIfNeeded();
//if ($container->has('cache.render')) {
//    $container->get('cache.render')->invalidateAll();
//}
//echo "Light rebuild complete - ready for browser auto-recovery.\n";
////// Force module discovery → recreates core.extension if missing
////$container->get('module_handler')->reload();
////echo "Module list reloaded — core.extension recreated if it was missing.\n";
////
////// Rebuild the router (this populates the router table with all routes)
////$container->get('router.builder')->rebuildIfNeeded();
////echo "Router rebuilt.\n";
////
////// Optional: Invalidate render cache
////if ($container->has('cache.render')) {
////    $container->get('cache.render')->invalidateAll();
////    echo "Render cache invalidated.\n";
////}
////
////// Optional: Clear compiled container and Twig (safe to do again)
////$site_path = $container->get('site.path'); // e.g. sites/default
////$files_dir = $app_root . '/' . $site_path . '/files';
////array_map('unlink', glob($files_dir . '/php_storage/*') ?: []);
////array_map('unlink', glob($files_dir . '/twig/*') ?: []);
////echo "Compiled container and Twig cache cleared.\n";

////echo "\n=== ALL DONE ===\n";
////echo "You should now have a valid core.extension row and hundreds of routes in the database.\n";
////echo "Visit https://d11.lcyc.info/ in your browser (preferably incognito mode).\n";
////echo "The site should load normally. Log in as user 1, then clear caches at /admin/config/development/performance.\n";
////echo "Finally, tighten your trusted_host_patterns in settings.php.\n";