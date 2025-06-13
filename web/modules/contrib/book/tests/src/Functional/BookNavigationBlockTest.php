<?php

declare(strict_types=1);

namespace Drupal\Tests\book\Functional;

use Drupal\user\RoleInterface;

/**
 * Create a book, add pages, and test book interface.
 *
 * @group book
 * @group #slow
 */
class BookNavigationBlockTest extends BookTestBase {

  /**
   * Tests the functionality of the book navigation block.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testBookNavigationBlock(): void {
    $this->drupalLogin($this->adminUser);

    // Enable the block.
    $block = $this->drupalPlaceBlock('book_navigation');

    // Give anonymous users the permission 'node test view'.
    $edit = [];
    $edit[RoleInterface::ANONYMOUS_ID . '[node test view]'] = TRUE;
    $this->drupalGet('admin/people/permissions/' . RoleInterface::ANONYMOUS_ID);
    $this->submitForm($edit, 'Save permissions');
    $this->assertSession()->pageTextContains('The changes have been saved.');

    // Test correct display of the block.
    $nodes = $this->createBook();
    $this->drupalGet('<front>');
    // Book navigation block.
    $this->assertSession()->pageTextContains($block->label());
    // Link to book root.
    $this->assertSession()->pageTextContains($this->book->label());
    // No links to individual book pages.
    $this->assertSession()->pageTextNotContains($nodes[0]->label());

    // Ensure that an unpublished node does not appear in the navigation for a
    // user without access. By unpublishing a parent page, child pages should
    // not appear in the navigation. The node_access_test module is disabled
    // since it interferes with this logic.
    $nodes[0]->setUnPublished();
    $nodes[0]->save();

    // Verify block still appears on unpublished page. Doing this before
    // uninstalling node_access_test.
    $this->drupalGet($nodes[0]->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($block->label());

    /** @var \Drupal\Core\Extension\ModuleInstaller $installer */
    $installer = $this->container->get('module_installer');
    $installer->uninstall(['node_access_test']);
    node_access_rebuild();

    // Verify the user does not have access to the unpublished node.
    $this->assertFalse($nodes[0]->access('view', $this->webUser));

