<?php

namespace Drupal\book;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Provides an interface defining a book manager.
 */
interface BookManagerInterface {

  /**
   * Gets the data structure representing a named menu tree.
   *
   * Since this can be the full tree including hidden items, the data returned
   * may be used for generating an admin interface or a select.
   *
   * Note: based on menu_tree_all_data().
   *
   * @param int $bid
   *   The Book ID to find links for.
   * @param array|null $link
   *   (optional) A fully loaded menu link, or NULL. If a link is supplied, only
   *   the path to root will be included in the returned tree - as if this link
   *   represented the current page in a visible menu.
   * @param int|null $max_depth
   *   (optional) Maximum depth of links to retrieve. Typically useful if only
   *   one or two levels of a subtree are needed in conjunction with a non-NULL
   *   $link, in which case $max_depth should be greater than $link['depth'].
   * @param int|null $min_depth
   *   (optional) Minimum depth of links to retrieve. Typically useful if only
   *   one or two levels of a subtree are needed in conjunction with a non-NULL
   *   $link, in which case $min_depth should be greater than $link['depth'].
   *
   * @return array
   *   A tree of menu links in an array, in the order they should be rendered.
   */
  public function bookTreeAllData(int $bid, ?array $link = NULL, ?int $max_depth = NULL, ?int $min_depth = NULL): array;

  /**
   * Gets the active trail IDs for the specified book at the provided path.
   *
   * @param string $bid
   *   The Book ID to find links for.
   * @param array $link
   *   A fully loaded menu link.
   *
   * @return array
   *   An array containing the active trail: a list of menu link IDs.
   */
  public function getActiveTrailIds(string $bid, array $link): array;

  /**
   * Loads a single book entry.
   *
   * The entries of a book entry is documented in
   * \Drupal\book\BookOutlineStorageInterface::loadMultiple.
   *
   * If $translate is TRUE, it also checks access ('access' key) and
   * loads the title from the node itself.
   *
   * @param int $nid
   *   The node ID of the book.
   * @param bool $translate
   *   If TRUE, set access, title, and other elements.
   *
   * @return array
   *   The book data of that node.
   *
   * @see \Drupal\book\BookOutlineStorageInterface::loadMultiple
   */
  public function loadBookLink(int $nid, bool $translate = TRUE): array;

  /**
   * Loads multiple book entries.
   *
   * The entries of a book entry is documented in
   * \Drupal\book\BookOutlineStorageInterface::loadMultiple.
   *
   * If $translate is TRUE, it also checks access ('access' key) and
   * loads the title from the node itself.
   *
   * @param int[] $nids
   *   An array of nids to load.
   * @param bool $translate
   *   If TRUE, set access, title, and other elements.
   *
   * @return array[]
   *   The book data of each node keyed by NID.
   *
   * @see \Drupal\book\BookOutlineStorageInterface::loadMultiple
   */
  public function loadBookLinks(array $nids, bool $translate = TRUE): array;

  /**
   * Returns an array of book pages in table of contents order.
   *
   * @param int|string $bid
   *   The ID of the book whose pages are to be listed.
   * @param int $depth_limit
   *   Any link deeper than this value will be excluded (along with its
   *   children).
   * @param array $exclude
   *   (optional) An array of menu link ID values. Any link whose menu link ID
   *   is in this array will be excluded (along with its children). Defaults to
   *   an empty array.
   * @param bool $truncate
   *   (optional) Flag to indicate if title should be truncated.
   *   Defaults to TRUE.
   *
   * @return array
   *   An array of (menu link ID, title) pairs for use as options for selecting
   *   a book page.
   */
  public function getTableOfContents(int|string $bid, int $depth_limit, array $exclude = [], bool $truncate = TRUE): array;

  /**
   * Finds the depth limit for items in the parent select.
   *
   * @param array $book_link
   *   A fully loaded menu link that is part of the book hierarchy.
   *
   * @return int
   *   The depth limit for items in the parent select.
   */
  public function getParentDepthLimit(array $book_link): int;

  /**
   * Collects node links from a given menu tree recursively.
   *
   * @param array $tree
   *   The menu tree you wish to collect node links from.
   * @param array $node_links
   *   An array in which to store the collected node links.
   */
  public function bookTreeCollectNodeLinks(array &$tree, array &$node_links): void;

  /**
   * Provides book loading, access control and translation.
   *
   * @param array $link
   *   A book link.
   *
   * @return array
   *   The book entry link information.
   */
  public function bookLinkTranslate(array &$link): array;

  /**
   * Gets the book for a page and returns it as a linear array.
   *
   * @param array $book_link
   *   A fully loaded book link that is part of the book hierarchy.
   *
   * @return array
   *   A linear array of book links in the order that the links are shown in the
   *   book, so the previous and next pages are the elements before and after
   *   the element corresponding to the current node. The children of the
   *   current node (if any) will come immediately after it in the array, and
   *   links will only be fetched as deep as one level deeper than $book_link.
   */
  public function bookTreeGetFlat(array $book_link): array;

