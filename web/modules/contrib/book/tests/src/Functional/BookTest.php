<?php

declare(strict_types=1);

namespace Drupal\Tests\book\Functional;

use Drupal\Core\Cache\Cache;
use Drupal\node\Entity\Node;
use Drupal\user\RoleInterface;

/**
 * Create a book, add pages, and test book interface.
 *
 * @group book
 * @group #slow
 */
class BookTest extends BookTestBase {

  /**
   * Tests the book navigation cache context.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   *
   * @see \Drupal\book\Cache\BookNavigationCacheContext
   */
  public function testBookNavigationCacheContext(): void {
    // Create a page node.
    $this->drupalCreateContentType(['type' => 'page']);
    $page = $this->drupalCreateNode();

    // Create a book, consisting of book nodes.
    $book_nodes = $this->createBook();

    // Enable the debug output.
    $this->container->get('state')->set('book_test.debug_book_navigation_cache_context', TRUE);
    Cache::invalidateTags(['book_test.debug_book_navigation_cache_context']);

    $this->drupalLogin($this->bookAuthor);

    // On non-node route.
    $this->drupalGet($this->adminUser->toUrl());
    $this->assertSession()->responseContains('[route.book_navigation]=book.none');

    // On non-book node route.
    $this->drupalGet($page->toUrl());
    $this->assertSession()->responseContains('[route.book_navigation]=book.none');

    // On book node route.
    $this->drupalGet($book_nodes[0]->toUrl());
    $this->assertSession()->responseContains('[route.book_navigation]=0|2|3');
    $this->drupalGet($book_nodes[1]->toUrl());
    $this->assertSession()->responseContains('[route.book_navigation]=0|2|3|4');
    $this->drupalGet($book_nodes[2]->toUrl());
    $this->assertSession()->responseContains('[route.book_navigation]=0|2|3|5');
    $this->drupalGet($book_nodes[3]->toUrl());
    $this->assertSession()->responseContains('[route.book_navigation]=0|2|6');
    $this->drupalGet($book_nodes[4]->toUrl());
    $this->assertSession()->responseContains('[route.book_navigation]=0|2|7');
  }

  /**
   * Tests saving the book outline on an empty book.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testEmptyBook(): void {
    // Create a new empty book.
    $this->drupalLogin($this->bookAuthor);
    $book = $this->createBookNode('new');
    $this->drupalLogout();

    // Log in as a user with access to the book outline and save the form.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/structure/book/' . $book->id());
    $this->submitForm([], 'Save book pages');
    $this->assertSession()->pageTextContains('Updated book ' . $book->label() . '.');
  }

  /**
   * Tests book functionality through node interfaces.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBook(): void {
    // Create new book.
    $nodes = $this->createBook();
    $book = $this->book;

    $this->drupalLogin($this->webUser);

    // Check that book pages display along with the correct outlines and
    // previous/next links.
    $this->checkBookNode($book, [$nodes[0], $nodes[3], $nodes[4]], FALSE, FALSE, $nodes[0], []);
    $this->checkBookNode($nodes[0], [$nodes[1], $nodes[2]], $book, $book, $nodes[1], [$book]);
    $this->checkBookNode($nodes[1], NULL, $nodes[0], $nodes[0], $nodes[2], [$book, $nodes[0]]);
    $this->checkBookNode($nodes[2], NULL, $nodes[1], $nodes[0], $nodes[3], [$book, $nodes[0]]);
    $this->checkBookNode($nodes[3], NULL, $nodes[2], $book, $nodes[4], [$book]);
    $this->checkBookNode($nodes[4], NULL, $nodes[3], $book, FALSE, [$book]);

    $this->drupalLogout();
    $this->drupalLogin($this->bookAuthor);

    // Check the presence of expected cache tags.
    $this->drupalGet('node/add/book');
    $this->assertSession()->responseHeaderContains('X-Drupal-Cache-Tags', 'config:book.settings');

    /*
     * Add Node 5 under Node 3.
     * Book
     *  |- Node 0
     *   |- Node 1
     *   |- Node 2
     *  |- Node 3
     *   |- Node 5
     *  |- Node 4
     */
    // Node 5.
    $nodes[] = $this->createBookNode($book->id(), $nodes[3]->book['nid']);
    $this->drupalLogout();
    $this->drupalLogin($this->webUser);
    // Verify the new outline - make sure we don't get stale cached data.
    $this->checkBookNode($nodes[3], [$nodes[5]], $nodes[2], $book, $nodes[5], [$book]);
    $this->checkBookNode($nodes[4], NULL, $nodes[5], $book, FALSE, [$book]);
    $this->drupalLogout();
    // Create a second book, and move an existing book page into it.
    $this->drupalLogin($this->bookAuthor);
    $other_book = $this->createBookNode('new');
    $node = $this->createBookNode($book->id());

