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

    // Generate token valid for exactly 30 seconds (even tighter security)
    $issued_at = time();
    $expire = $issued_at + 30;

    $payload = [
      'iat'   => $issued_at,
      'exp'   => $expire,
      'email' => $current_user->getEmail(),
      'name'  => $current_user->getAccountName(),
    ];

    $jwt = JWT::encode($payload, $secret_key, 'HS256');
    
    // Construct target local Rails dev environment URL
    $rails_url = 'http://localhost:3001/sso/login?token=' . urlencode($jwt);

    // Issue standard HTTP 302 redirect
    return new TrustedRedirectResponse($rails_url);
  }

}