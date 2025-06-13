<?php

namespace Drupal\book\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Determines if a user has access to print.
 */
class BookNodePrintAccessCheck implements AccessInterface {

  /**
   * Constructs a BookNodePrintAccessCheck object.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current logged-in user.
   */
  public function __construct(protected AccountInterface $currentUser) {
  }

  /**
   * Checks if user has permission to access print link.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($this->currentUser, 'access printer-friendly version');
  }

}
