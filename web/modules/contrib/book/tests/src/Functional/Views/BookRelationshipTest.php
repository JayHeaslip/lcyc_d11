<?php

declare(strict_types=1);

namespace Drupal\Tests\book\Functional\Views;

use Drupal\Tests\book\Functional\BookTestTrait;
use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests entity reference relationship data.
 *
 * @group book
 *
 * @see book_views_data()
 */
class BookRelationshipTest extends ViewTestBase {

  use BookTestTrait;

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static array $testViews = ['test_book_view'];

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['book_test_views', 'book', 'views'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp($import_test_views = TRUE, $modules = []): void {
    parent::setUp($import_test_views, $modules);

    // Create users.
    $this->bookAuthor = $this->drupalCreateUser(
      [
        'create new books',
        'create book content',
        'edit own book content',
        'add content to books',
      ]
    );
    ViewTestData::createTestViews(static::class, ['book_test_views']);
  }

  /**
   * Creates a new book with a page hierarchy.
   */
  protected function createBook(): array {
    // Create new book.
    $this->drupalLogin($this->bookAuthor);

    $this->book = $this->createBookNode('new');
    $book = $this->book;

    $nodes = [];
    // Node 0.
    $nodes[] = $this->createBookNode($book->id());
    // Node 1.
    $nodes[] = $this->createBookNode($book->id(), $nodes[0]->book['nid']);
    // Node 2.
    $nodes[] = $this->createBookNode($book->id(), $nodes[1]->book['nid']);
    // Node 3.
    $nodes[] = $this->createBookNode($book->id(), $nodes[2]->book['nid']);
    // Node 4.
    $nodes[] = $this->createBookNode($book->id(), $nodes[3]->book['nid']);
    // Node 5.
    $nodes[] = $this->createBookNode($book->id(), $nodes[4]->book['nid']);
    // Node 6.
    $nodes[] = $this->createBookNode($book->id(), $nodes[5]->book['nid']);
    // Node 7.
    $nodes[] = $this->createBookNode($book->id(), $nodes[6]->book['nid']);

    $this->drupalLogout();

    return $nodes;
  }

  /**
   * Tests using the views relationship.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testRelationship(): void {

    // Create new book.
    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $this->createBook();
    for ($i = 0; $i < 8; $i++) {
      $this->drupalGet('test-book/' . $nodes[$i]->id());

      for ($j = 0; $j < $i; $j++) {
        $this->assertSession()->linkExists($nodes[$j]->label());
      }
    }
  }

}
