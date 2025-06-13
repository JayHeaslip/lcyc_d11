<?php

namespace Drupal\book\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\book\BookManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for route access.
 */
class RouteAccessController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Parameter from route.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $node;

  /**
   * Constructs a book manager object and current node.
   *
   * @param \Drupal\book\BookManagerInterface $bookManager
   *   The BookManager service.
   * @param \Drupal\Core\Routing\RouteMatch $route_match
   *   The route match service.
   */
  public function __construct(
    protected BookManagerInterface $bookManager,
    protected RouteMatchInterface $route_match,
  ) {
    $node = $route_match->getParameter('node');
    if (!$node instanceof NodeInterface) {
      $node = $this->entityTypeManager()
        ->getStorage('node')
        ->load($node);
    }
    $this->node = $node;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('book.manager'),
      $container->get('current_route_match')
    );
  }

  /**
   * Checks access for a specific request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    // Checks if user has permission to reorder pages.
    $hasAccess = $account->hasPermission('reorder book pages');

    // Checks if book has children.
    $haveChildren = $this->checkIfBookHasChildren();

    if ($haveChildren) {
      // Checks if number of book page is more than one.
      $ifExceedsCount = $this->checkIfChildIsGreaterThanOne();
    }
    else {
      $ifExceedsCount = FALSE;
    }

    return AccessResult::allowedIf($hasAccess && $haveChildren && $ifExceedsCount);
  }

  /**
   * Checks if a book have children.
   */
  public function checkIfBookHasChildren(): bool {
    return (bool) ($this->node->book['has_children'] ?? FALSE);
  }

  /**
   * Checks if child of a book is more than one.
   */
  public function checkIfChildIsGreaterThanOne(): bool {
    $children = $this->bookManager->bookSubtreeData($this->node->book);
    $child = reset($children);

    return !empty($child['below']) && count($child['below']) > 1;
  }

}
