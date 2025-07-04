<?php

namespace Drupal\Tests\book\Kernel\Migrate\d7;

use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;
use Drupal\node\Entity\Node;

/**
 * Tests migration of book structures from Drupal 7.
 *
 * @group book
 */
class MigrateBookTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'book',
    'menu_ui',
    'node',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('book', ['book']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig('book');
    $this->migrateUsers(FALSE);
    $this->migrateContentTypes();
    $this->executeMigrations([
      'd7_node',
      'd7_book',
    ]);
  }

  /**
   * Gets the path to the fixture file.
   */
  protected function getFixtureFilePath(): string {
    return __DIR__ . '/../../../../fixtures/drupal7.php';
  }

  /**
   * Tests the Drupal 7 book structure to Drupal 8 migration.
   */
  public function testBook(): void {
    $nodes = Node::loadMultiple([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
    $this->assertSame('1', $nodes[1]->book['bid']);
    $this->assertSame('0', $nodes[1]->book['pid']);

    $this->assertSame('1', $nodes[2]->book['bid']);
    $this->assertSame('1', $nodes[2]->book['pid']);

    $this->assertSame('1', $nodes[3]->book['bid']);
    $this->assertSame('1', $nodes[3]->book['pid']);

    $this->assertSame('1', $nodes[4]->book['bid']);
    $this->assertSame('3', $nodes[4]->book['pid']);

    $this->assertSame('1', $nodes[5]->book['bid']);
    $this->assertSame('3', $nodes[5]->book['pid']);

    $this->assertSame('6', $nodes[6]->book['bid']);
    $this->assertSame('0', $nodes[6]->book['pid']);

    $this->assertSame('6', $nodes[7]->book['bid']);
    $this->assertSame('6', $nodes[7]->book['pid']);

    $this->assertSame('6', $nodes[8]->book['bid']);
    $this->assertSame('6', $nodes[8]->book['pid']);

    $this->assertSame('6', $nodes[9]->book['bid']);
    $this->assertSame('8', $nodes[9]->book['pid']);

    $this->assertSame('6', $nodes[10]->book['bid']);
    $this->assertSame('8', $nodes[10]->book['pid']);

    $tree = \Drupal::service('book.manager')->bookTreeAllData(1);
    $this->assertSame('1', $tree['50000 Birds 1']['link']['nid']);
    $this->assertSame('2', $tree['50000 Birds 1']['below']['50000 Emu 2']['link']['nid']);
    $this->assertSame([], $tree['50000 Birds 1']['below']['50000 Emu 2']['below']);
    $this->assertSame('3', $tree['50000 Birds 1']['below']['50000 Parrots 3']['link']['nid']);
    $this->assertSame('4', $tree['50000 Birds 1']['below']['50000 Parrots 3']['below']['50000 Kea 4']['link']['nid']);
    $this->assertSame([], $tree['50000 Birds 1']['below']['50000 Parrots 3']['below']['50000 Kea 4']['below']);
    $this->assertSame('5', $tree['50000 Birds 1']['below']['50000 Parrots 3']['below']['50000 Kakapo 5']['link']['nid']);
    $this->assertSame([], $tree['50000 Birds 1']['below']['50000 Parrots 3']['below']['50000 Kakapo 5']['below']);

    $tree = \Drupal::service('book.manager')->bookTreeAllData(6);
    $this->assertSame('6', $tree['50000 Tree 6']['link']['nid']);
    $this->assertSame('7', $tree['50000 Tree 6']['below']['50000 Rimu 7']['link']['nid']);
    $this->assertSame([], $tree['50000 Tree 6']['below']['50000 Rimu 7']['below']);
    $this->assertSame('8', $tree['50000 Tree 6']['below']['50000 Oaks 8']['link']['nid']);
    $this->assertSame('9', $tree['50000 Tree 6']['below']['50000 Oaks 8']['below']['50000 Cork oak 9']['link']['nid']);
    $this->assertSame([], $tree['50000 Tree 6']['below']['50000 Oaks 8']['below']['50000 Cork oak 9']['below']);
    $this->assertSame('10', $tree['50000 Tree 6']['below']['50000 Oaks 8']['below']['50000 White oak 10']['link']['nid']);
    $this->assertSame([], $tree['50000 Tree 6']['below']['50000 Oaks 8']['below']['50000 White oak 10']['below']);

    // Set the d7_book migration to update and re-run the migration.
    $id_map = $this->migration->getIdMap();
    $id_map->prepareUpdate();
    $this->executeMigration('d7_book');
  }

}
