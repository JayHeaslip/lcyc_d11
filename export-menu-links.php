
<?php

use Drupal\Core\Serialization\Yaml;

/**
 * Export menu_link_content entities to JSON.
 */

$storage = \Drupal::entityTypeManager()
  ->getStorage('menu_link_content');

$links = $storage->loadMultiple();

$export = [
  'exported_at' => date('c'),
  'site' => \Drupal::request()->getSchemeAndHttpHost(),
  'menu_links' => [],
];

$fields = [
  'langcode',
  'enabled',
  'title',
  'description',
  'menu_name',
  'link',
  'weight',
  'expanded',
  'parent',
];

foreach ($links as $link) {
  $item = [
    'uuid' => $link->uuid(),
  ];

  foreach ($fields as $field) {
    if ($link->hasField($field)) {
      $item[$field] = $link->get($field)->getValue();
    }
  }

  $export['menu_links'][] = $item;
}

$file = DRUPAL_ROOT . '/menu-links-export.json';

file_put_contents(
  $file,
  json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo "Exported " . count($links) . " menu links to $file\n";
