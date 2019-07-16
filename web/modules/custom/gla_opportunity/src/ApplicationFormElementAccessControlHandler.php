<?php

namespace Drupal\gla_opportunity;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Application form element entity.
 *
 * @see \Drupal\gla_opportunity\Entity\ApplicationFormElement.
 */
class ApplicationFormElementAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\gla_opportunity\Entity\ApplicationFormElementInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished application form element entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published application form element entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit application form element entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete application form element entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add application form element entities');
  }

}
