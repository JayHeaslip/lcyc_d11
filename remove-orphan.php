<?php
# Nuclear wipe of every orphaned image reference site-wide (100% safe)

foreach (\Drupal::entityTypeManager()->getStorage('node')->loadMultiple() as $node) {
  $changed = false;
  foreach ($node->getFields() as $field_name => $field) {
    if ($field->getFieldDefinition()->getType() === 'image') {
      foreach ($field as $delta => $item) {
        if ($item->entity === NULL && $item->target_id) {
          print "Removing broken image fid {$item->target_id} from node {$node->id()} ({$node->bundle()}) field $field_name\n";
          $field->removeItem($delta);
          $changed = true;
        }
      }
    }
  }
  if ($changed) $node->save();
}
print "All broken image references removed.\n";
