<?php

namespace Drupal\custom_theme_conditions\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Current User ID' condition.
 *
 * @Condition(
 *   id = "current_user_id",
 *   label = @Translation("Current user ID"),
 *   context_definitions = {
 *     "user" = @ContextDefinition("entity:user", label = @Translation("User"), required = TRUE)
 *   }
 * )
 */
class CurrentUserId extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
      $form['user_id'] = [
           '#type' => 'textfield',
     	   '#title' => $this->t('User ID'),
	   '#description' => $this->t('Enter the user ID (UID) to match against the current user.'),
	   '#default_value' => $this->configuration['user_id'] ?? '',
     	   '#required' => FALSE,
           '#maxlength' => 10,
	   '#attributes' => ['type' => 'number', 'min' => 0],
	   '#element_validate' => [[$this, 'validateUserId']],
      ];
 
      return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['user_id'] = $form_state->getValue('user_id');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    $uid = $this->configuration['user_id'] ?? 'unknown';
    return $this->t('The current user ID is @uid', ['@uid' => $uid]);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    if (empty($this->configuration['user_id'])) {
      return FALSE;
    }

    $user = $this->getContextValue('user');
    if (!$user || !$user->id()) {
      return FALSE;
    }

    $result = ($user->id() == $this->configuration['user_id']);

    return $this->isNegated() ? !$result : $result;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['user_id' => ''] + parent::defaultConfiguration();
  }

 public function validateUserId(array &$element, FormStateInterface $form_state) {
    $uid = $element['#value'];

     if (empty($uid) || $uid === '') {
      return; 
    }

    // Ensure the value is a valid integer string representation.
    if (!is_numeric($uid) || $uid < 0 || strpos($uid, '.') !== FALSE) {
      $form_state->setError($element, $this->t('The User ID must be a non-negative integer.'));
    }
    // Also, cast the value to an integer before storage for clean data.
    $form_state->setValueForElement($element, (int) $uid);
  }

}
