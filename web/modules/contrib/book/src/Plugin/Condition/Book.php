<?php

namespace Drupal\book\Plugin\Condition;

use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\book\BookManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Book' condition.
 */
#[Condition(
  id: "book",
  label: new TranslatableMarkup("Book"),
  context_definitions: [
    "node" => new EntityContextDefinition(
      data_type: "entity:node",
      label: new TranslatableMarkup("Node"),
    ),
  ]
)]
class Book extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Creates a new NodeType instance.
   *
   * @param \Drupal\book\BookManagerInterface $bookManager
   *   The book manager.
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(
    protected BookManagerInterface $bookManager,
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get('book.manager'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $options = [];
    $books = $this->bookManager->getAllBooks();
    foreach ($books as $bid => $book) {
      $options[$bid] = $book['title'];
    }
    $form['books'] = [
      '#title' => $this->t('Books'),
      '#type' => 'checkboxes',
      '#options' => $options,
      '#default_value' => $this->configuration['books'],
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['books'] = array_filter($form_state->getValue('books'));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    $books = array_intersect_key($this->bookManager->getAllBooks(), array_combine($this->configuration['books'], $this->configuration['books']));
    $book_titles = array_column($books, 'title');

    if (count($book_titles) > 1) {
      $last = array_pop($book_titles);
      $book_titles = implode(', ', $book_titles);
      return $this->t('The node is part of the @books or @last books', ['@books' => $book_titles, '@last' => $last]);
    }
    elseif (count($book_titles) == 1) {
      $book = reset($book_titles);
      return $this->t('The node is part of the @book book', ['@book' => $book]);
    }
    return $this->t('The node is not part of any books');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   */
  public function evaluate(): bool {
    if (empty($this->configuration['books']) && !$this->isNegated()) {
      return TRUE;
    }
    $node = $this->getContextValue('node');
    if (!empty($node->book['bid'])) {
      return !empty($this->configuration['books'][$node->book['bid']]);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'books' => [],
    ] + parent::defaultConfiguration();
  }

}
