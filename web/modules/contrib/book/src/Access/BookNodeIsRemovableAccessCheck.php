<?php

namespace Drupal\book\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\book\BookManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Determines whether the requested node can be removed from its book.
 */
class BookNodeIsRemovableAccessCheck implements AccessInterface {

  /**
   * Constructs a BookNodeIsRemovableAccessCheck object.
   *
   * @param \Drupal\book\BookManagerInterface $bookManager
   *   Book Manager Service.
   */
  public function __construct(protected BookManagerInterface $bookManager) {
  }

  /**
   * Checks access for removing the node from its book.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node requested to be removed from its book.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(NodeInterface $node): AccessResultInterface {
    return AccessResult::allowedIf($this->bookManager->checkNodeIsRemovable($node))->addCacheableDependency($node);
  }

}
