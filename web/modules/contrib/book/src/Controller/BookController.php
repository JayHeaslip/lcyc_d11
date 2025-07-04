<?php

namespace Drupal\book\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\book\BookExport;
use Drupal\book\BookManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller routines for book routes.
 */
class BookController extends ControllerBase {

  /**
   * Constructs a BookController object.
   *
   * @param \Drupal\book\BookManagerInterface $bookManager
   *   The book manager.
   * @param \Drupal\book\BookExport $bookExport
   *   The book export service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(
    protected BookManagerInterface $bookManager,
    protected BookExport $bookExport,
    protected RendererInterface $renderer,
  ) {
  }

  /**
   * Returns an administrative overview of all books.
   *
   * @return array
   *   A render array representing the administrative page content.
   */
  public function adminOverview(): array {
    $rows = [];

    $headers = [t('Book'), t('Operations')];
    // Add any recognized books to the table list.
    foreach ($this->bookManager->getAllBooks() as $book) {
      /** @var \Drupal\Core\Url $url */
      $url = $book['url'];
      if (isset($book['options'])) {
        $url->setOptions($book['options']);
      }
      $row = [
        Link::fromTextAndUrl($book['title'], $url),
      ];
      $links = [];
      $links['edit'] = [
        'title' => t('Edit order and titles'),
        'url' => Url::fromRoute('book.admin_edit', ['node' => $book['nid']]),
      ];
      $row[] = [
        'data' => [
          '#type' => 'operations',
          '#links' => $links,
        ],
      ];
      $rows[] = $row;
    }
    return [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => t('No books available.'),
    ];
  }

  /**
   * Prints a listing of all books.
   *
   * @return array
   *   A render array representing the listing of all books content.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function bookRender(): array {
    $book_list = [];
    foreach ($this->bookManager->getAllBooks() as $book) {
      $book_list[] = Link::fromTextAndUrl($book['title'], $book['url']);
    }
    return [
      '#theme' => 'item_list',
      '#items' => $book_list,
      '#cache' => [
        'tags' => $this->entityTypeManager()->getDefinition('node')->getListCacheTags(),
      ],
    ];
  }

  /**
   * Generates representations of a book page and its children.
   *
   * The method delegates the generation of output to helper methods. The method
   * name is derived by prepending 'bookExport' to the camelized form of given
   * output type. For example, a type of 'html' results in a call to the method
   * bookExportHtml().
   *
   * @param string $type
   *   A string encoding the type of output requested. The following types are
   *   currently supported in book module:
   *   - html: Printer-friendly HTML.
   *   Other types may be supported in contributed modules.
   * @param \Drupal\node\NodeInterface $node
   *   The node to export.
   *
   * @return \Drupal\Core\Cache\CacheableResponse
   *   A render array representing the node and its children in the book
   *   hierarchy in a format determined by the $type parameter.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function bookExport(string $type, NodeInterface $node): CacheableResponse {
    $method = 'bookExport' . Container::camelize($type);

    // @todo Convert the custom export functionality to serializer.
    if (!method_exists($this->bookExport, $method)) {
      $this->messenger()->addStatus(t('Unknown export format.'));
      throw new NotFoundHttpException();
    }

    if (!isset($node->book)) {
      $this->messenger()->addWarning(t('%title is not in a book and cannot be exported.', ['%title' => $node->label()]));
      throw new NotFoundHttpException();
    }

    $exported_book = $this->bookExport->{$method}($node);
    $rendered = $this->renderer->renderRoot($exported_book);
    $response = new CacheableResponse($rendered);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($exported_book));
    return $response;
  }

}
