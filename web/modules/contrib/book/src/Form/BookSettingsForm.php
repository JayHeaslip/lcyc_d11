<?php

namespace Drupal\book\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure book settings for this site.
 *
 * @internal
 */
class BookSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'book_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['book.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('book.settings');

    $types = node_type_get_names();
    $form['book_allowed_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content types allowed in book outlines'),
      '#config_target' => new ConfigTarget('book.settings', 'allowed_types', toConfig: static::class . '::filterAndSortAllowedTypes'),
      '#options' => $types,
      '#description' => $this->t('Users with the %outline-perm permission can add all content types.', ['%outline-perm' => $this->t('Add non-book content to outlines')]),
      '#required' => FALSE,
    ];
    $form['book_child_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Content type for the <em>Add child page</em> link'),
      '#config_target' => 'book.settings:child_type',
      '#options' => $types,
      '#required' => FALSE,
    ];
    $form['book_sort'] = [
      '#type' => 'radios',
      '#title' => $this->t('Book list sorting for administrative pages, outlines, and menus'),
      '#default_value' => $config->get('book_sort'),
      '#config_target' => 'book.settings:book_sort',
      '#options' => [
        'weight' => 'Sort by weight',
        'title' => 'Sort alphabetically by title',
      ],
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Transformation callback for the book_allowed_types config value.
   *
   * @param array $allowed_types
   *   The config value to transform.
   *
   * @return array
   *   The transformed value.
   */
  public static function filterAndSortAllowedTypes(array $allowed_types): array {
    $allowed_types = array_filter($allowed_types);
    // We need to save the allowed types in an array ordered by machine_name so
    // that we can save them in the correct order if node type changes.
    // @see book_node_type_update().
    sort($allowed_types);
    return $allowed_types;
  }

}
