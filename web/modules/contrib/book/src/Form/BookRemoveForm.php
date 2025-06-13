<?php

namespace Drupal\book\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\book\BookManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Remove form for book module.
 *
 * @internal
 */
class BookRemoveForm extends ConfirmFormBase {

  /**
   * The node representing the book.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $node;

  /**
   * Constructs a BookRemoveForm object.
   *
   * @param \Drupal\book\BookManagerInterface $bookManager
   *   The book manager.
   */
  public function __construct(protected BookManagerInterface $bookManager) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('book.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'book_remove_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    $this->node = $node;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    $title = ['%title' => $this->node->label()];
    if ($this->node->book['has_children']) {
      return $this->t('%title has associated child pages, which will be relocated automatically to maintain their connection to the book. To recreate the hierarchy (as it was before removing this page), %title may be added again using the Outline tab, and each of its former child pages will need to be relocated manually.', $title);
    }
    else {
      return $this->t('%title may be added to hierarchy again using the Outline tab.', $title);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Remove');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to remove %title from the book hierarchy?', ['%title' => $this->node->label()]);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function getCancelUrl(): Url {
    return $this->node->toUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($this->bookManager->checkNodeIsRemovable($this->node)) {
      $this->bookManager->deleteFromBook($this->node->id());
      $this->messenger()->addStatus($this->t('The post has been removed from the book.'));
    }
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
