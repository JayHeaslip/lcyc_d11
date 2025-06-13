<?php

namespace Drupal\book\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\book\BookManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Book navigation' block.
 */
#[Block(
  id: "book_navigation",
  admin_label: new TranslatableMarkup("Book navigation"),
  category: new TranslatableMarkup("Menus"),
)]
class BookNavigationBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new BookNavigationBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\book\BookManagerInterface $bookManager
   *   The book manager.
   * @param \Drupal\Core\Entity\EntityStorageInterface $nodeStorage
   *   The node storage.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected RouteMatchInterface $routeMatch,
    protected BookManagerInterface $bookManager,
    protected EntityStorageInterface $nodeStorage,
    protected AccountProxyInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('book.manager'),
      $container->get('entity_type.manager')->getStorage('node'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'block_mode' => "all pages",
      'use_top_level_title' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $options = [
      'all pages' => $this->t('Show block on all pages'),
      'book pages' => $this->t('Show block only on book pages'),
      'primary book page' => $this->t('Show block only on the top level book page'),
      'child book pages' => $this->t('Show block only on child book pages'),
    ];
    $form['book_block_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Book navigation block display'),
      '#options' => $options,
      '#default_value' => $this->configuration['block_mode'],
      '#description' => $this->t("If <em>Show block on all pages</em> is selected, the block will contain the automatically generated menus for all the site's books. If <em>Show block only on book pages</em> is selected, the block will contain only the one menu corresponding to the current page's book. In this case, if the current page is not in a book, no block will be displayed. The <em>Page specific visibility settings</em> or other visibility settings can be used in addition to selectively display this block."),
    ];
    $form['use_top_level_title'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use top-level page title as block title'),
      '#default_value' => $this->configuration['use_top_level_title'],
      '#states' => [
        'visible' => [':input[name="settings[book_block_mode]"]' => ['value' => 'book pages']],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['block_mode'] = $form_state->getValue('book_block_mode');
    $this->configuration['use_top_level_title'] = $form_state->getValue('use_top_level_title');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   */
  public function build(): array {
    $current_bid = 0;

    $node = $this->routeMatch->getParameter('node');
    if ($node instanceof NodeInterface && !empty($node->book['bid'])) {
      $current_bid = $node->book['bid'];
    }

    if ($this->configuration['block_mode'] == 'all pages') {
      $book_menus = [];
      $pseudo_tree = [0 => ['below' => FALSE]];
      $books = $this->bookManager->getAllBooks();
      foreach ($books as $book_id => $book) {
        if ($book['bid'] == $current_bid) {
          // If the current page is a node associated with a book, the menu
          // needs to be retrieved.
          $data = $this->bookManager->bookTreeAllData($node->book['bid'], $node->book);
          $book_menus[$book_id] = $this->bookManager->bookTreeOutput($data);
        }
        else {
          // Since we know we will only display a link to the top node, there
          // is no reason to run an additional menu tree query for each book.
          $book['in_active_trail'] = FALSE;
          // Check whether user can access the book link.
          $book_node = $this->nodeStorage->load($book['nid']);
          $book['access'] = $book_node->access('view');
          $pseudo_tree[0]['link'] = $book;
          $book_menus[$book_id] = $this->bookManager->bookTreeOutput($pseudo_tree);
        }
        $book_menus[$book_id] += [
          '#book_title' => $book['title'],
        ];
      }
      if ($book_menus) {
        return [
          '#theme' => 'book_all_books_block',
        ] + $book_menus;
      }
    }
    elseif ($current_bid) {
      // Only display this block when the user is browsing a book
      // and included unpublished books if the user has access.
      $query = $this->nodeStorage->getQuery()
        ->accessCheck()
        ->condition('nid', $node->book['bid'], '=');
      if (!$this->currentUser->hasPermission('view any unpublished content')) {
        $query->condition('status', NodeInterface::PUBLISHED);
      }
      $nid = $query->execute();

      // Only show the block if the user has view access for the top-level node.
      if ($nid) {
        $node = $this->routeMatch->getParameter('node');
        $current_nid = $node->id();
        $tree = $this->bookManager->bookTreeAllData($node->book['bid'], $node->book);

        // There should only be one element at the top level.
        $data = array_shift($tree);
        $primary_book_nid = array_pop($nid);
        if (!empty($data['below'])) {
          $below = $this->bookManager->bookTreeOutput($data['below']);

          if ($this->configuration['block_mode'] == 'primary book page') {
            if ($current_nid === $primary_book_nid) {
              return $below;
            }
          }
          elseif ($this->configuration['block_mode'] == 'child book pages') {
            if ($current_nid !== $primary_book_nid) {
              return $below;
            }
          }
          else {
            // All book pages.
            if (!empty($below)) {
              if ($this->configuration['use_top_level_title']) {
                $below['#title'] = $data['link']['title'];
              }
              return $below;
            }
          }
        }
      }
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    // ::build() varies by the "book navigation" cache context.
    // Additional cache contexts, e.g. those that determine link text or
    // accessibility of a menu, will be bubbled automatically.
    return Cache::mergeContexts(parent::getCacheContexts(), [
      'route.book_navigation',
      'user.node_grants:view',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), [
      'node_list',
      'book_settings',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    if ($this->configuration['block_mode'] != 'all pages') {
      $node = $this->routeMatch->getParameter('node');
      if (!$node || empty($node->book['bid'])) {
        return AccessResult::forbidden();
      }
    }
    return AccessResult::allowed();
  }

}
