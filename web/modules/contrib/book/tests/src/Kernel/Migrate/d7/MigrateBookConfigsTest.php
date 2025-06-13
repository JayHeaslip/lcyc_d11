<?php

namespace Drupal\Tests\book\Kernel\Migrate\d7;

use Drupal\Tests\SchemaCheckTestTrait;
use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Tests the migration of Book settings.
 *
 * @group book
 */
class MigrateBookConfigsTest extends MigrateDrupal7TestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['book', 'node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->executeMigration('book_settings');
  }

  /**
   * Gets the path to the fixture file.
   */
  protected function getFixtureFilePath(): string {
    return __DIR__ . '/../../../../fixtures/drupal7.php';
  }

  /**
   * Tests migration of book variables to book.settings.yml.
   *
   * @throws \Exception
   */
  public function testBookSettings(): void {
    $config = $this->config('book.settings');
    $this->assertSame('book', $config->get('child_type'));
    $this->assertSame(['book'], $config->get('allowed_types'));
    $this->assertConfigSchema($this->container->get('config.typed'), 'book.settings', $config->get());
  }

}
