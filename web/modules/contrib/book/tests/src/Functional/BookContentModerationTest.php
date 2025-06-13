<?php

declare(strict_types=1);

namespace Drupal\Tests\book\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\user\Entity\Role;
use Drupal\user\UserInterface;

/**
 * Tests Book and Content Moderation integration.
 *
 * @group book
 */
class BookContentModerationTest extends BrowserTestBase {

  use BookTestTrait;
  use ContentModerationTestTrait;

  /**
   * A user with permission to make workflow transitions but not manage books.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $nonBookAdminUser;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'book',
    'block',
    'book_test',
    'content_moderation',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('system_breadcrumb_block');
    $this->drupalPlaceBlock('page_title_block');

    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'book');
    $workflow->save();

    // We need a user with additional content moderation permissions.
    $this->bookAuthor = $this->drupalCreateUser([
      'create new books',
      'create book content',
      'edit own book content',
      'add content to books',
      'access printer-friendly version',
      'view any unpublished content',
      'use editorial transition create_new_draft',
      'use editorial transition publish',
    ]);

    // Another user without manage book permissions to test updates to nodes
    // that are
    // 1. Not part of a book outline.
    // 2. Part of a book outline.
    $this->nonBookAdminUser = $this->drupalCreateUser([
      'create book content',
      'edit own book content',
      'use editorial transition create_new_draft',
      'use editorial transition publish',
      'access printer-friendly version',
      'view any unpublished content',
    ]);
  }

  /**
   * Tests that book drafts can not modify the book outline.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testBookWithPendingRevisions(): void {
    // Create two books.
    $book_1_nodes = $this->createBook(['moderation_state[0][state]' => 'published']);
    $book_1 = $this->book;

    $this->createBook(['moderation_state[0][state]' => 'published']);
    $book_2 = $this->book;

    $this->drupalLogin($this->bookAuthor);

    // Check that book pages display along with the correct outlines.
    $this->book = $book_1;
    $this->checkBookNode($book_1, [$book_1_nodes[0], $book_1_nodes[3], $book_1_nodes[4]], FALSE, FALSE, $book_1_nodes[0], []);
    $this->checkBookNode($book_1_nodes[0], [$book_1_nodes[1], $book_1_nodes[2]], $book_1, $book_1, $book_1_nodes[1], [$book_1]);

    // Create a new book page without actually attaching it to a book and create
    // a draft.
    $edit = [
      'title[0][value]' => $this->randomString(),
      'moderation_state[0][state]' => 'published',
    ];
    $this->drupalGet('node/add/book');
    $this->submitForm($edit, 'Save');
    $node = $this->drupalGetNodeByTitle($edit['title[0][value]']);
    $this->assertNotEmpty($node);

    $edit = [
      'moderation_state[0][state]' => 'draft',
    ];
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextNotContains('You can only change the book outline for the published version of this content.');

    // Create a book draft with no changes, then publish it.
    $this->drupalGet('node/' . $book_1->id() . '/edit');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextNotContains('You can only change the book outline for the published version of this content.');
    $edit = [
      'moderation_state[0][state]' => 'published',
    ];
    $this->drupalGet('node/' . $book_1->id() . '/edit');
    $this->submitForm($edit, 'Save');

    // Try to move Node 2 to a different parent.
    $edit = [
      'book[pid]' => $book_1_nodes[3]->id(),
      'moderation_state[0][state]' => 'draft',
    ];
    $this->drupalGet('node/' . $book_1_nodes[1]->id() . '/edit');
    $this->submitForm($edit, 'Save');

    $this->assertSession()->pageTextContains('You can only change the book outline for the published version of this content.');

    // Check that the book outline did not change.
    $this->book = $book_1;
    $this->checkBookNode($book_1, [$book_1_nodes[0], $book_1_nodes[3], $book_1_nodes[4]], FALSE, FALSE, $book_1_nodes[0], []);
    $this->checkBookNode($book_1_nodes[0], [$book_1_nodes[1], $book_1_nodes[2]], $book_1, $book_1, $book_1_nodes[1], [$book_1]);

    // Try to move Node 2 to a different book.
    $this->addNodeToBook(intval($book_2->id()), intval($book_1_nodes[1]->id()), ['moderation_state[0][state]' => 'draft']);

    $this->assertSession()->pageTextContains('You can only change the book outline for the published version of this content.');

    // Check that the book outline did not change.
    $this->book = $book_1;
    $this->checkBookNode($book_1, [$book_1_nodes[0], $book_1_nodes[3], $book_1_nodes[4]], FALSE, FALSE, $book_1_nodes[0], []);
    $this->checkBookNode($book_1_nodes[0], [$book_1_nodes[1], $book_1_nodes[2]], $book_1, $book_1, $book_1_nodes[1], [$book_1]);

    // Try to change the weight of Node 2.
    $edit = [
      'book[weight]' => 2,
      'moderation_state[0][state]' => 'draft',
    ];
    $this->drupalGet('node/' . $book_1_nodes[1]->id() . '/edit');
    $this->submitForm($edit, 'Save');

    $this->assertSession()->pageTextContains('You can only change the book outline for the published version of this content.');

    // Check that the book outline did not change.
    $this->book = $book_1;
    $this->checkBookNode($book_1, [$book_1_nodes[0], $book_1_nodes[3], $book_1_nodes[4]], FALSE, FALSE, $book_1_nodes[0], []);
    $this->checkBookNode($book_1_nodes[0], [$book_1_nodes[1], $book_1_nodes[2]], $book_1, $book_1, $book_1_nodes[1], [$book_1]);

    // Save a new draft revision for the node without any changes and check that
    // the error message is not displayed.
    $edit = [
      'moderation_state[0][state]' => 'draft',
    ];
    $this->drupalGet('node/' . $book_1_nodes[1]->id() . '/edit');
    $this->submitForm($edit, 'Save');

    $this->assertSession()->pageTextNotContains('You can only change the book outline for the published version of this content.');
  }

  /**
   * Tests that users who cannot manage books can still make node updates.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testNonBookAdminNodeUpdates(): void {
    // 1. First test that users who cannot manage books can make updates to
    // nodes that are not part of a book outline.
    $this->drupalLogin($this->nonBookAdminUser);
    // Create a new book page without actually attaching it to a book and create
    // a draft.
    $this->drupalGet('node/add/book');
    $this->assertSession()->statusCodeEquals(200);
    $edit = [
      'title[0][value]' => 'Some moderated content',
      'moderation_state[0][state]' => 'draft',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('Some moderated content has been created.');
    $node = $this->drupalGetNodeByTitle($edit['title[0][value]']);
    $this->assertNotEmpty($node);

    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->statusCodeEquals(200);
    // Publish the content.
    $edit = [
      'body[0][value]' => 'Second change non book admin user',
      'moderation_state[0][state]' => 'published',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextNotContains('You can only change the book outline for the published version of this content.');
    $this->assertSession()->pageTextContains('Some moderated content has been updated');

    // Now update content again, it should be successfully updated and not throw
    // any errors.
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->statusCodeEquals(200);
    $edit = [
      'moderation_state[0][state]' => 'draft',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextNotContains('You can only change the book outline for the published version of this content.');
    $this->assertSession()->pageTextContains('Some moderated content has been updated');

    // 2. Now test that users who cannot manage books can make updates to nodes
    // that are part of a book outline. As the non admin book user, publish the
    // content created above in order to be added to a book.
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->statusCodeEquals(200);
    $edit = [
      'moderation_state[0][state]' => 'published',
    ];
    $this->submitForm($edit, 'Save');

    // Create a book (as a book admin user).
    $book_1_nodes = $this->createBook(['moderation_state[0][state]' => 'published']);
    $book_1 = $this->book;

    // Now add the node created previously by the non book admin user to the
    // book created above. We need to grant additional permission for bookAuthor
    // to be able to edit the node owned by nonBookAdminUser.
    $role_ids = $this->bookAuthor->getRoles(TRUE);
    $role_id = reset($role_ids);
    $role = Role::load($role_id);
    $role->grantPermission('edit any book content');
    $role->save();
    $this->drupalLogin($this->bookAuthor);
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->statusCodeEquals(200);
    $edit = [
      'book[bid]' => $this->book->id(),
      'moderation_state[0][state]' => 'published',
    ];
    $this->submitForm($edit, 'Save');

    // Assert that the node has been added to the book.
    $this->assertSession()->pageTextNotContains('You can only change the book outline for the published version of this content.');
    $this->assertSession()->pageTextContains('Some moderated content has been updated');
    $this->checkBookNode($book_1, [
      $book_1_nodes[0],
      $book_1_nodes[3],
      $book_1_nodes[4],
      $node,
    ], FALSE, FALSE, $book_1_nodes[0], []);

    // Try to update the non book admin's node in the book as the user
    // that cannot manage books, it should be successfully updated and not
    // throw any errors.
    $this->drupalLogin($this->nonBookAdminUser);
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->statusCodeEquals(200);
    $edit = [
      'body[0][value]' => 'Change by non book admin user again',
      'moderation_state[0][state]' => 'draft',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextNotContains('You can only change the book outline for the published version of this content.');
    $this->assertSession()->pageTextContains('Some moderated content has been updated');

    $this->drupalLogout();
    $this->drupalLogin($this->bookAuthor);
    // Check that the book outline did not change.
    $this->book = $book_1;
    $this->checkBookNode($book_1, [
      $book_1_nodes[0],
      $book_1_nodes[3],
      $book_1_nodes[4],
      $node,
    ], FALSE, FALSE, $book_1_nodes[0], []);
    $this->checkBookNode($book_1_nodes[0], [
      $book_1_nodes[1],
      $book_1_nodes[2],
    ], FALSE, $book_1, $book_1_nodes[1], [$book_1]);
  }

}
