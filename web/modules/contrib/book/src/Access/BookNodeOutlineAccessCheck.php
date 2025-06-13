<?php

namespace Drupal\book\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Determines if a node's outline settings can be accessed.
 */
class BookNodeOutlineAccessCheck implements AccessInterface {

  /**
   * Constructs a BookNodeOutlineAccessCheck object.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current logged-in user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   An immutable configuration object.
   */
  public function __construct(protected AccountInterface $currentUser, protected ConfigFactoryInterface $configFactory) {
  }

  /**
   * Checks if user has permission to access a node's book settings.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node requested to be removed from its book.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(NodeInterface $node): AccessResultInterface {
    // If content type is allowed book type, then check for 'add content to
    // books' permission.
    if (in_array($node->getType(), $this->configFactory->get('book.settings')->get('allowed_types'))) {
      return AccessResult::allowedIfHasPermission($this->currentUser, 'add content to books')
        ->orif(AccessResult::allowedIfHasPermission($this->currentUser, 'administer book outlines'));
    }
    // If content type is not allowed book type, then check additional
    // permissions and scenarios.
    else {
      if ($this->currentUser->hasPermission('add content to books') || $this->currentUser->hasPermission('administer book outlines')) {
        // If the user has the 'add content to books' permission and the node
        // is already in a book outline, then grant access. OR
        // If the user has the 'add content to books' and the 'add any content
        // to books' permissions, then grant access.
        if (!empty($node->book['bid']) && !$node->isNew() || $this->currentUser->hasPermission('add any content to books')) {
          return AccessResult::allowed();
        }
      }
    }
    return AccessResult::forbidden();
  }

}
