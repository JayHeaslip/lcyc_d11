<?php

namespace Drupal\book\Plugin\views\argument_default;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeStorageInterface;
use Drupal\node\Plugin\views\argument_default\Node;
use Drupal\views\Attribute\ViewsArgumentDefault;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default argument plugin to get the current node's top level book.
 */
#[ViewsArgumentDefault(
  id: 'top_level_book',
  title: new TranslatableMarkup('Top Level Book from current node"'),
)]
class TopLevelBook extends Node {

  /**
   * Constructs a Drupal\book\Plugin\views\argument_default\TopLevelBook object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\node\NodeStorageInterface $nodeStorage
   *   The node storage controller.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, RouteMatchInterface $route_match, protected NodeStorageInterface $nodeStorage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $route_match);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager')->getStorage('node')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getArgument() {
    // Use the argument_default_node plugin to get the nid argument.
    $nid = parent::getArgument();
    if (!empty($nid)) {
      $node = $this->nodeStorage->load($nid);
      if (isset($node->book['bid'])) {
        return $node->book['bid'];
      }
    }
  }

}