    $this->addNodeToBook(intval($other_book->id()), intval($node->id()));

    $this->drupalLogout();
    $this->drupalLogin($this->webUser);

    // Check that the nodes in the second book are displayed correctly.
    // First we must set $this->book to the second book, so that the
    // correct regex will be generated for testing the outline.
    $this->book = $other_book;
    $this->checkBookNode($other_book, [$node], FALSE, FALSE, $node, []);
    $this->checkBookNode($node, NULL, $other_book, $other_book, FALSE, [$other_book]);

    // Test that we can save a book programmatically.
    $this->drupalLogin($this->bookAuthor);
    $book = $this->createBookNode('new');
    $book->save();

    // Confirm that an unpublished book page has the 'Add child page' link.
    $this->drupalGet('node/' . $nodes[4]->id());
    $this->assertSession()->linkExists('Add child page');
    $nodes[4]->setUnPublished();
    $nodes[4]->save();
    $this->drupalGet('node/' . $nodes[4]->id());
    $this->assertSession()->linkExists('Add child page');

    // Confirm that a child page has the "Add sibling page".
    $this->drupalGet('node/' . $nodes[4]->id());
    $this->assertSession()->linkExists('Add sibling page');
    $this->clickLink('Add sibling page');
    /* Get the relative URL of the current session.
    This contains the pid passed in by 'Add sibling page'.
    Check that against the pid in $nodes[4]. */
    $current_url = parse_url($this->getSession()->getCurrentUrl(), PHP_URL_QUERY);
    $sibling_pid = substr($current_url, strpos($current_url, "=") + 1);

