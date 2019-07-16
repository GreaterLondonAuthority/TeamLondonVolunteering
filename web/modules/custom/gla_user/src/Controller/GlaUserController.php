<?php

namespace Drupal\gla_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Class GlaUserController.
 */
class GlaUserController extends ControllerBase {

  /**
   * The _controller for gla_user.check_email.
   */
  public function emailConfirm($email = NULL) {

    if (!isset($email)) {
      throw new AccessDeniedHttpException();
    }

    return [
      '#theme' => 'gla_user__email_confirm',
      '#email' => $email,
    ];
  }

}
