<?php

declare(strict_types=1);

namespace Drupal\Tests\book\Functional\Comment;

use Drupal\Tests\BrowserTestBase;
use Drupal\comment\CommentInterface;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\node\Entity\Node;

/**
 * Tests visibility of comments on book pages.
 *
 * @group book
 */
class CommentBookTest extends BrowserTestBase {

  use CommentTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['book', 'comment'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create comment field on book.
    $this->addDefaultCommentField('node', 'book');
  }

  /**
   * Tests comments in book export.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testBookCommentPrint(): void {
    $book_node = Node::create([
      'type' => 'book',
      'title' => 'Book title',
      'body' => 'Book body',
    ]);
    $book_node->book['bid'] = 'new';
    $book_node->save();

    $comment_subject = $this->randomMachineName();
    $comment_body = $this->randomMachineName();
    $comment = Comment::create([
      'subject' => $comment_subject,
      'comment_body' => $comment_body,
      'entity_id' => $book_node->id(),
      'entity_type' => 'node',
      'field_name' => 'comment',
      'status' => CommentInterface::PUBLISHED,
    ]);
    $comment->save();

    $commenting_user = $this->drupalCreateUser([
      'access printer-friendly version',
      'access comments',
      'post comments',
    ]);
    $this->drupalLogin($commenting_user);

    $this->drupalGet('node/' . $book_node->id());

    $this->assertSession()->pageTextContains($comment_subject);
    $this->assertSession()->pageTextContains($comment_body);
    $this->assertSession()->pageTextContains('Add new comment');
    // Ensure that the comment form subject field exists.
    $this->assertSession()->fieldExists('subject[0][value]');

    $this->drupalGet('book/export/html/' . $book_node->id());

    $this->assertSession()->pageTextContains('Comments');
    $this->assertSession()->pageTextContains($comment_subject);
    $this->assertSession()->pageTextContains($comment_body);

    $this->assertSession()->pageTextNotContains('Add new comment');
    // Verify that the comment form subject field is not found.
    $this->assertSession()->fieldNotExists('subject[0][value]');
  }

}
