<?php

namespace Drupal\book;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\StatementInterface;

/**
 * Defines a storage class for books outline.
 */
class BookOutlineStorage implements BookOutlineStorageInterface {

  /**
   * Constructs a BookOutlineStorage object.
   */
  public function __construct(protected Connection $connection) {
  }

  /**
   * {@inheritdoc}
   */
  public function getBooks(): array {
    return $this->connection->select('book', 'b')->fields('b', ['bid'])->distinct()->execute()->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function hasBooks(): bool {
    return (bool) $this->connection->select('book', 'b')->fields('b', ['bid'])->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $nids, bool $access = TRUE): array {
    $query = $this->connection->select('book', 'b', ['fetch' => \PDO::FETCH_ASSOC]);
    $query->fields('b');
    $query->condition('b.nid', $nids, 'IN');

    if ($access) {
      $query->addTag('node_access');
      $query->addMetaData('base_table', 'book');
    }

    return $query->execute()->fetchAll();
  }

  /**
   * {@inheritdoc}
   */
  public function getChildRelativeDepth(array $book_link, int $max_depth): int {
    $query = $this->connection->select('book');
    $query->addField('book', 'depth');
    $query->condition('bid', $book_link['bid']);
    $query->orderBy('depth', 'DESC');
    $query->range(0, 1);

    $i = 1;
    $p = 'p1';
    while ($i <= $max_depth && $book_link[$p]) {
      $query->condition($p, $book_link[$p]);
      $p = 'p' . ++$i;
    }

    return $query->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function delete(int $nid): int {
    return $this->connection->delete('book')
      ->condition('nid', $nid)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function loadBookChildren(int $pid): array {
    return $this->connection->select('book', 'b')
      ->fields('b')
      ->condition('b.pid', $pid)
      ->execute()->fetchAllAssoc('nid', \PDO::FETCH_ASSOC);
  }

  /**
   * {@inheritdoc}
   */
  public function getBookMenuTree(int $bid, array $parameters, int $min_depth, int $max_depth): StatementInterface {
    $query = $this->connection->select('book');
    $query->fields('book');
    for ($i = 1; $i <= $max_depth; $i++) {
      $query->orderBy('p' . $i);
    }
    $query->condition('bid', $bid);
    if (!empty($parameters['expanded'])) {
      $query->condition('pid', $parameters['expanded'], 'IN');
    }
    if ($min_depth != 1) {
      $query->condition('depth', $min_depth, '>=');
    }
    if (isset($parameters['max_depth'])) {
      $query->condition('depth', $parameters['max_depth'], '<=');
    }
    // Add custom query conditions, if any were passed.
    if (isset($parameters['conditions'])) {
      foreach ($parameters['conditions'] as $column => $value) {
        $query->condition($column, $value);
      }
    }

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function insert(array $link, array $parents): string|int|null {
    return $this->connection
      ->insert('book')
      ->fields([
        'nid' => $link['nid'],
        'bid' => $link['bid'],
        'pid' => $link['pid'],
        'weight' => $link['weight'],
      ] + $parents)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function update(int $nid, array $fields): ?int {
    return $this->connection
      ->update('book')
      ->fields($fields)
      ->condition('nid', $nid)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function updateMovedChildren(int $bid, array $original, array $expressions, int $shift): ?int {
    $query = $this->connection->update('book');
    $query->fields(['bid' => $bid]);

    foreach ($expressions as $expression) {
      $query->expression($expression[0], $expression[1], $expression[2]);
    }

    $query->expression('depth', '[depth] + :depth', [':depth' => $shift]);
    $query->condition('bid', $original['bid']);
    $p = 'p1';
    for ($i = 1; !empty($original[$p]); $p = 'p' . ++$i) {
      $query->condition($p, $original[$p]);
    }

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function countOriginalLinkChildren(array $original): int {
    return $this->connection->select('book', 'b')
      ->condition('bid', $original['bid'])
      ->condition('pid', $original['pid'])
      ->condition('nid', $original['nid'], '<>')
      ->countQuery()
      ->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getBookSubtree($link, $max_depth): array {
    $query = $this->connection->select('book', 'b', ['fetch' => \PDO::FETCH_ASSOC]);
    $query->fields('b');
    $query->condition('b.bid', $link['bid']);

    for ($i = 1; $i <= $max_depth && $link["p$i"]; ++$i) {
      $query->condition("p$i", $link["p$i"]);
    }
    for ($i = 1; $i <= $max_depth; ++$i) {
      $query->orderBy("p$i");
    }
    return $query->execute()->fetchAll();
  }

}
