<?php
$entity = \Drupal::entityTypeManager()->getStorage("node")->load(1293); // <-- your NID
if ($entity && $entity->hasField("field_race_committee")) {
  $field = $entity->get("field_race_committee")->first();
  $field->set("open_date", NULL);   // NULL = completely cleared
  $entity->save();
  print "Open date cleared for node " . $entity->id() . PHP_EOL;
} else {
  print "Node not found" . PHP_EOL;
}
