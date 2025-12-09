<?php

namespace Drupal\my_module\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A menu link that displays the current user's email as the title.
 */
class UserEmailMenuLink extends MenuLinkDefault {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new UserEmailMenuLink.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, StaticMenuLinkOverridesInterface $static_override, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $static_override);
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu_link.static.overrides'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    if ($this->currentUser->isAuthenticated()) {
      // Dynamically set the title to the current user's email.
      return $this->currentUser->getEmail();
    }
    else {
      return $this->t('Log in');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteName() {
    if ($this->currentUser->isAuthenticated()) {
      return 'entity.user.canonical';
    }
    else {
      return 'user.login';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters() {
    return $this->currentUser->isAuthenticated() ? ['user' => $this->currentUser->id()] : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['user.roles:authenticated'];
  }

}