  /**
   * Returns an array of all books.
   *
   * This list may be used for generating a list of all the books, or for
   * building the options for a form select.
   *
   * @return array
   *   An array of all books.
   */
  public function getAllBooks(): array;

  /**
   * Handles additions and updates to the book outline.
   *
   * This common helper function performs all additions and updates to the book
   * outline through node addition, node editing, node deletion, or the outline
   * tab.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node that is being saved, added, deleted, or moved.
   *
   * @return bool
   *   TRUE if the book link was saved; FALSE otherwise.
   */
  public function updateOutline(NodeInterface $node): bool;

  /**
   * Saves a link for a single book entry to the book.
   *
   * @param array $link
   *   The link data to save. $link['nid'] must be set. Other keys in this array
   *   get default values from
   *   \Drupal\book\BookManagerInterface::getLinkDefaults(). The array keys
   *   available to be set are documented in
   *   \Drupal\book\BookOutlineStorageInterface::loadMultiple().
   * @param bool $new
   *   Whether this is a link to a new book entry.
   *
   * @return array
   *   The book entry link information. This is $link with values added or
   *   updated.
   *
   * @see \Drupal\book\BookManagerInterface::getLinkDefaults()
   * @see \Drupal\book\BookOutlineStorageInterface::loadMultiple()
   */
  public function saveBookLink(array $link, bool $new): array;

  /**
   * Returns an array with default values for a book page's menu link.
   *
   * @param int|string $nid
   *   The ID of the node whose menu link is being created.
   *
   * @return array
   *   The default values for the menu link.
   */
  public function getLinkDefaults(int|string $nid): array;

  /**
   * Gets an associative array of parent IDs of a book page.
   *
   * @param array $item
   *   The book page defined by an associative array with the following keys:
   *   - nid: The node ID of the book page.
   *   - pid: The node ID of the immediate parent of that page.
   * @param array $parent
   *   (optional) An associative array containing details about the immediate
   *   parent, if one exists. Needed if the book page has a parent. The details
   *   include the following keys:
   *   - depth: the depth of the parent.
   *   - ['p1'] through ['p9']: 9 keys representing the node IDs of the parent's
   *     parents, from the top-level down to the parent. These keys are
   *     zero-filled below the depth of the parent. This max depth is defined
   *     by static::BOOK_MAX_DEPTH.
   *
   * @return array
   *   An associative array with the node IDs of the book page parents, with the
   *   following keys:
   *   - depth: the depth of the book page.
   *   - ['p1'] through ['p9']: 9 keys representing the node ID of the parents,
   *     from the top-level down to the submitted book page. These keys are
   *     zero-filled below the depth of the submitted book page. This max depth
   *     is defined by static::BOOK_MAX_DEPTH.
   */
  public function getBookParents(array $item, array $parent = []): array;

  /**
   * Builds the common elements of the book form for the node and outline forms.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\node\NodeInterface $node
   *   The node whose form is being viewed.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account viewing the form.
   * @param bool $collapsed
   *   If TRUE, the fieldset starts out collapsed.
   *
   * @return array
   *   The form structure, with the book elements added.
   */
  public function addFormElements(array $form, FormStateInterface $form_state, NodeInterface $node, AccountInterface $account, bool $collapsed = TRUE): array;

  /**
   * Deletes node's entry from book table.
   *
   * @param int $nid
   *   The nid to delete.
   */
  public function deleteFromBook(int $nid): void;

  /**
   * Returns a rendered menu tree.
   *
   * The menu item's LI element is given one of the following classes:
   * - expanded: The menu item is showing its submenu.
   * - collapsed: The menu item has a submenu which is not shown.
   *
   * @param array $tree
   *   A data structure representing the tree as returned from
   *   buildBookOutlineData.
   *
   * @return array
   *   A structured array to be rendered by
   *   \Drupal\Core\Render\RendererInterface::render().
   *
   * @see \Drupal\Core\Menu\MenuLinkTree::build
   */
  public function bookTreeOutput(array $tree): array;

  /**
   * Checks access and performs dynamic operations for each link in the tree.
   *
   * @param array $tree
   *   The book tree you wish to operate on.
   * @param array $node_links
   *   A collection of node link references generated from $tree by
   *   menu_tree_collect_node_links().
   */
  public function bookTreeCheckAccess(array &$tree, array $node_links = []): void;

  /**
   * Gets the data representing a subtree of the book hierarchy.
   *
   * The root of the subtree will be the link passed as a parameter, so the
   * returned tree will contain this item and all its descendants in the menu
   * tree.
   *
   * @param array $link
   *   A fully loaded book link.
   *
   * @return array
   *   A subtree of book links in an array, in the order they should be
   *   rendered.
   */
  public function bookSubtreeData(array $link): array;

  /**
   * Determines if a node can be removed from the book.
   *
   * A node can be removed from a book if it is actually in a book and it either
   * is not a top-level page or is a top-level page with no children.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to remove from the outline.
   *
   * @return bool
   *   TRUE if a node can be removed from the book, FALSE otherwise.
   */
  public function checkNodeIsRemovable(NodeInterface $node): bool;

}
