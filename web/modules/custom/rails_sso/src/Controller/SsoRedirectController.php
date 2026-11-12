<?php

namespace Drupal\rails_sso\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Firebase\JWT\JWT;
use Drupal\Core\Url;

/**
 * Handles the secure internal redirect to the external Rails application.
 */
class SsoRedirectController extends ControllerBase {

  /**
   * Generates a fresh JWT and redirects the user to Rails.
   */
  public function redirectToRails() {
    $current_user = $this->currentUser();

    // Check if the current user has the 'member' role machine name.
    $roles = $current_user->getRoles();
    if (!in_array('member', $roles) && !in_array('administrator', $roles)) {
      $this->messenger()->addError($this->t('Access denied. Only club members can access the portal.'));
      return new TrustedRedirectResponse('<front>');
    }

    $secret_key = Settings::get('rails_sso_secret');

    // Fallback if secret key is missing
    if (!$secret_key) {
      \Drupal::logger('rails_sso')->error('SSO Secret key is missing in settings.php');
      $this->messenger()->addError($this->t('SSO configuration error. Please contact an administrator.'));
      $url = Url::fromRoute('<front>')->toString();
      return new RedirectResponse($url);
    }

    $rails_base_url = Settings::get('rails_sso_url');

    // Fallback configuration check to prevent broken links
    if (!$rails_base_url) {
      \Drupal::logger('rails_sso')->error('SSO Destination URL (rails_sso_url) is missing in settings.php');
        $this->messenger()->addError($this->t('SSO destination setup error. Please contact an administrator.'));
	  return new TrustedRedirectResponse('<front>');
    }
    
    // 1. Get the destination path passed from the menu link (default to root if empty)
    $request = \Drupal::request();
    $destination = $request->query->get('return_to', '/');

    // Only permit relative paths beginning with a single slash.
    if (
      !is_string($destination) ||
        !str_starts_with($destination, '/') ||
	  str_starts_with($destination, '//')
    ) {
      $destination = '/';
    }

    // Generate token valid for exactly 120 seconds
    $issued_at = time(); 
    $expire = $issued_at + 300;
    \Drupal::logger('rails_sso')->info('SSO issued at: ' . $issued_at);
    $payload = [
      'iat'   => $issued_at,
      'exp'   => $expire,
      'email' => $current_user->getEmail(),
      'name'  => $current_user->getAccountName(),
      'return_to' => $destination, // Passing dynamic destination
    ];

    $jwt = JWT::encode($payload, $secret_key, 'HS256');
    
    // Construct target
    $rails_url = $rails_base_url . '?token=' . urlencode($jwt);

    // --- ADD THIS LOGGING STATEMENT ---
       \Drupal::logger('rails_sso')->info('Redirecting user to Rails SSO URL: @url', [
       '@url' => $rails_url,
    ]);						       	     
    
    // Issue standard HTTP 302 redirect
    return new TrustedRedirectResponse($rails_url);
  }

}
