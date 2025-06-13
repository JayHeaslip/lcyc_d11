<?php

declare(strict_types=1);

namespace Drupal\Tests\book\Unit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\book\BookManager;
use Drupal\book\BookOutlineStorageInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @coversDefaultClass \Drupal\book\BookManager
 * @group book
 */
class BookManagerTest extends UnitTestCase {

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject|EntityTypeManager $entityTypeManager;

  /**
   * The mocked language manager.
   *
   * @var \Drupal\Core\Language\LanguageManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject|LanguageManager $languageManager;

  /**
   * The mocked entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject|EntityRepositoryInterface $entityRepository;

  /**
   * The mocked config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory|\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject|ConfigFactory $configFactory;

  /**
   * The mocked translation manager.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject|TranslationInterface $translation;

  /**
   * The mocked renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject|RendererInterface $renderer;

  /**
   * The tested book manager.
   *
   * @var \Drupal\book\BookManager
   */
  protected BookManager $bookManager;

  /**
   * Book outline storage.
   *
   * @var \Drupal\book\BookOutlineStorageInterface
   */
  protected BookOutlineStorageInterface $bookOutlineStorage;

  /**
   * Current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject|RouteMatchInterface $routeMatch;

  /**
   * {@inheritdoc}
   *
   * @throws \PHPUnit\Framework\MockObject\Exception
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->translation = $this->getStringTranslationStub();
    $this->configFactory = $this->getConfigFactoryStub();
    $this->bookOutlineStorage = $this->createMock('Drupal\book\BookOutlineStorageInterface');
    $this->renderer = $this->createMock('\Drupal\Core\Render\RendererInterface');
    $this->languageManager = $this->createMock('Drupal\Core\Language\LanguageManagerInterface');
    $this->entityRepository = $this->createMock('Drupal\Core\Entity\EntityRepositoryInterface');
    $this->routeMatch = $this->createMock(RouteMatchInterface::class);
    // Used for both book manager cache services: backend chain and memory.
    $cache = $this->createMock(CacheBackendInterface::class);
    $this->bookManager = new BookManager($this->entityTypeManager, $this->translation, $this->configFactory, $this->bookOutlineStorage, $this->renderer, $this->languageManager, $this->entityRepository, $cache, $cache, $this->routeMatch);
  }

  /**
   * Tests the getBookParents() method.
   *
   * @dataProvider providerTestGetBookParents
   */
  public function testGetBookParents($book, $parent, $expected): void {
    $this->assertEquals($expected, $this->bookManager->getBookParents($book, $parent));
  }

  /**
   * Provides test data for testGetBookParents.
   *
   * @return array
   *   The test data.
   */
  public static function providerTestGetBookParents(): array {
    $empty = [
      'p1' => 0,
      'p2' => 0,
      'p3' => 0,
      'p4' => 0,
      'p5' => 0,
      'p6' => 0,
      'p7' => 0,
      'p8' => 0,
      'p9' => 0,
    ];
    return [
      // Provides a book without an existing parent.
      [
        ['pid' => 0, 'nid' => 12],
        [],
        ['depth' => 1, 'p1' => 12] + $empty,
      ],
      // Provides a book with an existing parent.
      [
        ['pid' => 11, 'nid' => 12],
        ['nid' => 11, 'depth' => 1, 'p1' => 11],
        ['depth' => 2, 'p1' => 11, 'p2' => 12] + $empty,
      ],
      // Provides a book with two existing parents.
      [
        ['pid' => 11, 'nid' => 12],
        ['nid' => 11, 'depth' => 2, 'p1' => 10, 'p2' => 11],
        ['depth' => 3, 'p1' => 10, 'p2' => 11, 'p3' => 12] + $empty,
      ],
    ];
  }

}
