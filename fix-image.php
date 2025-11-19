<?php

// Fix ALL broken default_image references site-wide (Drupal 11 safe)
// Run with: drush scr ../fix-image-defaults.php

$offenders = [];

// 1. Fix field.storage.* (the most common culprit)
foreach (\Drupal::configFactory()->listAll('field.storage.') as $name) {
  $config = \Drupal::configFactory()->getEditable($name);
  if ($config->get('type') === 'image') {
    $old = $config->get('settings.default_image.uuid') ?? 'none';
    $config->set('settings.default_image', [
      'uuid' => NULL,
      'alt' => '',
      'title' => '',
      'width' => NULL,
      'height' => NULL,
    ]);
    $config->set('default_value', []);
    $config->save();
    $offenders[] = "$name → fixed storage default_image (was $old)";
  }
}

// 2. Fix field.field.* (the per-bundle ones)
foreach (\Drupal::configFactory()->listAll('field.field.') as $name) {
  $config = \Drupal::configFactory()->getEditable($name);
  if ($config->get('field_type') === 'image') {
    $old = $config->get('default_image.uuid') ?? 'none';
    $config->set('default_image', [
      'uuid' => NULL,
      'alt' => '',
      'title' => '',
      'width' => NULL,
      'height' => NULL,
    ]);
    $config->save();
    $offenders[] = "$name → fixed field default_image (was $old)";
  }
}

print "Fixed " . count($offenders) . " image field configurations:\n";
print implode("\n", $offenders) . "\n";
print "All done – run `drush cr` now.\n";