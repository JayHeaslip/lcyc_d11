<?php

/**
 * generate the "member_emails" file on the rails site in the console using
 * load "dump_member_emails.rb"
 * put the result file in ~/lcyc.info directory
 * run the command like this:  drush php:script member_sync.php (set dry_run to FALSE to actually do the modifications
 */

/**
 * Synchronize the Drupal member role against a Rails member email list.
 *
 * @param string $csv_path
 *   Path to the Rails CSV export.
 * @param bool $dry_run
 *   If TRUE, report changes without saving them.
 *
 * @return array
 *   Summary of the synchronization.
 */
function sync_member_role_from_rails(
  string $csv_path,
  bool $dry_run = TRUE
): array {

  $role_id = 'member';

  // Make sure the file exists and is readable.
  if (!is_file($csv_path) || !is_readable($csv_path)) {
    throw new \RuntimeException(
      "Cannot read Rails member file: $csv_path"
    );
  }

  // Read the Rails member emails.
  $handle = fopen($csv_path, 'r');

  if (!$handle) {
    throw new \RuntimeException('Unable to open member file.');
  }

  $emails = [];
  $email_column = 0;
  $first_row = TRUE;
  $invalid_rows = [];

  while (($row = fgetcsv($handle)) !== FALSE) {

    if (empty($row)) {
      continue;
    }

    // Detect an optional email column header.
    if ($first_row) {
      $first_row = FALSE;

      $header = array_map(
        fn($value) => strtolower(trim($value)),
        $row
      );

      $header_index = array_search('email', $header, TRUE);

      if ($header_index !== FALSE) {
        $email_column = $header_index;
        continue;
      }
    }

    $email = trim($row[$email_column] ?? '');

    // Remove UTF-8 BOM if present.
    $email = preg_replace('/^\xEF\xBB\xBF/', '', $email);

    if ($email === '') {
      continue;
    }

    $email = strtolower($email);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $invalid_rows[] = $email;
      continue;
    }

    $emails[$email] = TRUE;
  }

  fclose($handle);

  // Never perform a potentially destructive sync with bad input.
  if (!empty($invalid_rows)) {
    throw new \RuntimeException(
      'Invalid email addresses found: ' .
      implode(', ', $invalid_rows)
    );
  }

  if (empty($emails)) {
    throw new \RuntimeException(
      'No valid member emails found. Nothing was changed.'
    );
  }

  // Load Drupal users.
  $storage = \Drupal::entityTypeManager()
    ->getStorage('user');

  $users = $storage->loadMultiple();

  $added = [];
  $removed = [];
  $unchanged = [];

  foreach ($users as $user) {

    $uid = $user->id();

    // Skip anonymous user.
    if ($uid == 0) {
      continue;
    }

    $email = strtolower(trim($user->getEmail()));

    $should_be_member = isset($emails[$email]);
    $is_member = $user->hasRole($role_id);

    // Add member role.
    if ($should_be_member && !$is_member) {

      $added[] = $email;

      if (!$dry_run) {
        $user->addRole($role_id);
        $user->save();
      }

    }

    // Remove member role.
    elseif (!$should_be_member && $is_member) {

      $removed[] = $email;

      if (!$dry_run) {
        $user->removeRole($role_id);
        $user->save();
      }

    }

    else {
      $unchanged[] = $email;
    }
  }

  return [
    'dry_run' => $dry_run,
    'rails_emails' => count($emails),
    'added' => $added,
    'removed' => $removed,
    'unchanged_count' => count($unchanged),
    'added_count' => count($added),
    'removed_count' => count($removed),
  ];
}

$result = sync_member_role_from_rails(
  __DIR__ . '/member_emails',
  TRUE
);

print_r($result);