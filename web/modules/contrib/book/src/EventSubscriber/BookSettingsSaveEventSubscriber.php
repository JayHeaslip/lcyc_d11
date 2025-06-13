<?php

namespace Drupal\book\EventSubscriber;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Clears cache tag when Book settings is saved.
 */
class BookSettingsSaveEventSubscriber implements EventSubscriberInterface {

  /**
   * Acts on changes to book settings to cache tag.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    $config = $event->getConfig();
    if ($config->getName() === 'book.settings') {
      // Now that the block is cached it needs to be invalidated.
      Cache::invalidateTags(['book_settings']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ConfigEvents::SAVE => 'onConfigSave'];
  }

}
