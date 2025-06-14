<?php

namespace Drupal\book;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

// cspell:ignore plid

/**
 * Defines a book manager.
 */
class BookManager implements BookManagerInterface {
  use StringTranslationTrait;

  /**
   * Defines the maximum supported depth of the book tree.
   */
  const BOOK_MAX_DEPTH = 9;

  /**
   * Books Array.
   *
   * @var array|null
   */
  protected ?array $books;

  /**
   * Stores flattened book trees.
   *
   * @var array
   */
  protected array $bookTreeFlattened;

  /**
   * Constructs a BookManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The string translation service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\book\BookOutlineStorageInterface $bookOutlineStorage
   *   The book outline storage.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $backendChainedCache
   *   The book chained backend cache service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $memoryCache
   *   The book memory cache service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TranslationInterface $translation,
    protected ConfigFactoryInterface $configFactory,
    protected BookOutlineStorageInterface $bookOutlineStorage,
    protected RendererInterface $renderer,
    protected LanguageManagerInterface $languageManager,
    protected EntityRepositoryInterface $entityRepository,
    protected CacheBackendInterface $backendChainedCache,
    protected CacheBackendInterface $memoryCache,
    protected RouteMatchInterface $route_match,
  ) {
    $this->stringTranslation = $translation;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllBooks(): array {
    if (!isset($this->books)) {
      $this->loadBooks();
    }
    // Sorts the Book list.
    $sort_element = $this->configFactory->get('book.settings')->get('book_sort') === 'title' ? 'sortByTitleElement' : 'sortByWeightElement';
    uasort($this->books, ['Drupal\Component\Utility\SortArray', $sort_element]);
    return $this->books;
  }

  /**
   * Loads Books Array.
   */
  protected function loadBooks(): void {
    $this->books = [];
    $nids = $this->bookOutlineStorage->getBooks();

    if ($nids) {
      $book_links = $this->bookOutlineStorage->loadMultiple($nids);
      // Load nodes with proper translation.
      $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
      $nodes = array_map([$this->entityRepository, 'getTranslationFromContext'], $nodes);
      // @todo Sort by weight and translated title.
      // @todo use route name for links, not system path.
      foreach ($book_links as $link) {
        $nid = $link['nid'];
        if (isset($nodes[$nid]) && $nodes[$nid]->access('view')) {
          $link['url'] = $nodes[$nid]->toUrl();
          $link['title'] = $nodes[$nid]->label();
          $link['type'] = $nodes[$nid]->bundle();
          $this->books[$link['bid']] = $link;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLinkDefaults(int|string $nid): array {
    return [
      'original_bid' => 0,
      'nid' => $nid,
      'bid' => 0,
      'pid' => 0,
      'has_children' => 0,
      'weight' => 0,
      'options' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getParentDepthLimit(array $book_link): int {
    return static::BOOK_MAX_DEPTH - 1 - (($book_link['bid'] && $book_link['has_children']) ? $this->findChildrenRelativeDepth($book_link) : 0);
  }

  /**
   * Determine the relative depth of the children of a given book link.
   *
   * @param array $book_link
   *   The book link.
   *
   * @return int
   *   The difference between the max depth in the book tree and the depth of
   *   the passed book link.
   */
  protected function findChildrenRelativeDepth(array $book_link): int {
    $max_depth = $this->bookOutlineStorage->getChildRelativeDepth($book_link, static::BOOK_MAX_DEPTH);
    return ($max_depth > $book_link['depth']) ? $max_depth - $book_link['depth'] : 0;
  }

  /**
   * {@inheritdoc}
   */
  public function addFormElements(array $form, FormStateInterface $form_state, NodeInterface $node, AccountInterface $account, bool $collapsed = TRUE): array {
    // If the form is being processed during the Ajax callback of our book bid
    // dropdown, then $form_state will hold the value that was selected.
    if ($form_state->hasValue('book')) {
      $node->book = $form_state->getValue('book');
    }
    $form['book'] = [
      '#type' => 'details',
      '#title' => $this->t('Book outline'),
      '#weight' => 10,
      '#open' => !$collapsed,
      '#group' => 'advanced',
      '#attributes' => [
        'class' => ['book-outline-form'],
      ],
      '#attached' => [
        'library' => ['book/drupal.book'],
      ],
      '#tree' => TRUE,
    ];
    foreach (['nid', 'has_children', 'original_bid', 'parent_depth_limit'] as $key) {
      $form['book'][$key] = [
        '#type' => 'value',
        '#value' => $node->book[$key],
      ];
    }

    $form['book']['pid'] = $this->addParentSelectFormElements($node->book);

    // @see \Drupal\book\Form\BookAdminEditForm::bookAdminTableTree(). The
    // weight may be larger than 50.
    $form['book']['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight'),
      '#default_value' => $node->book['weight'],
      '#delta' => max(50, abs($node->book['weight'])),
      '#weight' => 5,
      '#description' => $this->t('Pages at a given level are ordered first by weight and then by title.'),
    ];
    $options = [];
    $nid = !$node->isNew() ? $node->id() : 'new';
    if ($node->id() && ($nid == $node->book['original_bid']) && ($node->book['parent_depth_limit'] == 0)) {
      // This is the top level node in a maximum depth book and thus cannot be
      // moved.
      $options[$node->id()] = $node->label();
    }
    else {
      foreach ($this->getAllBooks() as $book) {
        $options[$book['nid']] = $book['title'];
      }
    }

    if ($account->hasPermission('create new books') && ($nid == 'new' || ($nid != $node->book['original_bid']))) {
      // The node can become a new book, if it is not one already.
      $options = [$nid => $this->t('- Create a new book -')] + $options;
    }
    if (!$node->book['bid'] || $nid === 'new' || $node->book['original_bid'] === 0) {
      // The node is not currently in the hierarchy.
      $options = [0 => $this->t('- None -')] + $options;
    }

    // Add a drop-down to select the destination book.
    $form['book']['bid'] = [
      '#type' => 'select',
      '#title' => $this->t('Book'),
      '#default_value' => $node->book['bid'],
      '#options' => $options,
      '#access' => (bool) $options,
      '#description' => $this->t('Your page will be a part of the selected book.'),
      '#weight' => -5,
      '#attributes' => ['class' => ['book-title-select']],
      '#ajax' => [
        'callback' => 'book_form_update',
        'wrapper' => 'edit-book-plid-wrapper',
        'effect' => 'fade',
        'speed' => 'fast',
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function checkNodeIsRemovable(NodeInterface $node): bool {
    return (!empty($node->book['bid']) && (($node->book['bid'] != $node->id()) || !$node->book['has_children']));
  }

  /**
   * {@inheritdoc}
   */
  public function updateOutline(NodeInterface $node): bool {
    if (empty($node->book['bid'])) {
      return FALSE;
    }

    if (!empty($node->book['bid'])) {
      if ($node->book['bid'] == 'new') {
        // New nodes that are their own book.
        $node->book['bid'] = $node->id();
      }
      elseif (!isset($node->book['original_bid'])) {
        $node->book['original_bid'] = $node->book['bid'];
      }
    }

    // Ensure we create a new book link if either the node itself is new, or the
    // bid was selected the first time, so that the original_bid is still empty.
    $new = empty($node->book['nid']) || empty($node->book['original_bid']);

    $node->book['nid'] = $node->id();

    // Create a new book from a node.
    if ($node->book['bid'] == $node->id()) {
      $node->book['pid'] = 0;
    }
    elseif ($node->book['pid'] < 0) {
      // -1 is the default value in BookManager::addParentSelectFormElements().
      // The node save should have set the bid equal to the node ID, but
      // handle it here if it did not.
      $node->book['pid'] = $node->book['bid'];
    }

    // Prevent changes to the book outline if the node being saved is not the
    // default revision.
    $updated = FALSE;
    if (!$new) {
      $original = $this->loadBookLink($node->id(), FALSE);
      if ($node->book['bid'] != $original['bid'] || $node->book['pid'] != $original['pid'] || $node->book['weight'] != $original['weight']) {
        $updated = TRUE;
      }
    }
    if (($new || $updated) && !$node->isDefaultRevision()) {
      return FALSE;
    }

    return !empty($this->saveBookLink($node->book, $new));
  }

  /**
   * {@inheritdoc}
   */
  public function getBookParents(array $item, array $parent = []): array {
    $book = [];
    if ($item['pid'] == 0) {
      $book['p1'] = $item['nid'];
      for ($i = 2; $i <= static::BOOK_MAX_DEPTH; $i++) {
        $parent_property = "p$i";
        $book[$parent_property] = 0;
      }
      $book['depth'] = 1;
    }
    else {
      $i = 1;
      $book['depth'] = $parent['depth'] + 1;
      while ($i < $book['depth']) {
        $p = 'p' . $i++;
        $book[$p] = $parent[$p];
      }
      $p = 'p' . $i++;
      // The parent (p1 - p9) corresponding to the depth always equals the nid.
      $book[$p] = $item['nid'];
      while ($i <= static::BOOK_MAX_DEPTH) {
        $p = 'p' . $i++;
        $book[$p] = 0;
      }
    }
    return $book;
  }

  /**
   * Builds the parent selection form element for the node form or outline tab.
   *
   * This function is also called when generating a new set of options during
   * the Ajax callback, so an array is returned that can be used to replace an
   * existing form element.
   *
   * @param array $book_link
   *   A fully loaded book link that is part of the book hierarchy.
   *
   * @return array
   *   A parent selection form element.
   */
  protected function addParentSelectFormElements(array $book_link): array {
    $config = $this->configFactory->get('book.settings');
    if ($config->get('override_parent_selector')) {
      return [];
    }
    // Offer a message or a drop-down to choose a different parent page.
    $form = [
      '#type' => 'hidden',
      '#value' => -1,
      '#prefix' => '<div id="edit-book-plid-wrapper">',
      '#suffix' => '</div>',
    ];

    if ($book_link['nid'] === $book_link['bid']) {
      // This is a book - at the top level.
      if ($book_link['original_bid'] === $book_link['bid']) {
        $form['#prefix'] .= '<em>' . $this->t('This is the top-level page in this book.') . '</em>';
      }
      else {
        $form['#prefix'] .= '<em>' . $this->t('This will be the top-level page in this book.') . '</em>';
      }
    }
    elseif (!$book_link['bid']) {
      $form['#prefix'] .= '<em>' . $this->t('No book selected.') . '</em>';
    }
    else {
      $form = [
        '#type' => 'select',
        '#title' => $this->t('Parent item'),
        '#default_value' => $book_link['pid'],
        '#description' => $this->t('The parent page in the book. The maximum depth for a book and all child pages is @maxdepth. Some pages in the selected book may not be available as parents if selecting them would exceed this limit.', ['@maxdepth' => static::BOOK_MAX_DEPTH]),
        '#options' => $this->getTableOfContents($book_link['bid'], $book_link['parent_depth_limit'], [$book_link['nid']]),
        '#attributes' => ['class' => ['book-title-select']],
        '#prefix' => '<div id="edit-book-plid-wrapper">',
        '#suffix' => '</div>',
      ];
    }
    $this->renderer->addCacheableDependency($form, $config);

    return $form;
  }

  /**
   * Recursively processes and formats book links for getTableOfContents().
   *
   * This helper function recursively modifies the table of contents arrays for
   * each item in the book tree, ignoring items in the exclude array or at a
   * depth greater than the limit. Truncates titles over thirty characters and
   * appends an indentation string incremented by depth.
   *
   * @param array $tree
   *   The data structure of the book's outline tree. Includes hidden links.
   * @param string $indent
   *   A string appended to each node title. Increments by '--' per depth
   *   level.
   * @param array $toc
   *   Reference to the table of contents arrays. This is modified in place, so
   *   the function does not have a return value.
   * @param array $exclude
   *   Optional array of Node ID values. Any link whose node ID is in this
   *   array will be excluded (along with its children).
   * @param int $depth_limit
   *   Any link deeper than this value will be excluded (along with its
   *   children).
   * @param bool $truncate_title
   *   (optional) Flag to indicate if title should be truncated.
   *   Defaults to TRUE.
   */
  protected function recurseTableOfContents(array $tree, string $indent, array &$toc, array $exclude, int $depth_limit, bool $truncate_title = TRUE): void {
    $nids = [];
    foreach ($tree as $data) {
      if ($data['link']['depth'] > $depth_limit) {
        // Don't iterate through any links on this level.
        return;
      }
      if (!in_array($data['link']['nid'], $exclude)) {
        $nids[] = $data['link']['nid'];
      }
    }

    // Load nodes with proper translation.
    try {
      $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
      $nodes = array_map([
        $this->entityRepository,
        'getTranslationFromContext',
      ], $nodes);

      foreach ($tree as $data) {
        $nid = $data['link']['nid'];
        // Check for excluded or missing node.
        if (empty($nodes[$nid])) {
          continue;
        }
        if ($truncate_title) {
          $toc[$nid] = $indent . ' ' . Unicode::truncate($nodes[$nid]->label(), 30, TRUE, TRUE);
        }
        else {
          $toc[$nid] = $indent . ' ' . $nodes[$nid]->label();
        }
        if ($data['below']) {
          $this->recurseTableOfContents($data['below'], $indent . '--', $toc, $exclude, $depth_limit);
        }
      }
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTableOfContents(int|string $bid, int $depth_limit, array $exclude = [], bool $truncate = TRUE): array {
    $tree = $this->bookTreeAllData($bid);
    $toc = [];
    $this->recurseTableOfContents($tree, '', $toc, $exclude, $depth_limit, $truncate);

    return $toc;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteFromBook(int $nid): void {
    $original = $this->loadBookLink($nid, FALSE);
    $this->bookOutlineStorage->delete($nid);

    try {
      // Handle deletion of a top-level post.
      $result = $this->bookOutlineStorage->loadBookChildren($nid);
      if ($nid == $original['bid']) {
        $children = $this->entityTypeManager->getStorage('node')->loadMultiple(array_keys($result));

        foreach ($children as $child) {
          $child->book['bid'] = $child->id();
          $this->updateOutline($child);
        }
      }
      if (count($result) > 0) {
        // Handle children reroute.
        $children = $this->entityTypeManager->getStorage('node')
          ->loadMultiple(array_keys($result));
        foreach ($children as $child) {
          $child->book['pid'] = $original['pid'];
          $this->updateOutline($child);
        }
      }
      $this->updateOriginalParent($original);
      $this->books = NULL;
      Cache::invalidateTags(['bid:' . $original['bid']]);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
    }
  }

  /**
   * {@inheritdoc}
   */
  public function bookTreeAllData(int $bid, ?array $link = NULL, ?int $max_depth = NULL, ?int $min_depth = NULL): array {
    // Use $nid as flag for whether the data being loaded is for the whole tree.
    $nid = $link['nid'] ?? 0;
    $langcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();

    // Create a cache ID for the given $nid, $link, $langcode, $min_depth and
    // $max_depth.
    $cid = implode(':', ['book-links', $bid, $nid, $langcode, (int) $min_depth, (int) $max_depth]);

    // Get it from cache, if available.
    if ($cache = $this->memoryCache->get($cid)) {
      return $cache->data;
    }

    // If the tree data was not in the static cache, build $tree_parameters.
    $tree_parameters = [
      'min_depth' => $min_depth,
      'max_depth' => $max_depth,
    ];
    if ($nid) {
      $active_trail = $this->getActiveTrailIds($bid, $link);
      $tree_parameters['expanded'] = $active_trail;
      $tree_parameters['active_trail'] = $active_trail;
      $tree_parameters['active_trail'][] = $nid;
    }

    // Build the tree using the parameters.
    $tree_build = $this->bookTreeBuild($bid, $tree_parameters);

    // Cache the tree build in memory.
    $this->memoryCache->set($cid, $tree_build);

    return $tree_build;
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveTrailIds(string $bid, array $link): array {
    // The tree is for a single item, so we need to match the values in its
    // p columns and 0 (the top level) with the plid values of other links.
    $active_trail = [0];
    for ($i = 1; $i < static::BOOK_MAX_DEPTH; $i++) {
      if (!empty($link["p$i"])) {
        $active_trail[] = $link["p$i"];
      }
    }
    return $active_trail;
  }

  /**
   * {@inheritdoc}
   */
  public function bookTreeOutput(array $tree): array {
    $items = $this->buildItems($tree);

    $build = [];

    if ($items) {
      // Make sure Drupal\Core\Render\Element::children() does not re-order the
      // links.
      $build['#sorted'] = TRUE;
      // Get the book id from the last link.
      $item = end($items);
      // Add the theme wrapper for outer markup.
      // Allow menu-specific theme overrides.
      $build['#theme'] = 'book_tree__book_toc_' . $item['original_link']['bid'];
      $build['#items'] = $items;
      // Set cache tag.
      $build['#cache']['tags'][] = 'config:system.book.' . $item['original_link']['bid'];
    }

    return $build;
  }

  /**
   * Builds the #items property for a book tree's renderable array.
   *
   * Helper function for ::bookTreeOutput().
   *
   * @param array $tree
   *   A data structure representing the tree.
   *
   * @return array
   *   The value to use for the #items property of a renderable menu.
   */
  protected function buildItems(array $tree): array {
    $items = [];

    foreach ($tree as $data) {
      $element = [];

      // Generally we only deal with visible links, but just in case.
      if (!$data['link']['access']) {
        continue;
      }
      // Set a class for the <li> tag. Since $data['below'] may contain local
      // tasks, only set 'expanded' to true if the link also has children within
      // the current book.
      $element['is_expanded'] = FALSE;
      $element['is_collapsed'] = FALSE;
      if ($data['link']['has_children'] && $data['below']) {
        $element['is_expanded'] = TRUE;
      }
      elseif ($data['link']['has_children']) {
        $element['is_collapsed'] = TRUE;
      }
      // Set a helper variable to indicate whether the link is in the active
      // trail.
      $element['in_active_trail'] = FALSE;
      if ($data['link']['in_active_trail']) {
        $element['in_active_trail'] = TRUE;
      }

      // Allow book-specific theme overrides.
      $element['attributes'] = new Attribute();
      $element['title'] = $data['link']['title'];
      $route = 'entity.node.canonical';
      $route_parameters = [
        'node' => $data['link']['nid'],
      ];
      $route_options = [
        'language' => $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT),
      ];
      $element['url'] = Url::fromRoute($route, $route_parameters, $route_options);
      $node = $this->route_match->getParameter('node');
      // Check if item belongs to the current page.
      if ($node instanceof NodeInterface && $node->id() === $data['link']['nid']) {
        $link_options = [
          'attributes' => [
            'class' => [
              'is-active',
            ],
          ],
        ];
        $element['url']->setOptions($link_options);
      }

      $element['localized_options'] = !empty($data['link']['localized_options']) ? $data['link']['localized_options'] : [];
      $element['localized_options']['set_active_class'] = TRUE;
      $element['below'] = $data['below'] ? $this->buildItems($data['below']) : [];

      $element['original_link'] = $data['link'];
      // Index using the link's unique nid.
      $items[$data['link']['nid']] = $element;
    }

    return $items;
  }

  /**
   * Builds a book tree, translates links, and checks access.
   *
   * @param int|string $bid
   *   The Book ID to find links for.
   * @param array $parameters
   *   (optional) An associative array of build parameters. Possible keys:
   *   - expanded: An array of parent link IDs to return only book links that
   *     are children of one of the parent link IDs in this list. If empty,
   *     the whole outline is built, unless 'only_active_trail' is TRUE.
   *   - active_trail: An array of node IDs, representing the currently active
   *     book link.
   *   - only_active_trail: Whether to only return links that are in the active
   *     trail. This option is ignored if 'expanded' is non-empty.
   *   - min_depth: The minimum depth of book links in the resulting tree.
   *     Defaults to 1, which is to build the whole tree for the book.
   *   - max_depth: The maximum depth of book links in the resulting tree.
   *   - conditions: An associative array of custom database select query
   *     condition key/value pairs; see
   *     \Drupal\book\BookOutlineStorage::getBookMenuTree() for the actual
   *     query.
   *
   * @return array
   *   A fully built book tree.
   */
  protected function bookTreeBuild(int|string $bid, array $parameters = []): array {
    // Build the book tree.
    $data = $this->doBookTreeBuild($bid, $parameters);
    // Check access for the current user to each item in the tree.
    $this->bookTreeCheckAccess($data['tree'], $data['node_links']);
    return $data['tree'];
  }

  /**
   * Builds a book tree.
   *
   * This function may be used build the data for a menu tree only, for example
   * to further massage the data manually before further processing happens.
   * _menu_tree_check_access() needs to be invoked afterward.
   *
   * @param int $bid
   *   The book ID to find links for.
   * @param array $parameters
   *   (optional) An associative array of build parameters. Possible keys:
   *   - expanded: An array of parent link IDs to return only book links that
   *     are children of one of the parent link IDs in this list. If empty,
   *     the whole outline is built, unless 'only_active_trail' is TRUE.
   *   - active_trail: An array of node IDs, representing the currently active
   *     book link.
   *   - only_active_trail: Whether to only return links that are in the active
   *     trail. This option is ignored if 'expanded' is non-empty.
   *   - min_depth: The minimum depth of book links in the resulting tree.
   *     Defaults to 1, which is to build the whole tree for the book.
   *   - max_depth: The maximum depth of book links in the resulting tree.
   *   - conditions: An associative array of custom database select query
   *     condition key/value pairs; see
   *     \Drupal\book\BookOutlineStorage::getBookMenuTree() for the actual
   *     query.
   *
   * @return array
   *   An array with links representing the tree structure of the book.
   *
   * @see \Drupal\book\BookOutlineStorageInterface::getBookMenuTree()
   */
  protected function doBookTreeBuild(int $bid, array $parameters = []): array {
    // Build the cache id; sort parents to prevent duplicate storage and remove
    // default parameter values.
    if (isset($parameters['expanded'])) {
      sort($parameters['expanded']);
    }
    $langcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    $cid = implode(':', ['book-links', $bid, 'tree-data', $langcode, hash('sha256', serialize($parameters))]);

    // Get it from cache, if available.
    if ($cache = $this->backendChainedCache->get($cid)) {
      return $cache->data;
    }

    $min_depth = $parameters['min_depth'] ?? 1;
    $result = $this->bookOutlineStorage->getBookMenuTree($bid, $parameters, $min_depth, static::BOOK_MAX_DEPTH);

    // Build an ordered array of links using the query result object.
    $links = [];
    foreach ($result as $link) {
      $link = (array) $link;
      $links[$link['nid']] = $link;
    }
    $active_trail = $parameters['active_trail'] ?? [];
    $data['tree'] = $this->buildBookOutlineData($links, $active_trail, $min_depth);
    $data['node_links'] = [];
    $this->bookTreeCollectNodeLinks($data['tree'], $data['node_links']);

    // Cache tree data.
    $this->backendChainedCache->set($cid, $data, Cache::PERMANENT, ['bid:' . $bid]);

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function bookTreeCollectNodeLinks(array &$tree, array &$node_links): void {
    // All book links are nodes.
    foreach ($tree as $tree_item) {
      $item = &$tree_item['link'];
      $nid = $item['nid'];
      $node_links[$nid][$item['nid']] = &$item['link'];
      $tree_item['link']['access'] = FALSE;
      if ($tree_item['below']) {
        $this->bookTreeCollectNodeLinks($tree_item['below'], $node_links);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function bookTreeGetFlat(array $book_link): array {
    if (!isset($this->bookTreeFlattened[$book_link['nid']])) {
      // Call $this->bookTreeAllData() to take advantage of caching.
      $tree = $this->bookTreeAllData($book_link['bid'], $book_link, $book_link['depth'] + 1);
      $this->bookTreeFlattened[$book_link['nid']] = [];
      $this->flatBookTree($tree, $this->bookTreeFlattened[$book_link['nid']]);
    }

    return $this->bookTreeFlattened[$book_link['nid']];
  }

  /**
   * Recursively converts a tree of menu links to a flat array.
   *
   * @param array $tree
   *   A tree of menu links in an array.
   * @param array $flat
   *   A flat array of the menu links from $tree, passed by reference.
   *
   * @see static::bookTreeGetFlat()
   */
  protected function flatBookTree(array $tree, array &$flat): void {
    foreach ($tree as $data) {
      $flat[$data['link']['nid']] = $data['link'];
      if ($data['below']) {
        $this->flatBookTree($data['below'], $flat);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadBookLink(int $nid, bool $translate = TRUE): array {
    $links = $this->loadBookLinks([$nid], $translate);
    return $links[$nid] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function loadBookLinks(array $nids, bool $translate = TRUE): array {
    $result = $this->bookOutlineStorage->loadMultiple($nids, $translate);
    $links = [];
    foreach ($result as $link) {
      if ($translate) {
        $this->bookLinkTranslate($link);
      }
      $links[$link['nid']] = $link;
    }

    return $links;
  }

  /**
   * {@inheritdoc}
   */
  public function saveBookLink(array $link, bool $new): array {
    // Keep track of Book IDs for cache clear.
    $affected_bids[$link['bid']] = $link['bid'];
    $link += $this->getLinkDefaults($link['nid']);
    if ($new) {
      // Insert new.
      $parents = $this->getBookParents($link, $this->loadBookLink($link['pid'], FALSE));
      $this->bookOutlineStorage->insert($link, $parents);

      // Update the has_children status of the parent.
      $this->updateParent($link);
    }
    else {
      $original = $this->loadBookLink($link['nid'], FALSE);
      // Using the Book ID as the key keeps this unique.
      $affected_bids[$original['bid']] = $original['bid'];
      // Handle links that are moving.
      if ($link['bid'] != $original['bid'] || $link['pid'] != $original['pid']) {
        // Update the bid for this page and all children.
        if ($link['pid'] == 0) {
          $link['depth'] = 1;
          $parent = [];
        }
        // In case the form did not specify a proper PID we use the BID as new
        // parent.
        elseif (($parent_link = $this->loadBookLink($link['pid'], FALSE)) && $parent_link['bid'] != $link['bid']) {
          $link['pid'] = $link['bid'];
          $parent = $this->loadBookLink($link['pid'], FALSE);
          $link['depth'] = $parent['depth'] + 1;
        }
        else {
          $parent = $this->loadBookLink($link['pid'], FALSE);
          $link['depth'] = $parent['depth'] + 1;
        }
        $this->setParents($link, $parent);
        $this->moveChildren($link, $original);

        // Update the has_children status of the original parent.
        $this->updateOriginalParent($original);
        // Update the has_children status of the new parent.
        $this->updateParent($link);
      }
      // Update the weight and pid.
      $this->bookOutlineStorage->update($link['nid'], [
        'weight' => $link['weight'],
        'pid' => $link['pid'],
        'bid' => $link['bid'],
      ]);
    }
    $cache_tags = [];
    foreach ($affected_bids as $bid) {
      $cache_tags[] = 'bid:' . $bid;
    }
    Cache::invalidateTags($cache_tags);
    return $link;
  }

  /**
   * Moves children from the original parent to the updated link.
   *
   * @param array $link
   *   The link being saved.
   * @param array $original
   *   The original parent of $link.
   */
  protected function moveChildren(array $link, array $original): void {
    $p = 'p1';
    $expressions = [];
    for ($i = 1; $i <= $link['depth']; $p = 'p' . ++$i) {
      $expressions[] = [$p, ":p_$i", [":p_$i" => $link[$p]]];
    }
    $j = $original['depth'] + 1;
    while ($i <= static::BOOK_MAX_DEPTH && $j <= static::BOOK_MAX_DEPTH) {
      $expressions[] = ['p' . $i++, 'p' . $j++, []];
    }
    while ($i <= static::BOOK_MAX_DEPTH) {
      $expressions[] = ['p' . $i++, 0, []];
    }

    $shift = $link['depth'] - $original['depth'];
    if ($shift > 0) {
      // The order of expressions must be reversed so the new values don't
      // overwrite the old ones before they can be used because "Single-table
      // UPDATE assignments are generally evaluated from left to right".
      // @see http://dev.mysql.com/doc/refman/5.0/en/update.html
      $expressions = array_reverse($expressions);
    }

    $this->bookOutlineStorage->updateMovedChildren($link['bid'], $original, $expressions, $shift);
  }

  /**
   * Sets the has_children flag of the parent of the node.
   *
   * This method is mostly called when a book link is moved/created etc. So we
   * want to update the has_children flag of the new parent book link.
   *
   * @param array $link
   *   The book link, data reflecting its new position, whose new parent we want
   *   to update.
   *
   * @return bool
   *   TRUE if the update was successful (either there is no parent to update,
   *   or the parent was updated successfully), FALSE on failure.
   */
  protected function updateParent(array $link): bool {
    if ($link['pid'] == 0) {
      // Nothing to update.
      return TRUE;
    }
    return $this->bookOutlineStorage->update($link['pid'], ['has_children' => 1]);
  }

  /**
   * Updates the has_children flag of the parent of the original node.
   *
   * This method is called when a book link is moved or deleted. So we want to
   * update the has_children flag of the parent node.
   *
   * @param array $original
   *   The original link whose parent we want to update.
   *
   * @return bool
   *   TRUE if the update was successful (either there was no original parent to
   *   update, or the original parent was updated successfully), FALSE on
   *   failure.
   */
  protected function updateOriginalParent(array $original): bool {
    if ($original['pid'] == 0) {
      // There were no parents of this link. Nothing to update.
      return TRUE;
    }
    // Check if $original had at least one child.
    $original_number_of_children = $this->bookOutlineStorage->countOriginalLinkChildren($original);

    $parent_has_children = ($original_number_of_children) ? 1 : 0;
    // Update the parent. If the original link did not have children, then the
    // parent now does not have children. If the original had children, then
    // the parent has children now (still).
    return $this->bookOutlineStorage->update($original['pid'], ['has_children' => $parent_has_children]);
  }

  /**
   * Sets the p1 through p9 properties for a book link being saved.
   *
   * @param array $link
   *   The book link to update, passed by reference.
   * @param array $parent
   *   The parent values to set.
   */
  protected function setParents(array &$link, array $parent): void {
    $i = 1;
    while ($i < $link['depth']) {
      $p = 'p' . $i++;
      $link[$p] = $parent[$p];
    }
    $p = 'p' . $i++;
    // The parent (p1 - p9) corresponding to the depth always equals the nid.
    $link[$p] = $link['nid'];
    while ($i <= static::BOOK_MAX_DEPTH) {
      $p = 'p' . $i++;
      $link[$p] = 0;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function bookTreeCheckAccess(array &$tree, array $node_links = []): void {
    if ($node_links) {
      // @todo Extract that into its own method.
      $nids = array_keys($node_links);

      // @todo This should be actually filtering on the desired node status
      //   field language and just fall back to the default language.
      $book_links = $this->bookOutlineStorage->loadMultiple($nids);

      foreach ($book_links as $book_link) {
        $nid = $book_link['nid'];
        foreach ($node_links[$nid] as $menu_link_id => $link) {
          $node_links[$nid][$menu_link_id]['access'] = TRUE;
        }
      }
    }
    $this->doBookTreeCheckAccess($tree);
  }

  /**
   * Sorts the menu tree and recursively checks access for each item.
   *
   * @param array $tree
   *   The book tree to operate on.
   */
  protected function doBookTreeCheckAccess(array &$tree): void {
    $new_tree = [];
    foreach ($tree as $tree_item) {
      $item = &$tree_item['link'];
      $this->bookLinkTranslate($item);
      if ($item['access']) {
        if ($tree_item['below']) {
          $this->doBookTreeCheckAccess($tree_item['below']);
        }
        // The weights are made a uniform 5 digits by adding 50000 as an offset.
        // After calling $this->bookLinkTranslate(), $item['title'] has the
        // translated title. Adding the nid to the end of the index insures that
        // it is unique.
        $new_tree[(50000 + $item['weight']) . ' ' . $item['title'] . ' ' . $item['nid']] = $tree_item;
      }
    }
    // Sort siblings in the tree based on the weights and localized titles.
    ksort($new_tree);
    $tree = $new_tree;
  }

  /**
   * {@inheritdoc}
   */
  public function bookLinkTranslate(array &$link): array {
    // Check access via the api, since the query node_access tag doesn't check
    // for unpublished nodes.
    // @todo load the nodes en-mass rather than individually.
    // @see https://www.drupal.org/project/drupal/issues/2470896
    $node = $this->entityTypeManager->getStorage('node')->load($link['nid']);
    $link['access'] = $node && $node->access('view');
    // For performance, don't localize a link the user can't access.
    if ($link['access']) {
      // The node label will be the value for the current language.
      $node = $this->entityRepository->getTranslationFromContext($node);
      $link['title'] = $node->label();
      $link['options'] = [];
    }
    return $link;
  }

  /**
   * Sorts and returns the built data representing a book tree.
   *
   * @param array $links
   *   A flat array of book links that are part of the book. Each array element
   *   is an associative array of information about the book link, containing
   *   the fields from the {book} table. This array must be ordered depth-first.
   * @param array $parents
   *   An array of the node ID values that are in the path from the current
   *   page to the root of the book tree.
   * @param int $depth
   *   The minimum depth to include in the returned book tree.
   *
   * @return array
   *   An array of book links in the form of a tree. Each item in the tree is an
   *   associative array containing:
   *   - link: The book link item from $links, with additional element
   *     'in_active_trail' (TRUE if the link ID was in $parents).
   *   - below: An array containing the subtree of this item, where each
   *     element is a tree item array with 'link' and 'below' elements. This
   *     array will be empty if the book link has no items in its subtree
   *     having a depth greater than or equal to $depth.
   */
  protected function buildBookOutlineData(array $links, array $parents = [], int $depth = 1): array {
    // Reverse the array, so we can use the more efficient array_pop() function.
    $links = array_reverse($links);
    return $this->buildBookOutlineRecursive($links, $parents, $depth);
  }

  /**
   * Builds the data representing a book tree.
   *
   * The function is a bit complex because the rendering of a link depends on
   * the next book link.
   *
   * @param array $links
   *   A flat array of book links that are part of the book. Each array element
   *   is an associative array of information about the book link, containing
   *   the fields from the {book} table. This array must be ordered depth-first.
   * @param array $parents
   *   An array of the node ID values that are in the path from the current page
   *   to the root of the book tree.
   * @param int $depth
   *   The minimum depth to include in the returned book tree.
   *
   * @return array
   *   Book tree.
   */
  protected function buildBookOutlineRecursive(array &$links, array $parents, int $depth): array {
    $tree = [];
    while ($item = array_pop($links)) {
      // We need to determine if we're on the path to root, so we can later
      // build the correct active trail.
      $item['in_active_trail'] = in_array($item['nid'], $parents);
      // Add the current link to the tree.
      $tree[$item['nid']] = [
        'link' => $item,
        'below' => [],
      ];
      // Look ahead to the next link, but leave it on the array, so it's
      // available to other recursive function calls if we return or build a
      // subtree.
      $next = end($links);
      // Check whether the next link is the first in a new subtree.
      if ($next && $next['depth'] > $depth) {
        // Recursively call buildBookOutlineRecursive to build the subtree.
        $tree[$item['nid']]['below'] = $this->buildBookOutlineRecursive($links, $parents, $next['depth']);
        // Fetch next link after filling the subtree.
        $next = end($links);
      }
      // Determine if we should exit the loop and $request = return.
      if (!$next || $next['depth'] < $depth) {
        break;
      }
    }
    return $tree;
  }

  /**
   * {@inheritdoc}
   */
  public function bookSubtreeData(array $link): array {
    // Generate a cache ID (cid) specific for this $link.
    $langcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    $cid = "book-links:subtree-data:{$link['nid']}:$langcode";
    // Get it from cache, if available.
    if ($cache = $this->backendChainedCache->get($cid)) {
      return $cache->data;
    }

    $result = $this->bookOutlineStorage->getBookSubtree($link, static::BOOK_MAX_DEPTH);

    $links = [];
    foreach ($result as $item) {
      $links[] = $item;
    }
    $data['tree'] = $this->buildBookOutlineData($links, [], $link['depth']);
    $data['node_links'] = [];
    $this->bookTreeCollectNodeLinks($data['tree'], $data['node_links']);
    // Check access for the current user to each item in the tree.
    $this->bookTreeCheckAccess($data['tree'], $data['node_links']);

    // Cache subtree data.
    $this->backendChainedCache->set($cid, $data['tree'], Cache::PERMANENT, ['bid:' . $link['bid']]);

    return $data['tree'];
  }

}
