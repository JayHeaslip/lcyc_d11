<?php

/**
 * @file
 * Post update functions for the book module.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\block\Entity\Block;

/**
 * Pre-populate the use_top_level_title setting of the book_navigation blocks.
 */
function book_post_update_prepopulate_use_top_level_title_setting(&$sandbox): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'block', function (Block $block) {
    if ($block->getPluginId() === 'book_navigation') {
      $block->getPlugin()->setConfigurationValue('use_top_level_title', FALSE);
      return TRUE;
    }
    return FALSE;
  });
}

/**
 * Update extra book field for entity view displays.
 */
function book_post_update_book_navigation_view_display(?array &$sandbox = NULL): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'entity_view_display', function (EntityViewDisplayInterface $entity_view_display): bool {
    $update = FALSE;
    $components = $entity_view_display->getComponents();
    if ($entity_view_display->getTargetEntityTypeId() === 'node') {
      if (isset($components['book_navigation'])) {
        if ($entity_view_display->getMode() !== 'full' || $entity_view_display->getMode() !== 'default') {
          $updated_component = $entity_view_display->getComponent('book_navigation');
          $updated_component['region'] = 'hidden';
          $entity_view_display->setComponent('book_navigation', $updated_component);
          $update = TRUE;
        }
      }
    }
    return $update;
  });
}
