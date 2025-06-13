<?php

namespace Drupal\Tests\book\Kernel\Plugin\Condition;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests that conditions provided by the book module, are working.
 *
 * @group book
 */
class BookConditionTest extends KernelTestBase {

  use UserCreationTrait {
    createUser as drupalCreateUser;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'filter', 'text', 'node', 'book'];

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('book', ['book']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node', 'book', 'field']);

    // Create user 1 who has special permissions.
    $this->setCurrentUser($this->drupalCreateUser());
  }

  /**
   * Tests the 'book' condition for checking if a node is part of a given book.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function testBookCondition(): void {
    $content_type = NodeType::create([
      'type' => $this->randomMachineName(),
      'name' => $this->randomString(),
    ]);
    $content_type->save();
    $book_config = $this->config('book.settings');
    $allowed_types = $book_config->get('allowed_types');
    $allowed_types[] = $content_type->id();
    $book_config->set('allowed_types', $allowed_types)->save();

    // Create a regular node and three books including one with children.
    $node_1 = Node::create(['title' => $this->randomString(), 'type' => $content_type->id()]);
    $node_1->save();

    $book_1 = Node::create(['title' => $this->randomString(), 'type' => $content_type->id()]);
    $book_1->book['bid'] = 'new';
    $book_1->save();
    $book_1_child = Node::create(['title' => $this->randomString(), 'type' => $content_type->id()]);
    $book_1_child->book['bid'] = $book_1->id();
    $book_1_child->book['pid'] = $book_1->id();
    $book_1_child->save();

    $book_2 = Node::create(['title' => $this->randomString(), 'type' => $content_type->id()]);
    $book_2->book['bid'] = 'new';
    $book_2->save();

    $book_3 = Node::create(['title' => $this->randomString(), 'type' => $content_type->id()]);
    $book_3->book['bid'] = 'new';
    $book_3->save();

    // Obtain the condition manager.
    $manager = $this->container->get('plugin.manager.condition');

    // Generate condition without filtering.
    $condition = $manager->createInstance('book')->setConfig('books', []);

    // Check for correct summary.
    $this->assertEquals('The node is not part of any books', $condition->summary());

    // Assert no conditions, no book.
    $condition->setContextValue('node', $node_1);
    $this->assertTrue($condition->execute(), 'No book filter passes for a non-book node.');

    // Assert no conditions, book.
    $condition->setContextValue('node', $book_1);
    $this->assertTrue($condition->execute(), 'No book filter passes for a parent node.');

    // Assert no conditions, book child.
    $condition->setContextValue('node', $book_1_child);
    $this->assertTrue($condition->execute(), 'No book filter passes for a child node.');

    // Configure a book condition.
    $condition->setConfig('books', [$book_1->id() => $book_1->id()]);

    // Check for correct summary.
    $this->assertEquals(new FormattableMarkup('The node is part of the @book book', ['@book' => $book_1->label()]), $condition->summary());

    // Assert conditions, node.
    $condition->setContextValue('node', $node_1);
    $this->assertFalse($condition->execute(), 'Book filter fails for a non-book node.');

    // Assert conditions, book.
    $condition->setContextValue('node', $book_1);
    $this->assertTrue($condition->execute(), 'Book filter passes for a parent node of that book.');

    // Assert conditions, book child.
    $condition->setContextValue('node', $book_1_child);
    $this->assertTrue($condition->execute(), 'Book filter passes for a child node of that book.');

    // Assert conditions, book.
    $condition->setContextValue('node', $book_2);
    $this->assertFalse($condition->execute(), 'Book filter fails for a parent node of another book.');

    // Configure a condition for two books.
    $condition->setConfig('books', [
      $book_1->id() => $book_1->id(),
      $book_2->id() => $book_2->id(),
      $book_3->id() => $book_3->id(),
    ]);

    // Check for correct summary.
    $book_titles = $book_1->label() . ', ' . $book_2->label();
    $this->assertEquals(new FormattableMarkup('The node is part of the @books or @last books',
      [
        '@books' => $book_titles,
        '@last' => $book_3->label(),
      ]),
      $condition->summary());

    // Assert conditions, book.
    $condition->setContextValue('node', $book_1);
    $this->assertTrue($condition->execute(), 'More books filter passes for a parent node of the first book.');

    // Assert conditions, book child.
    $condition->setContextValue('node', $book_1_child);
    $this->assertTrue($condition->execute(), 'More books filter passes for a child node of the first book.');

    // Assert conditions, book.
    $condition->setContextValue('node', $book_2);
    $this->assertTrue($condition->execute(), 'More books filter passes for a parent node of the second book.');

    // Assert conditions, book.
    $condition->setContextValue('node', $book_2);
    $this->assertTrue($condition->execute(), 'More books filter passes for a parent node of the third book.');
  }

}
