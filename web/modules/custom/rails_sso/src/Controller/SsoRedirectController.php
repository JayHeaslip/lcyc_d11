<?php

namespace Drupal\rails_sso\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Firebase\JWT\JWT;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Handles the secure internal redirect to the external Rails application.
 */
final class SsoRedirectController extends ControllerBase {

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The logger channel for rails_sso.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs an SsoRedirectController object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel for rails_sso.
   */
  public function __construct(RequestStack $request_stack, LoggerInterface $logger) {
    $this->requestStack = $request_stack;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('request_stack'),
      $container->get('logger.factory')->get('rails_sso')
    );
  }

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

    // Fallback if secret key is missing.
    if (!$secret_key) {
      $this->logger->error('SSO Secret key is missing in settings.php');
      $this->messenger()->addError($this->t('SSO configuration error. Please contact an administrator.'));
      $url = Url::fromRoute('<front>')->toString();
      return new RedirectResponse($url);
    }

    $rails_base_url = Settings::get('rails_sso_url');

    // Fallback configuration check to prevent broken links.
    if (!$rails_base_url) {
      $this->logger->error('SSO Destination URL (rails_sso_url) is missing in settings.php');
      $this->messenger()->addError($this->t('SSO destination setup error. Please contact an administrator.'));
      return new TrustedRedirectResponse('<front>');
    }

    // 1. Get the destination path passed from the menu link (default to root if empty).
      $request = $this->requestStack->getCurrentRequest();
      $destination = $request ? (string) $request->query->get('return_to', '/') : '/';
    // Only permit relative paths beginning with a single slash.
    if (
      !str_starts_with($destination, '/') ||
      str_starts_with($destination, '//')
    ) {
      $destination = '/';
    }

    // Generate token valid for 300 seconds (5 minutes).
    $issued_at = time();
    $expire = $issued_at + 300;
    $this->logger->info('SSO issued at: @iat', ['@iat' => $issued_at]);
    $payload = [
      'iat' => $issued_at,
      'exp' => $expire,
      'email' => $current_user->getEmail(),
      'name' => $current_user->getAccountName(),
      'return_to' => $destination,
    ];

    $jwt = JWT::encode($payload, $secret_key, 'HS256');

    // Construct target.
    $rails_url = $rails_base_url . '?token=' . urlencode($jwt);

    $this->logger->info('Redirecting user to Rails SSO URL: @url', [
      '@url' => $rails_url,
    ]);

    // Issue standard HTTP 302 redirect.
    return new TrustedRedirectResponse($rails_url);
  }

}
