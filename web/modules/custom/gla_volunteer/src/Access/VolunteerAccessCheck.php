<?php

namespace Drupal\gla_volunteer\Access;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
* Checks access that volunteers are the users of the submissions.
*/
class VolunteerAccessCheck implements AccessInterface{

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * VolunteerAccessCheck constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
  * A custom access check.
  *
  * @param \Drupal\Core\Session\AccountInterface $account
  *   Run access checks for this account.
  *
  * @return \Drupal\Core\Access\AccessResultInterface
  *   The access result.
  */
  public function access(AccountInterface $account, $entity = NULL) {
    // Load the target entity and compare user ids.
    $target_entity = $this->entityTypeManager->getStorage('application_submission')->load($entity);
    if ($target_entity->get('user_id')->target_id == $account->id()) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}