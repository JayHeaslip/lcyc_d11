
<?php

/**
 * Import menu_link_content entities from JSON.
 */

$file = DRUPAL_ROOT . '/menu-links-export.json';

if (!file_exists($file)) {
  throw new \RuntimeException("Export file not found: $file");
}

$data = json_decode(file_get_contents($file), TRUE);

if (!$data || !isset($data['menu_links'])) {
  throw new \RuntimeException("Invalid menu export file.");
}

$storage = \Drupal::entityTypeManager()
  ->getStorage('menu_link_content');

$created = 0;
$updated = 0;
$imported = [];

$fields = [
  'langcode',
  'enabled',
  'title',
  'description',
  'menu_name',
  'link',
  'weight',
  'expanded',
];

//
// Pass 1: Create/update entities without parent references.
//

foreach ($data['menu_links'] as $item) {

  $existing = $storage->loadByProperties([
    'uuid' => $item['uuid'],
  ]);

  $entity = $existing
    ? reset($existing)
    : $storage->create([
        'uuid' => $item['uuid'],
      ]);

  $is_new = $entity->isNew();

  foreach ($fields as $field) {
    if (isset($item[$field])) {
      $entity->set($field, $item[$field]);
    }
  }

  if ($entity->hasField('parent')) {
    $entity->set('parent', '');
  }

  $entity->save();

  $is_new ? $created++ : $updated++;

  $imported[$item['uuid']] = [
    'entity' => $entity,
    'parent' => $item['parent'] ?? [],
  ];
}

//
// Pass 2: Restore parent relationships.
//

foreach ($imported as $item) {
  $entity = $item['entity'];

  if ($entity->hasField('parent')) {
    $entity->set('parent', $item['parent']);
    $entity->save();
  }
}

\Drupal::service('plugin.manager.menu.link')
  ->rebuild();

\Drupal::service('cache_tags.invalidator')
  ->invalidateTags(['config:system.menu']);

\Drupal::service('cache.render')->invalidateAll();

echo "Menu import complete.\n";
echo "Created: $created\n";
echo "Updated: $updated\n";
echo "Total: " . count($imported) . "\n";
