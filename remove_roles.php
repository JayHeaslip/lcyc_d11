<?php

use Drupal\user\Entity\User;

$emails = [
  "dennis.w.manion@gmail.com",
  "miro@sales.northsails.com",
  "skipmcclellan@gmail.com"
  // Add more emails here
];

foreach ($emails as $email) {
  $users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' => $email]);
  $user = reset($users);

  if ($user) {
    if ($user->hasRole('member')) {
      try {
        $user->removeRole('member');
        $user->save();
        print "Removed 'member' role from user with email: $email (UID: " . $user->id() . ")\n";
      } catch (\Exception $e) {
        print "Error removing role for email $email: " . $e->getMessage() . "\n";
      }
    } else {
      print "User with email $email does not have the 'member' role.\n";
    }
  } else {
    print "No user found with email: $email\n";
  }
}