    // Verify the unpublished book page does not appear in the navigation.
    $this->drupalLogin($this->webUser);
    $this->drupalGet($nodes[0]->toUrl());
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet($this->book->toUrl());
    $this->assertSession()->responseNotContains($nodes[0]->getTitle());
    $this->assertSession()->responseNotContains($nodes[1]->getTitle());
    $this->assertSession()->responseNotContains($nodes[2]->getTitle());
  }

  /**
   * Tests the top-level page title setting of the book navigation block.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testBookNavigationBlockWithTopLevelPageTitle(): void {
    // Enable the block.
    $block = $this->drupalPlaceBlock('book_navigation', [
      'block_mode' => 'book pages',
      'use_top_level_title' => TRUE,
    ]);

    // Create a book.
    $nodes = $this->createBook();

    // Give anonymous users the permission 'node test view'.
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, ['node test view']);

    $book = $this->book;
    // Change the book top-level title.
    $book->setTitle('Top-level node title');
    $book->save();

    $block_xpath = $this->assertSession()->buildXPathQuery('//div[@id = :id]/h2', [
      ':id' => 'block-' . $block->id(),
    ]);

    // Check that the block title is the top-level page title on the book
    // summary.
    $this->drupalGet($book->toUrl());
    $this->assertBlockAppears($block);
    $this->assertSession()->elementTextEquals('xpath', $block_xpath, 'Top-level node title');

    // Check that the block title is the top-level page title on a deep book
    // page.
    $this->drupalGet($nodes[0]->toUrl());
    $this->assertBlockAppears($block);
    $this->assertSession()->elementTextEquals('xpath', $block_xpath, 'Top-level node title');

    // Check for presence of is-active class.
    $this->drupalGet($nodes[2]->toUrl());
    $link = $this->assertSession()->elementExists('xpath', '//a[contains(@href, "' . $nodes[2]->toUrl()->toString() . '")]');
    $this->assertTrue($link->hasAttribute('class'));
    $this->assertEquals('is-active', $link->getAttribute('class'));
  }

  /**
   * Tests book navigation block access options.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testNavigationBlockAccessOptions(): void {
    $this->drupalLogin($this->adminUser);

    // Give anonymous users the permission 'node test view'.
    $edit = [];
    $edit[RoleInterface::ANONYMOUS_ID . '[node test view]'] = TRUE;
    $this->drupalGet('admin/people/permissions/' . RoleInterface::ANONYMOUS_ID);
    $this->submitForm($edit, 'Save permissions');
    $this->assertSession()->pageTextContains('The changes have been saved.');

    $block = $this->drupalPlaceBlock('book_navigation', [
      'block_mode' => 'primary book page',
    ]);

    // Create a book.
    $nodes = $this->createBook();

    $this->drupalLogin($this->webUser);
    // Verify block appears on book page.
    $this->drupalGet($this->book->toUrl());
    $this->assertSession()->pageTextContains($block->label());

    // Verify block does not appear on child page.
    $this->drupalGet($nodes[0]->toUrl());
    $this->assertSession()->pageTextNotContains($block->label());

    $block->delete();

    $block = $this->drupalPlaceBlock('book_navigation', [
      'block_mode' => 'child book pages',
    ]);

    // Verify block does not appear on book page.
    $this->drupalGet($this->book->toUrl());
    $this->assertSession()->pageTextNotContains($block->label());

    // Verify block does appear on child page.
    $this->drupalGet($nodes[0]->toUrl());
    $this->assertSession()->pageTextContains($block->label());
  }

  /**
   * Tests the book navigation block when an access module is installed.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testNavigationBlockOnAccessModuleInstalled(): void {
    $this->drupalLogin($this->adminUser);
    $this->container->get('theme_installer')->install(['olivero']);
    $this->config('system.theme')->set('default', 'olivero')->save();
    $block = $this->drupalPlaceBlock('book_navigation', [
      'block_mode' => 'book pages',
      'region' => 'sidebar',
    ]);

    // Give anonymous users the permission 'node test view'.
    $edit = [];
    $edit[RoleInterface::ANONYMOUS_ID . '[node test view]'] = TRUE;
    $this->drupalGet('admin/people/permissions/' . RoleInterface::ANONYMOUS_ID);
    $this->submitForm($edit, 'Save permissions');
    $this->assertSession()->pageTextContains('The changes have been saved.');

    // Create a book.
    $this->createBook();

    // Test correct display of the block to registered users.
    $this->drupalLogin($this->webUser);
    $this->drupalGet('node/' . $this->book->id());
    $this->assertSession()->pageTextContains($block->label());
    $this->assertSession()->elementExists('css', '.region--sidebar');
    $this->drupalLogout();

    // Test correct display of the block to anonymous users.
    $this->drupalGet('node/' . $this->book->id());
    $this->assertSession()->pageTextContains($block->label());

    // Test the 'book pages' block_mode setting.
    $this->drupalGet('<front>');
    $this->assertSession()->pageTextNotContains($block->label());
    $this->assertSession()->elementNotExists('css', '.region--sidebar');
  }

  /**
   * Tests the book navigation block when book is unpublished.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBookNavigationBlockOnUnpublishedBook(): void {
    // Create a new book.
    $this->createBook();

    // Create administrator user.
    $administratorUser = $this->drupalCreateUser([
      'administer blocks',
      'administer nodes',
      'bypass node access',
    ]);
    $this->drupalLogin($administratorUser);

    // Enable the block with "Show block only on book pages" mode.
    $this->drupalPlaceBlock('book_navigation', ['block_mode' => 'book pages']);

    // Unpublish book node.
    $edit = ['status[value]' => FALSE];
    $this->drupalGet('node/' . $this->book->id() . '/edit');
    $this->submitForm($edit, 'Save');

    // Test node page.
    $this->drupalGet('node/' . $this->book->id());
    // Unpublished book with "Show block only on book pages" book navigation
    // settings.
    $this->assertSession()->pageTextContains($this->book->label());
  }

  /**
   * Tests books in Book Navigation Block are correctly ordered by weight.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testBookBlockOrderByWeight(): void {
    $this->drupalLogin($this->adminUser);

    // Create two books.
    $book1 = $this->createBookNode('new');
    $book2 = $this->createBookNode('new');

    // Change weight of second book, so it should appear above book 1.
    $this->drupalGet('node/' . $book2->id() . '/outline');
    $this->submitForm(['book[weight]' => -5], 'Update book outline');
    $this->assertSession()->statusMessageContains('The book outline has been updated');

    // Place a Book navigation block.
    $this->drupalPlaceBlock('book_navigation');
    $this->drupalGet('<front>');
    $this->assertSession()->responseMatches(sprintf('/%s.*%s/s', $book2->getTitle(), $book1->getTitle()));
  }

}
