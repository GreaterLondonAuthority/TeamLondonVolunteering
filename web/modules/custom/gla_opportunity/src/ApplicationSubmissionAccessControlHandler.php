<?php

namespace Drupal\gla_opportunity;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Application submission entity.
 *
 * @see \Drupal\gla_opportunity\Entity\ApplicationSubmission.
 */
class ApplicationSubmissionAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\gla_opportunity\Entity\ApplicationSubmissionInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished application submission entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published application submission entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit application submission entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete application submission entities');

      case 'respond':
        // This user must be a member of the same group as the application submission.
        // Only providers are added to groups (not volunteers) so no need for other checks.
        // Also check that the provider group isn't suspended.
        /** @var \Drupal\gla_provider\ProviderProcessor $provider_processor */
        $provider_processor = \Drupal::service('gla_provider.processor');
        $submission_group = $provider_processor->getGroupFromEntity($entity);
        $user_group = $provider_processor->getGroup($account);
        $provider_suspended = $user_group->get('field_suspended')->value;
        if ($submission_group && $user_group && $submission_group->id() == $user_group->id() && !$provider_suspended) {
          // Same group (and not suspended) so they are allowed.
          return AccessResult::allowed();
        }
        elseif ($provider_suspended) {
          // Add a message so the user knows what's going on.
          $messenger = \Drupal::messenger();
          $messenger->addMessage(t('Your provider organisation has been suspended. You cannot respond to applications at this time.'), $messenger::TYPE_WARNING);
        }

        // Otherwise not allowed.
        return AccessResult::forbidden();
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add application submission entities');
  }

  /**
   * {@inheritdoc}
   */
  protected function checkFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, FieldItemListInterface $items = NULL) {
    // If the provider hasn't yet responded to the application, then they can't
    // see the email address.
    $roles = $account->getRoles();
    $field_name = $field_definition->getName();
    if ($field_name == 'field_email' && in_array('provider', $roles) && !in_array('site_administrator', $roles) && $items) {
      /** @var \Drupal\gla_opportunity\Entity\ApplicationSubmission $entity */
      $entity = $items->getEntity();
      if (!isset($entity->get('responded')->value) || !$entity->get('responded')->value) {
        // Provider hasn't responded - no email access.
        return AccessResult::forbidden();
      }
      elseif (!$entity->hasField('field_application_status') || $entity->get('field_application_status')->value != 'accepted') {
        // Provider has rejected - no email access.
        return AccessResult::forbidden();
      }
    }

    // Default.
    return parent::checkFieldAccess($operation, $field_definition, $account, $items);
  }

}
