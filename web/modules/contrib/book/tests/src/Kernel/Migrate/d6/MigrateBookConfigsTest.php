<?php

namespace Drupal\Tests\book\Kernel\Migrate\d6;

use Drupal\Tests\SchemaCheckTestTrait;
use Drupal\Tests\migrate_drupal\Kernel\d6\MigrateDrupal6TestBase;

/**
 * Upgrade variables to book.settings.yml.
 *
 * @group book
 */
class MigrateBookConfigsTest extends MigrateDrupal6TestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['book'];

  /**
   * Gets the path to the fixture file.
   */
  protected function getFixtureFilePath(): string {
    return __DIR__ . '/../../../../fixtures/drupal6.php';
  }

  /**
   * Data provider for testBookSettings().
   *
   * @return array
   *   The data for each test scenario.
   */
  public static function providerBookSettings(): array {
    return [
      // d6_book_settings was renamed to book_settings, but use the old alias to
      // prove that it works.
      // @see book_migration_plugins_alter()
      ['d6_book_settings'],
      ['book_settings'],
    ];
  }

  /**
   * Tests migration of book variables to book.settings.yml.
   *
   * @dataProvider providerBookSettings
   *
   * @throws \Exception
   */
  public function testBookSettings($migration_id): void {
    $this->executeMigration($migration_id);

    $config = $this->config('book.settings');
    $this->assertSame('book', $config->get('child_type'));
    $this->assertSame(['book'], $config->get('allowed_types'));
    $this->assertConfigSchema($this->container->get('config.typed'), 'book.settings', $config->get());
  }

}
