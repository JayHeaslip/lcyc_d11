<?php

declare(strict_types=1);

namespace Drupal\Tests\book\Functional;

use Drupal\Core\Url;

/**
 * Tests existence of book local tasks.
 *
 * @group book
 */
class BookLocalTasksTest extends BookTestBase {

  /**
   * Tests local task existence.
   *
   * Create a book with some nodes. Get the path of the top level page and
   * send it to assertLocalTasks().
   */
  public function testBookNodeLocalTasks(): void {
    $book_nodes = $this->createBook();
    $this->drupalLogin($this->adminUser);
    $this->drupalGet($book_nodes[0]->toUrl());
    $path = $book_nodes[0]->toUrl()->toString();
    $this->assertLocalTasks($path);
  }

  /**
   * Asserts that the entity's local tasks are visible.
   *
   * Links to local tasks have the attribute "data-drupal-link-system-path".
   */
  protected function assertLocalTasks($path) {
    $this->assertSession()->elementExists('css', "a[href='$path'][data-drupal-link-system-path]");
    $this->assertSession()->elementExists('css', "a[href='$path/edit'][data-drupal-link-system-path]");
    $this->assertSession()->elementExists('css', "a[href='$path/delete'][data-drupal-link-system-path]");
    $this->assertSession()->elementExists('css', "a[href='$path/revisions'][data-drupal-link-system-path]");
    $this->assertSession()->elementExists('css', "a[href='$path/child-ordering'][data-drupal-link-system-path]");
  }

  /**
   * Tests local task existence on the admin settings page.
   */
  public function testBookAdminLocalTasks(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet(Url::fromRoute('book.admin')->toString());

    $adminRoutes = ['book.admin', 'book.settings'];

    foreach ($adminRoutes as $route) {
      $route = Url::fromRoute($route)->toString();
      $this->assertSession()->elementExists('css', "a[href='$route'][data-drupal-link-system-path]");
    }
  }

}
