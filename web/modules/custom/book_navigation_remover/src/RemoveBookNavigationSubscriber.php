<?php
namespace Drupal\book_navigation_remover\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Render\PageDisplayVariantSubscriber;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** * Class RemoveBookNavigationSubscriber. * * Removes the book navigation from the page render array. */

class RemoveBookNavigationSubscriber implements EventSubscriberInterface {

     /**   * {@inheritdoc}   */
     public static function getSubscribedEvents() {

      	     // Subscribe to the kernel view event with higher priority than page_display.
	     $events[KernelEvents::VIEW][] = ['onView', PageDisplayVariantSubscriber::PRIORITY + 10];
	     return $events;
     }

     /**   * Alters the page render array to remove book navigation.   *
           * @param \Symfony\Component\HttpKernel\Event\ViewEvent $event
	   *   The event object.   */
     public function onView(ViewEvent $event) {
        $result = $event->getControllerResult();
        // Only act on render arrays
	if (is_array($result)) {
	   // Remove book navigation if present
	   if (isset($result['book_navigation'])) {
	      unset($result['book_navigation']);
	      $event->setControllerResult($result);
	   }

          // Also check for book navigation in content
          if (isset($result['content']['book_navigation'])) {
	    unset($result['content']['book_navigation']);
	    $event->setControllerResult($result);
	  }
        }
    }
}