    $this->assertEquals($nodes[4]->book['pid'], $sibling_pid);
  }

  /**
   * Tests book export ("printer-friendly version") functionality.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ElementTextException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBookExport(): void {
    // Create a book.
    $nodes = $this->createBook();

    // Unpublish Node 2.
    $nodes[2]->setUnpublished()->save();

    // Log in as web user and view printer-friendly version.
    $this->drupalLogin($this->webUser);
    $this->drupalGet('node/' . $this->book->id());
    $this->clickLink('Printer-friendly version');

    $this->assertSession()->elementTextContains('css', 'h1', $this->book->label());

    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    $book_title = $node_storage->load($this->book->id())->label();

    // Make sure each part of the book is there.
    foreach ($nodes as $node) {
      // Verify unpublished node doesn't appear in export.
      if (!$node->isPublished()) {
        $this->assertSession()->pageTextNotContains('Book traversal links ' . $node->label());
        $this->assertSession()->responseNotContains($node->body->processed);
      }
      else {
        $this->assertSession()->pageTextContains($book_title);
        $this->assertSession()->pageTextContains($node->label());
        $this->assertSession()->responseContains($node->body->processed);
      }
    }

    // Enable module to make base fields' displays configurable and test again.
    $this->container->get('module_installer')->install(['book_display_configurable_test']);
    $this->drupalGet('book/export/html/' . $this->book->id());
    $this->assertSession()->elementTextContains('css', 'span', $this->book->label());

    // Make sure we can't export an unsupported format.
    $this->drupalGet('book/export/foobar/' . $this->book->id());
    $this->assertSession()->statusCodeEquals(404);

    // Make sure we get a 404 on a non-existent book node.
    $this->drupalGet('book/export/html/123');
    $this->assertSession()->statusCodeEquals(404);

    // Make sure we get 404 on nodes not in any book.
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Article-not-in-book',
    ]);
    $this->drupalGet('book/export/html/' . $node->id());
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->pageTextContains('Article-not-in-book is not in a book and cannot be exported');
    $node = $this->drupalCreateNode([
      'type' => 'book',
      'title' => 'Book-not-in-book',
    ]);
    $this->drupalGet('book/export/html/' . $node->id());
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->pageTextContains('Book-not-in-book is not in a book and cannot be exported');

    // Make sure an anonymous user cannot view printer-friendly version.
    $this->drupalLogout();

    // Load the book and verify there is no printer-friendly version link.
    $this->drupalGet('node/' . $this->book->id());
    $this->assertSession()->linkNotExists('Printer-friendly version', 'Anonymous user is not shown link to printer-friendly version.');

    // Try getting the URL directly, and verify it fails.
    $this->drupalGet('book/export/html/' . $this->book->id());
    $this->assertSession()->statusCodeEquals(403);

    // Now grant anonymous users permission to view the printer-friendly
    // version and verify that node access restrictions still prevent them from
    // seeing it.
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, ['access printer-friendly version']);
    $this->drupalGet('book/export/html/' . $this->book->id());
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests BookManager::getTableOfContents().
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testGetTableOfContents(): void {
    // Create new book.
    $nodes = $this->createBook();
    $book = $this->book;

    $this->drupalLogin($this->bookAuthor);

    /*
     * Add Node 5 under Node 2.
     * Add Node 6, 7, 8, 9, 10, 11 under Node 3.
     * Book
     *  |- Node 0
     *   |- Node 1
     *   |- Node 2
     *    |- Node 5
     *  |- Node 3
     *   |- Node 6
     *    |- Node 7
     *     |- Node 8
     *      |- Node 9
     *       |- Node 10
     *        |- Node 11
     *  |- Node 4
     */
    foreach ([5 => 2, 6 => 3, 7 => 6, 8 => 7, 9 => 8, 10 => 9, 11 => 10] as $child => $parent) {
      $nodes[$child] = $this->createBookNode($book->id(), $nodes[$parent]->id());
    }
    $this->drupalGet($nodes[0]->toUrl('edit-form'));
    // Since Node 0 has children 2 levels deep, nodes 10 and 11 should not
    // appear in the selector.
    $this->assertSession()->optionNotExists('edit-book-pid', $nodes[10]->id());
    $this->assertSession()->optionNotExists('edit-book-pid', $nodes[11]->id());
    // Node 9 should be available as an option.
    $this->assertSession()->optionExists('edit-book-pid', $nodes[9]->id());

    // Get a shallow set of options.
    /** @var \Drupal\book\BookManagerInterface $manager */
    $manager = $this->container->get('book.manager');
    $options = $manager->getTableOfContents($book->id(), 3);
    // Verify that all expected option keys are present.
    $expected_nids = [
      $book->id(),
      $nodes[0]->id(),
      $nodes[1]->id(),
      $nodes[2]->id(),
      $nodes[3]->id(),
      $nodes[6]->id(),
      $nodes[4]->id(),
    ];
    $this->assertEquals($expected_nids, array_keys($options));
    // Exclude Node 3.
    $options = $manager->getTableOfContents($book->id(), 3, [$nodes[3]->id()]);
    // Verify that expected option keys are present after excluding Node 3.
    $expected_nids = [$book->id(), $nodes[0]->id(), $nodes[1]->id(), $nodes[2]->id(), $nodes[4]->id()];
    $this->assertEquals($expected_nids, array_keys($options));
  }

  /**
   * Tests the access for deleting top-level book nodes.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function testBookDelete() {
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    $nodes = $this->createBook();
    $this->drupalLogin($this->adminUser);
    $edit = [];

    // Ensure that the top-level book node cannot be deleted.
    $this->drupalGet('node/' . $this->book->id() . '/outline/remove');
    $this->assertSession()->statusCodeEquals(403);

    // Ensure that a child book node can be deleted.
    $this->drupalGet('node/' . $nodes[4]->id() . '/outline/remove');
    $this->submitForm($edit, 'Remove');
    $node_storage->resetCache([$nodes[4]->id()]);
    $node4 = $node_storage->load($nodes[4]->id());
    $this->assertEmpty($node4->book, 'Deleting child book node properly allowed.');

    // $nodes[4] is stale, trying to delete it directly will cause an error.
    $node4->delete();
    unset($nodes[4]);

    // Delete all child book nodes and retest top-level node deletion.
    $node_storage->delete($nodes);

    $this->drupalGet('node/' . $this->book->id() . '/outline/remove');
    $this->submitForm($edit, 'Remove');
    $node_storage->resetCache([$this->book->id()]);
    $node = $node_storage->load($this->book->id());
    $this->assertEmpty($node->book, 'Deleting childless top-level book node properly allowed.');

    // Tests directly deleting a book parent.
    $nodes = $this->createBook();
    $this->drupalLogin($this->adminUser);
    $this->drupalGet($this->book->toUrl('delete-form'));
    $this->assertSession()->pageTextContains($this->book->label() . ' is part of a book outline, and has associated child pages. If you proceed with deletion, the child pages will be relocated automatically.');
    // Delete parent, and visit a child page.
    $this->drupalGet($this->book->toUrl('delete-form'));
    $this->submitForm([], 'Delete');
    $this->drupalGet($nodes[0]->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($nodes[0]->label());
    // The book parents should be updated.
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    $node_storage->resetCache();
    $child = $node_storage->load($nodes[0]->id());
    $this->assertEquals($child->id(), $child->book['bid'], 'Child node book ID updated when parent is deleted.');
    // 3rd-level children should now be 2nd-level.
    $second = $node_storage->load($nodes[1]->id());
    $this->assertEquals($child->id(), $second->book['bid'], '3rd-level child node is now second level when top-level node is deleted.');

    // Set the child page book id to deleted book id, and visit the child page.
    $node = $node_storage->load($nodes[0]->id());
    $node->book['bid'] = $nodes[0]->book['bid'];
    $node->save();
    $this->drupalGet($nodes[0]->toUrl());
    $this->assertSession()->pageTextContains($nodes[0]->label());
  }

  /**
   * Tests outline of a book.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBookOutline(): void {
    $this->drupalLogin($this->bookAuthor);

    // Create new node not yet a book.
    $empty_book = $this->drupalCreateNode(['type' => 'book']);
    $this->drupalGet('node/' . $empty_book->id() . '/outline');
    $this->assertSession()->linkNotExists('Book outline', 'Book Author is not allowed to outline');

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('node/' . $empty_book->id() . '/outline');
    $this->assertSession()->pageTextContains('Book outline');
    // Verify that the node does not belong to a book.
    $this->assertTrue($this->assertSession()->optionExists('edit-book-bid', 0)->isSelected());
    $this->assertSession()->linkNotExists('Remove from book outline');

    $edit = [];
    $edit['book[bid]'] = '1';
    $this->drupalGet('node/' . $empty_book->id() . '/outline');
    $this->submitForm($edit, 'Add to book outline');
    $node = $this->container->get('entity_type.manager')->getStorage('node')->load($empty_book->id());
    // Test the book array.
    $this->assertEquals($empty_book->id(), $node->book['nid']);
    $this->assertEquals($empty_book->id(), $node->book['bid']);
    $this->assertEquals(1, $node->book['depth']);
    $this->assertEquals($empty_book->id(), $node->book['p1']);
    $this->assertEquals('0', $node->book['pid']);

    // Create new book.
    $this->drupalLogin($this->bookAuthor);
    $book = $this->createBookNode('new');

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('node/' . $book->id() . '/outline');
    $this->assertSession()->pageTextContains('Book outline');
    $this->clickLink('Remove from book outline');
    $this->assertSession()->pageTextContains('Are you sure you want to remove ' . $book->label() . ' from the book hierarchy?');

    // Create a new node and set the book after the node was created.
    $node = $this->drupalCreateNode(['type' => 'book']);
    $this->addNodeToBook(intval($node->id()), intval($node->id()));
    $node = $this->container->get('entity_type.manager')->getStorage('node')->load($node->id());

    // Test the book array.
    $this->assertEquals($node->id(), $node->book['nid']);
    $this->assertEquals($node->id(), $node->book['bid']);
    $this->assertEquals(1, $node->book['depth']);
    $this->assertEquals($node->id(), $node->book['p1']);
    $this->assertEquals('0', $node->book['pid']);

    // Test the form itself.
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertTrue($this->assertSession()
      ->optionExists('edit-book-bid', $node->id())
      ->isSelected());

    // Create a new node that is not of book type.
    $this->drupalLogin($this->adminUser);
    $this->drupalCreateContentType(['type' => 'page']);
    $non_book_node = $this->drupalCreateNode(['type' => 'page']);

    // Create a non-book node and place in an outline.
    $non_book_node_in_outline = $this->drupalCreateNode([
      'type' => 'page',
      'book' => [
        'bid' => 'new',
      ],
    ]);

    // Admin user has edit book field on all nodes.
    $this->drupalGet('node/' . $non_book_node->id() . '/edit');
    $this->assertSession()->fieldExists('edit-book-bid');
    // Admin user has access to outline path on all nodes.
    $this->assertSession()->linkByHrefExists('node/' . $non_book_node->id() . '/outline');

    // Book author user only has edit book field on allowed book type nodes.
    $this->drupalLogin($this->bookAuthor);
    $this->drupalGet('node/' . $non_book_node->id() . '/edit');
    $this->assertSession()->fieldNotExists('edit-book-bid');
    // Book author user only has outline access on allowed book type nodes.
    $this->assertSession()->linkByHrefNotExists('node/' . $non_book_node->id() . '/outline');

    // Update bookAuthor permissions to edit page content type.
    $this->bookAuthor = $this->drupalCreateUser([
      'create new books',
      'create book content',
      'edit own book content',
      'add content to books',
      'add any content to books',
      'node test view',
      'edit any page content',
    ]);
    $this->drupalLogin($this->bookAuthor);

    // Book author user has edit book field on non-book nodes if node is in
    // an outline already.
    $this->drupalGet('node/' . $non_book_node_in_outline->id() . '/edit');
    $this->assertSession()->pageTextContains('This is the top-level page in this book');
    // Book author user has access to outline path if a node is already in
    // an outline already.
    $this->assertSession()->linkByHrefExists('node/' . $non_book_node_in_outline->id() . '/outline');
  }

  /**
   * Tests that saveBookLink() returns something.
   */
  public function testSaveBookLink(): void {
    $book_manager = $this->container->get('book.manager');
    // Mock a link for a new book.
    $link = [
      'nid' => 1,
      'has_children' => 0,
      'original_bid' => 0,
      'pid' => 0,
      'weight' => 0,
      'bid' => 0,
    ];

    // Save the link.
    $return = $book_manager->saveBookLink($link, TRUE);

    // Add the link defaults to $link, so we have something to compare to
    // the return from saveBookLink().
    $link = $book_manager->getLinkDefaults($link['nid']);

    // Test the return from saveBookLink.
    $this->assertEquals($return, $link);
  }

  /**
   * Tests the listing of all books.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testBookListing(): void {
    // Uninstall 'node_access_test' as this interferes with the test.
    $this->container->get('module_installer')->uninstall(['node_access_test']);
    // Create a new book.
    $nodes = $this->createBook();

    // Load the book page and assert the created book title is displayed.
    $this->drupalGet('book');
    $this->assertSession()->pageTextContains($this->book->label());

    // Assert helper links aren't available for anonymous users.
    $this->drupalGet('node/' . $nodes[1]->id());
    $this->assertSession()->linkNotExists('Add child page');
    $this->assertSession()->linkNotExists('Add sibling page');

    // Unpublish the top book page and confirm that the created book title is
    // not displayed for anonymous.
    $this->book->setUnpublished();
    $this->book->save();

    $this->drupalGet('book');
    $this->assertSession()->pageTextNotContains($this->book->label());

    // Publish the top book page and unpublish a page in the book and confirm
    // that the created book title is displayed for anonymous.
    $this->book->setPublished();
    $this->book->save();
    $nodes[0]->setUnpublished();
    $nodes[0]->save();

    $this->drupalGet('book');
    $this->assertSession()->pageTextContains($this->book->label());

    // Unpublish the top book page and confirm that the created book title is
    // displayed for user which has 'view own unpublished content' permission.
    $this->drupalLogin($this->bookAuthor);
    $this->book->setUnpublished();
    $this->book->save();

    $this->drupalGet('book');
    $this->assertSession()->pageTextContains($this->book->label());

    // Ensure the user doesn't see the book if they don't own it.
    $this->book->setOwner($this->webUser)->save();
    $this->drupalGet('book');
    $this->assertSession()->pageTextNotContains($this->book->label());

    // Confirm that the created book title is displayed for user which has
    // 'view any unpublished content' permission.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('book');
    $this->assertSession()->pageTextContains($this->book->label());
  }

  /**
   * Tests the administrative listing of all books.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testAdminBookListing(): void {
    // Create a new book.
    $this->createBook();

    // Load the book page and assert the created book title is displayed.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/structure/book');
    $this->assertSession()->pageTextContains($this->book->label());
  }

  /**
   * Tests the administrative listing of all book pages in a book.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testAdminBookNodeListing(): void {
    // Create a new book.
    $nodes = $this->createBook();
    $this->drupalLogin($this->adminUser);

    // Load the book page list and assert the created book title is displayed
    // and action links are shown on list items.
    $this->drupalGet('admin/structure/book/' . $this->book->id());
    $this->assertSession()->pageTextContains($this->book->label());

    // Test that the view link is found from the list.
    $this->assertSession()->elementTextEquals('xpath', '//table//ul[@class="dropbutton"]/li/a', 'View');

    // Test that all the book pages are displayed on the book outline page.
    $this->assertSession()->elementsCount('xpath', '//table//ul[@class="dropbutton"]/li/a', count($nodes));

    // Unpublish a book in the hierarchy.
    $nodes[0]->setUnPublished();
    $nodes[0]->save();

    // Node should still appear on the outline for admins.
    $this->drupalGet('admin/structure/book/' . $this->book->id());
    $this->assertSession()->elementsCount('xpath', '//table//ul[@class="dropbutton"]/li/a', count($nodes));

    // Saving a book page not as the current version shouldn't affect the book.
    $old_title = $nodes[1]->getTitle();
    $new_title = $this->getRandomGenerator()->name();
    $nodes[1]->isDefaultRevision(FALSE);
    $nodes[1]->setNewRevision();
    $nodes[1]->setTitle($new_title);
    $nodes[1]->save();
    $this->drupalGet('admin/structure/book/' . $this->book->id());
    $this->assertSession()->elementsCount('xpath', '//table//ul[@class="dropbutton"]/li/a', count($nodes));
    $this->assertSession()->responseNotContains($new_title);
    $this->assertSession()->responseContains($old_title);
  }

  /**
   * Ensure the loaded book in hook_node_load() does not depend on the user.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function testHookNodeLoadAccess(): void {
    $this->container->get('module_installer')->install(['node_access_test']);

    // Ensure that the loaded book in hook_node_load() does NOT depend on the
    // current user.
    $this->drupalLogin($this->bookAuthor);
    $this->book = $this->createBookNode('new');
    // Reset any internal static caching.
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    $node_storage->resetCache();

    // Log in as user without access to the book node, so no 'node test view'
    // permission.
    // @see node_access_test_node_grants().
    $this->drupalLogin($this->webUserWithoutNodeAccess);
    $book_node = $node_storage->load($this->book->id());
    $this->assertNotEmpty($book_node->book);
    $this->assertEquals($this->book->id(), $book_node->book['bid']);

    // Reset the internal cache to retrigger the hook_node_load() call.
    $node_storage->resetCache();

    $this->drupalLogin($this->webUser);
    $book_node = $node_storage->load($this->book->id());
    $this->assertNotEmpty($book_node->book);
    $this->assertEquals($this->book->id(), $book_node->book['bid']);
  }

  /**
   * Tests the ordering of books in all the listings.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testBookOrder(): void {
    $this->drupalLogin($this->adminUser);

    // Create three books.
    $book1 = $this->createBookNode('new');
    $book1->setTitle('AAA Book');
    $book1->save();

    $book2 = $this->createBookNode('new');
    $book2->setTitle('BBB Book');
    $book2->save();

    $book3 = $this->createBookNode('new');
    $book3->setTitle('CCC Book');
    $book3->save();

    // Set weight for books.
    $edit_url = 'node/' . $book1->id() . '/outline';
    $edit = ['book[weight]' => 1];
    $this->drupalGet($edit_url);
    $this->submitForm($edit, 'Update book outline');
    $this->assertSession()->pageTextContains('The book outline has been updated');

    $edit_url = 'node/' . $book3->id() . '/outline';
    $edit = ['book[weight]' => -1];
    $this->drupalGet($edit_url);
    $this->submitForm($edit, 'Update book outline');
    $this->assertSession()->pageTextContains('The book outline has been updated');

    // Place a book navigation block.
    $this->drupalPlaceBlock('book_navigation');

    // Test books order by weight.
    $expected_order = [
      $book3->getTitle(),
      $book2->getTitle(),
      $book1->getTitle(),
    ];
    $this->assertBookOrder($expected_order);

    // Set the books sorting by title.
    $this->config('book.settings')
      ->set('book_sort', 'title')
      ->save();

    // Test books order by title.
    $expected_order = [
      $book1->getTitle(),
      $book2->getTitle(),
      $book3->getTitle(),
    ];
    $this->assertBookOrder($expected_order);
  }

  /**
   * Asserts the ordering of books.
   *
   * @param array $expected_order
   *   Expected book order.
   */
  protected function assertBookOrder(array $expected_order): void {
    // URLs to test the ordering of books.
    $urls = [
      'Navigation block on front page' => '<front>',
      'Admin overview' => 'admin/structure/book',
      'Node add/edit' => 'node/add/book',
    ];

    foreach ($urls as $url) {
      $this->drupalGet($url);
      $content = $this->getSession()->getPage()->getContent();

      $actual_order = [];
      $offset = 0;
      foreach ($expected_order as $substring) {
        if (($pos = strpos($content, $substring, $offset)) !== FALSE) {
          $actual_order[] = $substring;
          $offset = $pos + strlen($substring);
        }
      }

      $this->assertSame($expected_order, $actual_order, "Books are incorrectly ordered on URL '$url'.");
    }
  }

  /**
   * Tests that the book settings form can be saved without error.
   */
  public function testSettingsForm(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/structure/book/settings');
    $this->submitForm([], 'Save configuration');
  }

  /**
   * Tests saving the book outline with empty title.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function testEmptyBookTitle(): void {
    $book = Node::create([
      'type' => 'book',
      'title' => 'Book',
      'book' => ['bid' => 'new'],
    ]);
    $book->save();
    $page1 = Node::create([
      'type' => 'book',
      'title' => '1st page',
      'book' => ['bid' => $book->id(), 'pid' => $book->id(), 'weight' => 0],
    ]);
    $page1->save();
    $page2 = Node::create([
      'type' => 'book',
      'title' => '2nd page',
      'book' => ['bid' => $book->id(), 'pid' => $book->id(), 'weight' => 1],
    ]);
    $page2->save();

    // Head to admin screen and attempt to re-order.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/structure/book/' . $book->id());

    $edit = [
      "table[book-admin-{$page1->id()}][title]" => '',
    ];
    $this->submitForm($edit, 'Save book pages');
    $this->assertSession()->pageTextContains('Title field is required.');

    $title = $this->randomString();
    $edit = [
      "table[book-admin-{$page1->id()}][title]" => $title,
    ];
    $this->submitForm($edit, 'Save book pages');
    $this->assertSession()->pageTextContains($title);

    $node = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->loadByProperties(['title' => $title]);
    $this->assertNotEmpty($node);
    $node = reset($node);
    $this->assertEquals($node->getTitle(), $title);
  }

  /**
   * Testing updated hierarchy after we remove one node from outline.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function testBookParentDelete(): void {
    $nodes = $this->createBook();
    $this->drupalLogin($this->adminUser);

    /*
     * Add Node 5 under Node 1.
     * Book
     *  |- Node 0
     *    |- Node 1
     *      | - Node 5
     *    |- Node 2
     *  |- Node 3
     *  |- Node 4
     */
    $book = $this->book;
    $child = 5;
    $nodes[$child] = $this->createBookNode($book->id(), $nodes[1]->id());

    // Remove Node 1 from outline which should move Node 5 up 1 level.
    $this->drupalGet($nodes[1]->toUrl()->toString() . '/outline/remove');
    $this->submitForm([], 'Remove');
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    $node_storage->resetCache();

    $this->drupalLogout();
    $this->drupalLogin($this->webUser);

    $this->checkBookNode($nodes[0], [$nodes[2], $nodes[5]], FALSE, $book, FALSE, [$book]);
  }

  /**
   * Tests the child ordering feature.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testChildOrdering(): void {
    // Create new book.
    $nodes = $this->createBook();

    $this->drupalLogin($this->adminUser);

    // Third node has no children, therefore no child order link.
    $this->drupalGet('node/' . $nodes[3]->id());
    $this->assertSession()->pageTextNotContains('Child Order');

    // First node in the book has 2 children.
    $this->drupalGet('node/' . $nodes[0]->id());
    $this->assertSession()->pageTextContains('Child order');
    $this->clickLink('Child order');

    // Verify children.
    $this->assertSession()->statusCodeEquals(200);
    $child1 = $nodes[1];
    $child2 = $nodes[2];
    $this->assertSession()->pageTextContains($child1->getTitle());
    $this->assertSession()->pageTextContains($child2->getTitle());

    // Verify weight changes save.
    $edit = [
      'table[book-admin-' . $child1->id() . '][weight]' => 0,
      'table[book-admin-' . $child2->id() . '][weight]' => 1,
    ];
    $this->submitForm($edit, 'Save book pages');
    $this->assertSession()->fieldValueEquals('table[book-admin-' . $child1->id() . '][weight]', 0);
    $this->assertSession()->fieldValueEquals('table[book-admin-' . $child2->id() . '][weight]', 1);
  }

}
