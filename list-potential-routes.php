<?php
// list-potential-routes-final.php

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

$app_root = '/home/lcyc/d11.lcyc.info/web';
chdir($app_root);

$autoloader = require $app_root . '/../vendor/autoload.php';

$request = Request::createFromGlobals();
$kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod', FALSE);
$kernel->boot();

$container = $kernel->getContainer();

echo "Drupal kernel booted. Site Name: " . $container->get('config.factory')->get('system.site')->get('name') . "\n\n";

// Fresh module discovery
$container->get('extension.list.module')->reset();
$modules = $container->get('extension.list.module')->getList();

echo "Discovered " . count($modules) . " modules.\n\n";

// Manually collect the route collection in memory (no DB write)
$route_builder = $container->get('router.builder');

// Use the protected method via reflection to get the collection without committing
$reflection = new \ReflectionClass($route_builder);
$method = $reflection->getMethod('getRouteCollection');
$method->setAccessible(true);
$collection = $method->invoke($route_builder);

echo "Potential routes that would be built: " . $collection->count() . "\n";
echo str_repeat("-", 100) . "\n";

$i = 0;
foreach ($collection as $name => $route) {
  $path = $route->getPath();
  $defaults = $route->getDefaults();
  $controller = $defaults['_controller'] ?? $defaults['_form'] ?? $defaults['_entity_view'] ?? '(other)';
  if (strlen($controller) > 70) {
    $controller = substr($controller, 0, 67) . '...';
  }
  printf("%-60s %-50s %s\n", $name, $path, $controller);
  $i++;
  if ($i >= 50) {  // Limit to first 50 to avoid huge output; remove if you want all
    echo "... (truncated at 50 routes — total: " . $collection->count() . ")\n";
    break;
  }
}

echo str_repeat("-", 100) . "\n";
echo "Listing complete — NO changes made to database or router table.\n";
echo "If total is ~400–600+, module discovery and route collection are healthy.\n";
