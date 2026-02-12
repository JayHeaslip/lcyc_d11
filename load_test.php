<?php

use Drupal\user\Entity\User;

$uid = 3291;
$user = User::load($uid);

if ($user) {
  print "SUCCESS: User loaded!\n";
  print "UID: " . $user->id() . "\n";
  print "Name: " . $user->getDisplayName() . "\n";
  print "Email: " . $user->getEmail() . "\n";
  print "Active: " . ($user->isActive() ? 'Yes' : 'No') . "\n";
  $roles = $user->getRoles(TRUE);
  print "Roles: " . implode(', ', $roles) . "\n";
  print "Has 'member' role: " . ($user->hasRole('member') ? 'YES' : 'NO') . "\n";
} else {
  print "FAIL: User::load($uid) returned NULL\n";
}
