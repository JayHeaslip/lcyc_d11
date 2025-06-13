<?php

namespace Drupal\book;

use Drupal\Core\Database\StatementInterface;

/**
 * Defines a common interface for book outline storage classes.
 */
interface BookOutlineStorageInterface {

  /**
   * Gets books (the highest positioned book links).
   *
   * @return array
   *   An array of book IDs.
   */
  public function getBooks(): array;

  /**
   * Checks if there are any books.
   *
   * @return bool
   *   TRUE if there are books, FALSE if not.
   */
  public function hasBooks(): bool;

  /**
   * Loads books.
   *
   * Each book entry consists of the following keys:
   *   - bid: The node ID of the main book.
   *   - nid: The node ID of the book entry itself.
   *   - pid: The parent node ID of the book.
   *   - has_children: A boolean to indicate whether the book has children.
   *   - weight: The weight of the book entry to order siblings.
   *   - depth: The depth in the menu hierarchy the entry is placed into.
   *
   * @param array $nids
   *   An array of node IDs.
   * @param bool $access
   *   Whether access checking should be taken into account.
   *
   * @return array
   *   Array of loaded book items.
   */
  public function loadMultiple(array $nids, bool $access = TRUE): array;

  /**
   * Gets child relative depth.
   *
   * @param array $book_link
   *   The book link.
   * @param int $max_depth
   *   The maximum supported depth of the book tree.
   *
   * @return int
   *   The depth of the searched book.
   */
  public function getChildRelativeDepth(array $book_link, int $max_depth): int;

  /**
   * Deletes a book entry.
   *
   * @param int $nid
   *   Deletes a book entry.
   *
   * @return mixed
   *   Number of deleted book entries.
   */
  public function delete(int $nid): mixed;

  /**
   * Loads book's children using its parent ID.
   *
   * @param int $pid
   *   The book's parent ID.
   *
   * @return array
   *   Array of loaded book items.
   */
  public function loadBookChildren(int $pid): array;

  /**
   * Builds tree data used for the menu tree.
   *
   * @param int $bid
   *   The ID of the book that we are building the tree for.
   * @param array $parameters
   *   An associative array of build parameters. For info about individual
   *   parameters see BookManager::bookTreeBuild().
   * @param int $min_depth
   *   The minimum depth of book links in the resulting tree.
   * @param int $max_depth
   *   The maximum supported depth of the book tree.
   *
   * @return \Drupal\Core\Database\StatementInterface
   *   Array of loaded book links.
   */
  public function getBookMenuTree(int $bid, array $parameters, int $min_depth, int $max_depth): StatementInterface;

  /**
   * Inserts a book link.
   *
   * @param array $link
   *   The link array to be inserted in the database.
   * @param array $parents
   *   The array of parent ids for the link to be inserted.
   *
   * @return mixed
   *   The last insert ID of the query, if one exists.
   */
  public function insert(array $link, array $parents): mixed;

  /**
   * Updates book reference for links that were moved between books.
   *
   * @param int $nid
   *   The nid of the book entry to be updated.
   * @param array $fields
   *   The array of fields to be updated.
   *
   * @return mixed
   *   The number of rows matched by the update query.
   */
  public function update(int $nid, array $fields): mixed;

  /**
   * Update the book ID of the book link that it's being moved.
   *
   * @param int $bid
   *   The ID of the book whose children we move.
   * @param array $original
   *   The original parent of the book link.
   * @param array $expressions
   *   Array of expressions to be added to the query.
   * @param int $shift
   *   The difference in depth between the old and the new position of the
   *   element being moved.
   *
   * @return mixed
   *   The number of rows matched by the update query.
   */
  public function updateMovedChildren(int $bid, array $original, array $expressions, int $shift): mixed;

  /**
   * Count the number of original link children.
   *
   * @param array $original
   *   The book link array.
   *
   * @return int
   *   Number of children.
   */
  public function countOriginalLinkChildren(array $original): int;

  /**
   * Get book subtree.
   *
   * @param array $link
   *   A fully loaded book link.
   * @param int $max_depth
   *   The maximum supported depth of the book tree.
   *
   * @return array
   *   Array of unordered subtree book items.
   */
  public function getBookSubtree(array $link, int $max_depth): array;

}
