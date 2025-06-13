<?php

declare(strict_types=1);

namespace Drupal\Tests\book\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;

/**
 * Create a book, add pages, and test book interface.
 *
 * @group book
 */
class BookBreadcrumbTest extends BrowserTestBase {

  use BookTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['book', 'block', 'book_breadcrumb_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user without the 'node test view' permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $webUserWithoutNodeAccess;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalPlaceBlock('system_breadcrumb_block');
    $this->drupalPlaceBlock('page_title_block');

    // Create users.
    $this->bookAuthor = $this->drupalCreateUser([
      'create new books',
      'create book content',
      'edit own book content',
      'add content to books',
    ]);
  }

  /**
   * Creates a new book with a page hierarchy.
   *
   * @return \Drupal\node\NodeInterface[]
   *   The created book nodes.
   */
  protected function createBreadcrumbBook(): array {
    // Create new book.
    $this->drupalLogin($this->bookAuthor);

    $this->book = $this->createBookNode('new');
    $book = $this->book;

    /*
     * Add page hierarchy to book.
     * Book
     *  |- Node 0
     *   |- Node 1
     *   |- Node 2
     *    |- Node 3
     *     |- Node 4
     *      |- Node 5
     *  |- Node 6
     */
    $nodes = [];
    $nodes[0] = $this->createBookNode($book->id());
    $nodes[1] = $this->createBookNode($book->id(), $nodes[0]->id());
    $nodes[2] = $this->createBookNode($book->id(), $nodes[0]->id());
    $nodes[3] = $this->createBookNode($book->id(), $nodes[2]->id());
    $nodes[4] = $this->createBookNode($book->id(), $nodes[3]->id());
    $nodes[5] = $this->createBookNode($book->id(), $nodes[4]->id());
    $nodes[6] = $this->createBookNode($book->id());

    $this->drupalLogout();

    return $nodes;
  }

  /**
   * Tests that the breadcrumb is updated when book content changes.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testBreadcrumbTitleUpdates(): void {
    // Create a new book.
    $nodes = $this->createBreadcrumbBook();

    $this->drupalLogin($this->bookAuthor);

    $this->drupalGet($nodes[4]->toUrl());
    // Fetch each node title in the current breadcrumb.
    $links = $this->xpath('//nav[@aria-labelledby="system-breadcrumb"]/ol/li/a');
    $got_breadcrumb = [];
    foreach ($links as $link) {
      $got_breadcrumb[] = $link->getText();
    }
    // Home link and four parent book nodes should be in the breadcrumb.
    $this->assertCount(5, $got_breadcrumb);
    $this->assertEquals($nodes[3]->getTitle(), end($got_breadcrumb));
    $edit = [
      'title[0][value]' => 'Updated node5 title',
    ];
    $this->drupalGet($nodes[3]->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');
    $this->drupalGet($nodes[4]->toUrl());
    // Fetch each node title in the current breadcrumb.
    $links = $this->xpath('//nav[@aria-labelledby="system-breadcrumb"]/ol/li/a');
    $got_breadcrumb = [];
    foreach ($links as $link) {
      $got_breadcrumb[] = $link->getText();
    }
    $this->assertCount(5, $got_breadcrumb);
    $this->assertEquals($edit['title[0][value]'], end($got_breadcrumb));
  }

  /**
   * Tests that the breadcrumb is updated when book access changes.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testBreadcrumbAccessUpdates(): void {
    // Create a new book.
    $nodes = $this->createBreadcrumbBook();
    $this->drupalLogin($this->bookAuthor);
    $edit = [
      'title[0][value]' => "you can't see me",
    ];
    $this->drupalGet($nodes[3]->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');
    $this->drupalGet($nodes[4]->toUrl());
    $links = $this->xpath('//nav[@aria-labelledby="system-breadcrumb"]/ol/li/a');
    $got_breadcrumb = [];
    foreach ($links as $link) {
      $got_breadcrumb[] = $link->getText();
    }
    $this->assertCount(5, $got_breadcrumb);
    $this->assertEquals($edit['title[0][value]'], end($got_breadcrumb));
    $config = $this->container->get('config.factory')->getEditable('book_breadcrumb_test.settings');
    $config->set('hide', TRUE)->save();
    $this->drupalGet($nodes[4]->toUrl());
    $links = $this->xpath('//nav[@aria-labelledby="system-breadcrumb"]/ol/li/a');
    $got_breadcrumb = [];
    foreach ($links as $link) {
      $got_breadcrumb[] = $link->getText();
    }
    $this->assertCount(4, $got_breadcrumb);
    $this->assertEquals($nodes[2]->getTitle(), end($got_breadcrumb));
    $this->drupalGet($nodes[3]->toUrl());
    $this->assertSession()->statusCodeEquals(403);
  }

}
