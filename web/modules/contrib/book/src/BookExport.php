<?php

namespace Drupal\book;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\node\NodeInterface;

/**
 * Provides methods for exporting book to different formats.
 *
 * If you would like to add another format, swap this class in container.
 */
class BookExport {

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $nodeStorage;

  /**
   * The node view builder.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected EntityViewBuilderInterface $viewBuilder;

  /**
   * Constructs a BookExport object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\book\BookManagerInterface $bookManager
   *   The book manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    protected BookManagerInterface $bookManager,
    protected EntityRepositoryInterface $entityRepository,
  ) {
    $this->nodeStorage = $entity_type_manager->getStorage('node');
    $this->viewBuilder = $entity_type_manager->getViewBuilder('node');
  }

  /**
   * Generates HTML for export when invoked by book_export().
   *
   * The given node is embedded to its absolute depth in a top level section.
   * For example, a child node with depth 2 in the hierarchy is contained in
   * (otherwise empty) <div> elements corresponding to depth 0 and depth 1.
   * This is intended to support WYSIWYG output; for instance, level 3 sections
   * always look like level 3 sections, no matter their depth relative to the
   * node selected to be exported as printer-friendly HTML.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to export.
   *
   * @return array
   *   A render array representing the HTML for a node and its children in the
   *   book hierarchy.
   *
   * @throws \Exception
   *   Thrown when the node was not attached to a book.
   */
  public function bookExportHtml(NodeInterface $node): array {
    if (!isset($node->book)) {
      throw new \Exception();
    }

    $tree = $this->bookManager->bookSubtreeData($node->book);
    $contents = $this->exportTraverse($tree, [$this, 'bookNodeExport']);
    $node = $this->entityRepository->getTranslationFromContext($node);
    $book_title = $this->nodeStorage->load($node->book['bid'])->label();
    return [
      '#theme' => 'book_export_html',
      '#title' => $node->label(),
      '#book_title' => $book_title,
      '#contents' => $contents,
      '#depth' => $node->book['depth'],
      '#cache' => [
        'tags' => $node->getEntityType()->getListCacheTags(),
      ],
    ];
  }

  /**
   * Traverses the book tree to build printable or exportable output.
   *
   * During the traversal, the callback is applied to each node and is called
   * recursively for each child of the node (in weight, title order).
   *
   * @param array $tree
   *   A subtree of the book menu hierarchy, rooted at the current page.
   * @param callable $callable
   *   A callback to be called upon visiting a node in the tree.
   *
   * @return array
   *   The render array generated in visiting each node.
   */
  protected function exportTraverse(array $tree, callable $callable): array {
    // If there is no valid callable, use the default callback.
    $callable = !empty($callable) ? $callable : [$this, 'bookNodeExport'];

    $build = [];
    foreach ($tree as $data) {
      // Access checking is already performed when building the tree.
      if ($node = $this->nodeStorage->load($data['link']['nid'])) {
        if ($node->isPublished()) {
          $node = $this->entityRepository->getTranslationFromContext($node);
          $children = $data['below'] ? $this->exportTraverse($data['below'], $callable) : '';
          $build[] = call_user_func($callable, $node, $children);
        }
      }
    }

    return $build;
  }

  /**
   * Generates printer-friendly HTML for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node that will be output.
   * @param string $children
   *   (optional) All the rendered child nodes within the current node. Defaults
   *   to an empty string.
   *
   * @return array
   *   A render array for the exported HTML of a given node.
   *
   * @see \Drupal\book\BookExport::exportTraverse()
   */
  protected function bookNodeExport(NodeInterface $node, $children = ''): array {
    $build = $this->viewBuilder->view($node, 'print');
    return [
      '#theme' => 'book_node_export_html',
      '#content' => $build,
      '#node' => $node,
      '#children' => $children,
    ];
  }

}
