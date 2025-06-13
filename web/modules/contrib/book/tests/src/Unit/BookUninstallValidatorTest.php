<?php

declare(strict_types=1);

namespace Drupal\Tests\book\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\book\BookUninstallValidator;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @coversDefaultClass \Drupal\book\BookUninstallValidator
 * @group book
 */
class BookUninstallValidatorTest extends UnitTestCase {

  /**
   * BookUninstallValidator object.
   *
   * @var \Drupal\book\BookUninstallValidator|\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject|BookUninstallValidator $bookUninstallValidator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->bookUninstallValidator = $this->getMockBuilder('Drupal\book\BookUninstallValidator')
      ->disableOriginalConstructor()
      ->onlyMethods(['hasBookOutlines', 'hasBookNodes'])
      ->getMock();
    $this->bookUninstallValidator->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * @covers ::validate
   */
  public function testValidateNotBook(): void {
    $this->bookUninstallValidator->expects($this->never())
      ->method('hasBookOutlines');
    $this->bookUninstallValidator->expects($this->never())
      ->method('hasBookNodes');

    $module = 'not_book';
    $expected = [];
    $reasons = $this->bookUninstallValidator->validate($module);
    $this->assertEquals($expected, $reasons);
  }

  /**
   * @covers ::validate
   */
  public function testValidateEntityQueryWithoutResults(): void {
    $this->bookUninstallValidator->expects($this->once())
      ->method('hasBookOutlines')
      ->willReturn(FALSE);
    $this->bookUninstallValidator->expects($this->once())
      ->method('hasBookNodes')
      ->willReturn(FALSE);

    $module = 'book';
    $expected = [];
    $reasons = $this->bookUninstallValidator->validate($module);
    $this->assertEquals($expected, $reasons);
  }

  /**
   * @covers ::validate
   */
  public function testValidateEntityQueryWithResults(): void {
    $this->bookUninstallValidator->expects($this->once())
      ->method('hasBookOutlines')
      ->willReturn(FALSE);
    $this->bookUninstallValidator->expects($this->once())
      ->method('hasBookNodes')
      ->willReturn(TRUE);

    $module = 'book';
    $expected = ['To uninstall Book, delete all content that has the Book content type'];
    $reasons = $this->bookUninstallValidator->validate($module);
    $this->assertEquals($expected, $reasons);
  }

  /**
   * @covers ::validate
   */
  public function testValidateOutlineStorage(): void {
    $this->bookUninstallValidator->expects($this->once())
      ->method('hasBookOutlines')
      ->willReturn(TRUE);
    $this->bookUninstallValidator->expects($this->never())
      ->method('hasBookNodes');

    $module = 'book';
    $expected = ['To uninstall Book, delete all content that is part of a book'];
    $reasons = $this->bookUninstallValidator->validate($module);
    $this->assertEquals($expected, $reasons);
  }

}
