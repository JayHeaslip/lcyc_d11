<?php

namespace Drupal\views_enhancements\Plugin\views\filter;

use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Hardcoded filter: Only show events with spaces remaining > 0.
 *
 * @ViewsFilter("views_enhancements_spaces_remaining")
 */
class SpacesRemaining extends FilterPluginBase {

  protected $valueTitle;

  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->valueTitle = t('Spaces remaining > 0 (hardcoded)');
  }

  /**
   * No configuration needed since hardcoded.
   */
  public function buildOptionsForm(&$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $form['info'] = [
      '#markup' => '<p>' . $this->t('This filter is hardcoded to show only events with >0 spaces remaining. No options available.') . '</p>',
    ];
  }

  public function query() {

    $this->query->addWhereExpression(
      $this->options['group'],
      'EXISTS (
        SELECT 1 FROM {registration_settings_field_data} rs
        WHERE rs.entity_id = node_field_data.nid
          AND rs.entity_type_id = :entity_type
          AND rs.status = 1
          AND rs.capacity > (
            SELECT COUNT(r.registration_id)
            FROM {registration} r
            WHERE r.entity_id = node_field_data.nid
              AND r.entity_type_id = :entity_type
              AND r.state IN (:active_statuses[])
          )
      )',
      [
        ':entity_type' => 'node',
        ':active_statuses[]' => ['complete'],  // Fix here
      ]
    );
  } 

  public function adminSummary() {
    return $this->t('> 0 spaces remaining (hardcoded)');
  }

  public function canExpose() {
    return FALSE;  // Hardcoded → no exposure needed
  }
}
