<?php

namespace Drupal\book\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validation constraint for changing the book outline in pending revisions.
 */
#[Constraint(
  id: 'BookOutline',
  label: new TranslatableMarkup('Book outline.', [], ['context' => 'Validation'])
)]
class BookOutlineConstraint extends SymfonyConstraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public string $message = 'You can only change the book outline for the <em>published</em> version of this content.';

  /**
   * The default violation message with outline link.
   *
   * @var string
   */
  public string $messageWithLink = 'You can only change the book outline for the <em>published</em> version of this content. Visit <a href=":link">Book outlines</a> page to make changes.';

}
