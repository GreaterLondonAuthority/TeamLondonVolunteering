<?php

namespace Drupal\gla_provider;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupContent;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\GroupMembershipLoader;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * todo: add interface
 */
class ProviderProcessor {

  /**
   * @var GroupMembershipLoader
   */
  protected $groupMembershipLoader;

  /**
   * @var Connection
   */
  protected $database;

  /**
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Creates a new ProviderProcessor instance.
   */
  public function __construct(GroupMembershipLoader $group_membership_loader, Connection $database, EntityTypeManagerInterface $entity_type_manager) {
    $this->groupMembershipLoader = $group_membership_loader;
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Add the specified user to the current user's group.
   *
   * @param $user \Drupal\user\Entity\User
   */
  public function addUserToCurrentGroup($user) {
    // Assign the provider role.
    $user->addRole('provider');
    $user->save();
    // Get the current user's group.
    $users_groups = $this->groupMembershipLoader->loadByUser();
    // todo: Would a user ever be a member of multiple groups?
    if (!empty($users_groups)) {
      /** @var \Drupal\group\Entity\Group $group */
      $group = reset($users_groups)->getGroup();
      $group->addMember($user);
      $group->save();
    }
  }

  /**
   * Provider profile-specific processing during node save.
   *
   * Note: This is run from hook_node_presave() so the entity might not yet be
   * complete.
   *
   * @param \Drupal\node\Entity\Node $node
   */
  public function processProfileNode(Node $node) {
    // Check the moderation state of this node.
    $state = $node->get('moderation_state')->getString();
    if (!empty($node->id()) && $state == 'published' && $this->isFirstTimePublished($node)) {
      // todo: Form validation for uniqueness? Or gla admin's responsibility to cross-reference?
      // Create group and set the profile node.
      $new_provider_group = Group::create(['type' => 'provider']);
      $new_provider_group->set('label', $node->getTitle());
      $new_provider_group->set('field_provider_profile', $node->id());
      $new_provider_group->save();

      // Add the author of the node to the group.
      $author = $node->getOwner();
      $new_provider_group->addMember($author);
      $new_provider_group->save();

      // Set the group ID on the node.
      $node->set('field_provider_group', $new_provider_group->id());
      $node->setNewRevision(FALSE);

      // Add the node to the group.
      $new_provider_group->addContent($node, 'group_node:provider_profile');
    }
  }

  /**
   * Check if this is the first time this node has been published.
   *
   * @param \Drupal\node\Entity\Node $node
   *
   * @return bool
   */
  public function isFirstTimePublished(Node $node) {
    // Check current node states.
    $query = $this->database->select('content_moderation_state_field_data', 'cmd');
    $query->fields('cmd', ['revision_id']);
    $query->condition('content_entity_id', $node->id());
    $query->condition('moderation_state', 'published');
    $result_d = $query->execute()->fetchAll();

    // Check previous states.
    $query = $this->database->select('content_moderation_state_field_revision', 'cmr');
    $query->fields('cmr', ['revision_id']);
    $query->condition('content_entity_id', $node->id());
    $query->condition('moderation_state', 'published');
    $result_r = $query->execute()->fetchAll();

    return empty($result_d) && empty($result_r);
  }

  /**
   * Create a stub for the provider profile node.
   *
   * @param FormStateInterface $form_state
   *
   * @return int
   */
  public function createStubProfile(FormStateInterface $form_state) {
    // Get the relevant values from the form.
    $values = $form_state->getValues();
    $uid = $values['uid'];

    $stub_profile = \Drupal\node\Entity\Node::create(['type' => 'provider_profile']);
    $stub_profile->set('title', ' ');
    $stub_profile->set('uid', $uid);
    $stub_profile->save();

    return $stub_profile->id();
  }

  /**
   * Create a stub for the opportunity node.
   *
   * @param \Drupal\user\Entity\User $user
   *
   * @return int
   */
  public function createStubOpportunity($user) {
    $uid = $user->id();
    $stub_opp = \Drupal\node\Entity\Node::create(['type' => 'opportunity']);
    $stub_opp->set('title', ' ');
    $stub_opp->set('uid', $uid);
    $stub_opp->save();

    // Add the node to the user's group.
    $group = $this->getGroup($user);
    $group->addContent($stub_opp, 'group_node:opportunity');

    return $stub_opp->id();
  }

  /**
   * Get the nid of the user's provider profile.
   *
   * @param $user \Drupal\user\Entity\User
   *
   * @return int
   */
  public function getUserProviderProfile($user, $return_entity = FALSE) {
    $group = $this->getGroup($user);
    if ($group) {
      if ($return_entity) {
        $provider_profile = $group->get('field_provider_profile')->entity;
      }
      else {
        $provider_profile = $group->get('field_provider_profile')->target_id;
      }

      return $provider_profile;
    }
    else {
      // If they don't yet have a group, get their profile from author.
      $node = $this->entityTypeManager->getStorage('node')->loadByProperties([
        'uid' => $user->id(),
        'type' => 'provider_profile',
      ]);

      if ($node && !$return_entity) {
        return key($node);
      }
      elseif ($node) {
        return reset($node);
      }
    }

    return FALSE;
  }

  /**
   * Get the user's group.
   *
   * @param $user \Drupal\Core\Session\AccountInterface
   *
   * @return GroupInterface | bool
   */
  public function getGroup(AccountInterface $user) {
    // Get the current user's group.
    $users_groups = $this->groupMembershipLoader->loadByUser($user);
    if (!empty($users_groups)) {
      /** @var \Drupal\group\Entity\Group $group */
      $group = reset($users_groups)->getGroup();
      return $group;
    }

    return FALSE;
  }

  /**
   * Get the latest revision log to show.
   */
  public function getLatestFeedback(Node $node) {

    // We need the latest revision when it changed from 'ready for review' to
    // 'draft' as new revisions are created each time the user goes between steps
    // in the form.

    // The ready_for_review version is in content_moderation_state_field_revision
    // if there is now feedback (i.e. is now a draft version).
    $query = $this->database->select('content_moderation_state_field_revision', 'cmr');
    $query->fields('cmr', ['content_entity_revision_id']);
    $query->condition('content_entity_id', $node->id());
    $query->condition('moderation_state', 'ready_for_review');
    $query->orderBy('content_entity_revision_id', 'DESC');
    $query->range(0, 1);
    $result_ready_for_review = $query->execute()->fetchCol();
    if (!empty($result_ready_for_review)) {
      $ready_for_review_vid = $result_ready_for_review[0];

      // Get the next draft after this ready_for_review version as this will have
      // the feedback.
      $query = $this->database->select('content_moderation_state_field_revision', 'cmr');
      $query->fields('cmr', ['content_entity_revision_id']);
      $query->condition('content_entity_id', $node->id());
      $query->condition('moderation_state', 'draft');
      $query->condition('content_entity_revision_id', $ready_for_review_vid, '>');
      $query->orderBy('content_entity_revision_id', 'ASC');
      $query->range(0, 1);
      $result_draft = $query->execute()->fetchCol();
      if (!empty($result_draft)) {
        $feedback_vid = $result_draft[0];

        // Load the node at this revision.
        $node_rid = $this->entityTypeManager->getStorage('node')->loadRevision($feedback_vid);

        // Check node rid is not null.
        if (!empty($node_rid)) {
          // Get log value.
          $log = nl2br($node_rid->get('revision_log')->value);
          $values = [
            'log' => $log,
            'rid' => $node_rid,
          ];
          return $values;
        }
      }
    }

    return FALSE;
  }

  /**
   * Check to see if the latest revision has feedback or not.
   *
   * @param Node $node
   * @return bool
   */
  public function latestRevisionHasFeedback(Node $node) {
    $query = $this->database->select('node_revision', 'nr');
    $query->fields('nr');
    $query->condition('nr.vid', $node->get('vid')->value);
    $query->isNotNull('nr.revision_log');
    $result = $query->execute()->fetchCol();
    if (!empty($result)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Check to see if the node of the revision you are using is published.
   *
   * @param Node $node
   * @return bool
   */
  public function nodeIsPublished(Node $node) {
    $query = $this->database->select('node_field_data', 'nf');
    $query->fields('nf');
    $query->condition('nf.nid', $node->id());
    $query->condition('nf.status', 1);
    $result = $query->execute()->fetchCol();
    if (!empty($result)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Check to see if the node has any application submissions attached to it.
   *
   * @param Node $node
   * @return bool
   */
  public function roleHasSubmissions(Node $node, $responded = FALSE) {
    $result = $this->checkSubmission($node, $responded);
    if (!empty($result)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Helper function to check if node has submissions (responded or not
   * responded, as specified).
   *
   * @param Node $node
   * @param bool $responded
   * @return mixed
   */
  private function checkSubmission(Node $node, $responded = FALSE) {
    $query = $this->database->select('application_submission', 'aps');
    $query->fields('aps');
    $query->condition('aps.node_id', $node->id());
    $query->condition('submitted', 1);
    if ($responded) {
      $query->condition('responded', 1);
    }
    else {
      $query->condition('responded', 1, '!=');
    }

    if (isset($node->currentApplicationBeingSaved, $node->currentApplicationBeingSavedResponded)) {
      // Ignore the submission being saved and we'll account for it later.
      $application_to_ignore = $node->currentApplicationBeingSaved;
      $query->condition('id', $application_to_ignore, '!=');
    }

    $results =  $query->execute()->fetchCol();

    // Add the current one to the results.
    if (isset($node->currentApplicationBeingSaved, $node->currentApplicationBeingSavedResponded)) {
      if ($node->currentApplicationBeingSavedResponded == $responded) {
        $results[] = $node->currentApplicationBeingSaved;
      }
    }

    return $results;
  }

  /**
   * Check whether or not role is expiring.
   * Puts a role as expiring if it has less then 2 weeks left on it.
   *
   * @param Node $node
   * @return bool
   */
  public function roleIsExpiring(Node $node) {
    if ($node->hasField('field_end_of_ad_specific') && !$node->get('field_end_of_ad_specific')->isEmpty()) {
      $expiry_date = $node->get('field_end_of_ad_specific')->value;
      if ($expiry_date < date('Y-m-d', strtotime('+14 days'))) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Check whether or not role has expired.
   *
   * @param Node $node
   * @return bool
   */
  public function roleExpired(Node $node) {
    if ($node->hasField('field_end_of_ad_specific') && !$node->get('field_end_of_ad_specific')->isEmpty()) {
      $expiry_date = $node->get('field_end_of_ad_specific')->value;
      if ($expiry_date <= date('Y-m-d')) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Check whether or not role has been approved for publishing.
   *
   * @param Node $node
   * @return bool
   */
  public function roleApproved(Node $node) {
    // Check if the default revision is approved.
    $default_rev = Node::load($node->id());
    $default_rev_mod = $default_rev->get('moderation_state')->value;
    if ($default_rev_mod == 'published' || $default_rev_mod == 'approved') {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Get the group related to the given entity.
   *
   * @param $entity \Drupal\Core\Entity\ContentEntityInterface
   *
   * @return GroupInterface | bool
   */
  public function getGroupFromEntity(ContentEntityInterface $entity) {

    // If the entity is a group just return this.
    if ($entity instanceof GroupInterface) {
      return $entity;
    }

    $group_content_result = GroupContent::loadByEntity($entity);
    if (!empty($group_content_result)) {
      /** @var GroupContent $group_content */
      $group_content = reset($group_content_result);
      $group = $group_content->getGroup();
      return $group;
    }

    return FALSE;
  }

  /**
   * Get the provider profile related to the given entity.
   *
   * @param $entity \Drupal\Core\Entity\ContentEntityInterface
   *
   * @return Node | bool
   */
  public function getProviderProfileFromEntity(ContentEntityInterface $entity) {

    $group = $this->getGroupFromEntity($entity);
    if ($group) {
      $provider_profile_result = $this->entityTypeManager->getStorage('node')->loadByProperties([
        'field_provider_group' => $group->id(),
        'type' => 'provider_profile',
      ]);

      if (!empty($provider_profile_result)) {
        return reset($provider_profile_result);
      }
    }

    return FALSE;
  }

  /**
   * Get users in the given group.
   *
   * @param $group Group
   *
   * @return \Drupal\user\Entity\User[]
   */
  public function getUsersInGroup(GroupInterface $group) {
    $memberships = $group->getMembers();
    if (empty($memberships)) {
      return [];
    }

    $members = [];
    foreach ($memberships as $membership) {
      $members[] = $membership->getUser();
    }

    return $members;
  }

  /**
   * Load latest revision of a node.
   *
   * @param Node $node
   * @return Node
   */
  public function loadLatestRevision(Node $node) {
    $revision_ids = $this->entityTypeManager->getStorage('node')->revisionIds($node);
    $last_revision_id = end($revision_ids);
    return $this->entityTypeManager->getStorage('node')->loadRevision($last_revision_id);
  }

  /**
   * Returns the date when this node was first published.
   *
   * @param \Drupal\node\Entity\Node $node
   */
  public function datePublished(Node $node) {
    // Check current node states.
    $query = $this->database->select('node_field_data', 'nd');
    $query->fields('nd', ['changed']);
    $query->condition('nid', $node->id());
    $query->condition('status', 1);
    $query->orderBy('changed', 'ASC');
    $query->range(0, 1);
    $result_d = $query->execute()->fetchCol();

    // Check previous states.
    $query = $this->database->select('node_field_revision', 'nr');
    $query->fields('nr', ['changed']);
    $query->condition('nid', $node->id());
    $query->condition('status', 1);
    $query->orderBy('changed', 'ASC');
    $query->range(0, 1);
    $result_r = $query->execute()->fetchCol();

    if (empty($result_d) && empty($result_r)) {
      return t('Unpublished');
    }
    elseif (empty($result_d)) {
      $timestamp = $result_r[0];
    }
    elseif (empty($result_r)) {
      $timestamp = $result_d[0];
    }
    elseif ($result_r[0] > $result_d[0]) {
      $timestamp = $result_d[0];
    }
    else {
      $timestamp = $result_r[0];
    }

    return DrupalDateTime::createFromTimestamp($timestamp)->format('d/m/Y');
  }

  /**
   * Get the provider profile and opportunity content of a group.
   *
   * @param $group Group
   *
   * @return Node[]
   */
  public function getContentInGroup(GroupInterface $group) {
    $provider_profile_content = $group->getContentEntities('group_node:provider_profile');
    $opportunity_content = $group->getContentEntities('group_node:opportunity');
    return array_merge($provider_profile_content, $opportunity_content);
  }

  /**
   * Get the provider profile and opportunity group content of a group.
   *
   * @param $group Group
   *
   * @return GroupContent[]
   */
  public function getGroupContentInGroup(GroupInterface $group) {
    $provider_profile_content = $group->getContent('group_node:provider_profile');
    $opportunity_content = $group->getContent('group_node:opportunity');
    return array_merge($provider_profile_content, $opportunity_content);
  }

  /**
   * Get the applications of a group.
   *
   * @param $group Group
   *
   * @return \Drupal\gla_opportunity\Entity\ApplicationSubmission[]
   */
  public function getApplicationsInGroup(GroupInterface $group) {
    return $group->getContentEntities('group_entity_application_submission:application_submission');
  }

  /**
   * Get the applications group content of a group.
   *
   * @param $group Group
   *
   * @return GroupContent[]
   */
  public function getApplicationsGroupContentInGroup(GroupInterface $group) {
    return $group->getContent('group_entity_application_submission:application_submission');
  }

  /**
   * Suspend the entire provider organisation.
   *
   * - Set provider group as suspended which will restrict users' access to some
   *   areas for the dashboard.
   * - Unpublish provider profile.
   * - Unpublish all roles.
   * - Leave all applications as they are.
   *
   * @param $group Group
   */
  public function suspendProviderOrganisation(GroupInterface $group) {
    // Unpublish all content in the group.
    $restore_info = [
      'published' => [],
      'approved' => [],
      'ready_for_review' => [],
    ];
    $content = $this->getContentInGroup($group);
    foreach ($content as $node) {
      // Save the current published revision ID to the group so that if they are
      // re-activated then we can re-publish that revision.
      // $node here is the default version, so if the node is published it'll be
      // the published revision.
      $moderation_state = $node->get('moderation_state')->value;
      if (isset($restore_info[$moderation_state])) {
        $vid = $node->getRevisionId();
        $restore_info[$moderation_state][$node->id()] = $vid;

        // Note: leaving this code in case it is wanted, but we'd have to think
        // carefully about any recent changes the provider might have made.
        // Get the pending revision too (if there is one) and collect this info.
        /** @var \Drupal\content_moderation\ModerationInformation $moderation_info */
        //      $moderation_info = \Drupal::getContainer()->get('content_moderation.moderation_information');
        //      if ($moderation_info->hasPendingRevision($node)) {
        //        $latest_revision = $moderation_info->getLatestRevision('node', $node->id());
        //        $latest_revision_moderation_state = $latest_revision->get('moderation_state')->value;
        //        $restore_info[$latest_revision_moderation_state][$node->id()] = $latest_revision->getRevisionId();
        //      }
      }

      // Now set the node to unpublished. Also unset any scheduled publishing.
      $node->set('publish_on', NULL);
      $node->set('unpublish_on', NULL);
      $node->setNewRevision(TRUE);
      $node->set('moderation_state', 'unpublished');
      $node->setPublished(FALSE);
      $node->glaSkipEmails = TRUE;
      $node->save();
    }

    // Set the group as suspended.
    $group->set('field_restore_node_vids', serialize($restore_info));
    $group->set('field_suspended', TRUE);
    $group->save();

    return TRUE;
  }

  /**
   * Re-activate the provider organisation.
   *
   * - Set provider group as not suspended which will restore users' access.
   * - Republish the public verison of provider profile.
   * - Republish the public verison of all roles.
   *
   * @param $group Group
   */
  public function reactivateProviderOrganisation(GroupInterface $group) {
    // Set the group as NOT suspended.
    $group->set('field_suspended', FALSE);
    $group->save();

    // Get restore info.
    $restore_info = $group->get('field_restore_node_vids')->value;
    $restore_info = unserialize($restore_info);
    if (isset($restore_info['published'])) {
      $nodes_to_publish = $restore_info['published'];
    }
    else {
      // We're in the old data format.
      $nodes_to_publish = $restore_info;
    }

    // Go through each nid => vid pair we have and publish that version.
    foreach ($nodes_to_publish as $nid => $vid) {
      /** @var \Drupal\node\Entity\Node $revision */
      $revision = $this->entityTypeManager->getStorage('node')->loadRevision($vid);
      $revision->setNewRevision();
      $revision->isDefaultRevision(TRUE);
      $revision->set('moderation_state', 'published');
      $revision->glaSkipEmails = TRUE;

      $revision->save();
    }

    // Now go through ready for review and approved items.
    if (isset($restore_info['approved'])) {
      foreach ($restore_info['approved'] as $nid => $vid) {
        /** @var \Drupal\node\Entity\Node $revision */
        $revision = $this->entityTypeManager->getStorage('node')->loadRevision($vid);
        $revision->setNewRevision();
        $revision->set('moderation_state', 'approved');
        $revision->glaSkipEmails = TRUE;

        $revision->save();
      }
    }
    if (isset($restore_info['ready_for_review'])) {
      foreach ($restore_info['ready_for_review'] as $nid => $vid) {
        /** @var \Drupal\node\Entity\Node $revision */
        $revision = $this->entityTypeManager->getStorage('node')->loadRevision($vid);
        $revision->setNewRevision();
        $revision->set('moderation_state', 'ready_for_review');
        $revision->glaSkipEmails = TRUE;

        $revision->save();
      }
    }

    return TRUE;
  }

  /**
   * Delete the entire provider organisation and all associated
   * content/entities.
   *
   * - Delete all applications.
   * - Delete all content.
   * - Delete all members.
   * - Delete all group.
   *
   * @param $group Group
   */
  public function deleteProviderOrganisation(GroupInterface $group) {
    // First delete all applications in the group.
    // This sends emails to the volunteers.
    $applications = $this->getApplicationsGroupContentInGroup($group);
    foreach ($applications as $application_group_content) {
      /** @var \Drupal\gla_opportunity\Entity\ApplicationSubmission $application */
      $application = $application_group_content->getEntity();
      $application_group_content->delete();
      $application->delete();
    }

    // Then remove and delete all content in the group.
    $content = $this->getGroupContentInGroup($group);
    foreach ($content as $group_content) {
      /** @var Node $node */
      $node = $group_content->getEntity();
      $group_content->delete();
      $node->delete();
    }

    // Then remove and delete all members of the group.
    $members = $this->getUsersInGroup($group);
    foreach ($members as $member) {
      $group->removeMember($member);
      $member->delete();
    }

    // Then delete the group itself.
    $group->delete();
  }

  /**
   * Check if this user can delete their account.
   *
   * @param $user User
   *
   * @return bool
   */
  public function userCanDelete(User $user) {
    $roles = $user->getRoles();
    if (in_array('volunteer', $roles)) {
      return TRUE;
    }

    $group = $this->getGroup($user);
    if ($group) {
      $members = $this->getUsersInGroup($group);
      return count($members) > 1;
    }

    return FALSE;
  }

  /**
   * Get the next applicable member in the group.
   *
   * @param $user User
   *
   * @return User
   */
  public function getNextMemberOfGroup(User $user) {
    $group = $this->getGroup($user);
    if ($group) {
      $members = $this->getUsersInGroup($group);
      foreach ($members as $member) {
        if ($member->isActive() && $member->id() != $user->id()) {
          return $member;
        }
      }
    }

    return FALSE;
  }

  /**
   * Delete the user and transfer/delete applicable content.
   *
   * - Delete all applications.
   * - Delete all content.
   * - Delete all members.
   * - Delete all group.
   *
   * @param $group Group
   */
  public function deleteUserBatch(User $user) {
    // Double check this user can be deleted.
    if (!$this->userCanDelete($user)) {
      return FALSE;
    }

    $operations = [];

    $roles = $user->getRoles();
    if (in_array('provider', $roles)) {
      // Transfer any content to another member of the group.
      $new_owner = $this->getNextMemberOfGroup($user);
      if (!$new_owner) {
        return FALSE;
      }

      // Reassign content to the new owner. Do this in batches of 5 to make sure
      // it doesn't time out.
      // Also pass in the user and group to process in the 'finished' callback.
      $group = $this->getGroup($user);
      $nodes = \Drupal::entityQuery('node')
        ->condition('uid', $user->id())
        ->execute();
      $nodes_chunked = array_chunk($nodes,5,TRUE);
      $operations[] = [
        '\Drupal\gla_provider\ProviderProcessor::reassignNodes',
        [
          $nodes_chunked,
          $new_owner,
          $group,
          $user,
        ],
      ];
    }
    elseif (in_array('volunteer', $roles)) {
      // Get a list of this user's submissions.
      // We must first remove them from their group, then delete the entity. Do
      // this in batches of 5 to make sure it doesn't time out.
      // Also pass in the user and group to process in the 'finished' callback.
      /** @var \Drupal\gla_opportunity\Entity\ApplicationSubmission[] $applicant_submissions */
      $applicant_submissions = $this->entityTypeManager->getStorage('application_submission')->loadByProperties([
        'user_id' => $user->id(),
      ]);

      $applicant_submissions_chunked = array_chunk($applicant_submissions,5,TRUE);
      $operations[] = [
        '\Drupal\gla_provider\ProviderProcessor::removeApplications',
        [
          $applicant_submissions_chunked,
          $user,
        ],
      ];
    }

    $batch = [
      'operations' => $operations,
      'finished' => '\Drupal\gla_provider\ProviderProcessor::deleteUserBatchFinished',
      'title' => t('Processing user delete.'),
      'init_message' => t('User deletion is starting.'),
      'progress_message' => t('Processed @current out of @total.'),
      'error_message' => t('User delete process has encountered an error.'),
    ];

    return $batch;
  }

  /**
   * Batch finished callback.
   */
  static public function deleteUserBatchFinished($success, $results, $operations) {
    $messenger = \Drupal::messenger();
    if ($success && isset($results['user']) && $results['user']) {
      // The batch processes have finished so now delete the user itself.
      // First remove from group if applicable.
      /** @var User $user */
      $user = $results['user'];
      if (isset($results['group']) && $results['group']) {
        /** @var Group $group */
        $group = $results['group'];
        $group->removeMember($user);
      }

      // Then delete the user itself.
      $user->delete();

      $messenger->addMessage(t('Your account has been deleted.'), $messenger::TYPE_WARNING);
      $redirect_url = Url::fromRoute('<front>')->toString();
      return new RedirectResponse($redirect_url);
    }
    else {
      $messenger->addMessage(t('There was a problem deleting your account.'), $messenger::TYPE_WARNING);
    }
  }

  /**
   * Reassign nodes.
   */
  static public function reassignNodes($nodes_chunked, $new_owner, $group, $current_user, &$context) {
    if (empty($context['sandbox'])) {
      $context['sandbox'] = [];
      $context['sandbox']['current_chunk'] = 0;
      $context['results']['group'] = $group;
      $context['results']['user'] = $current_user;
    }

    $total_chunks = count($nodes_chunked);
    if (!$total_chunks) {
      $context['finished'] = 1;
      return;
    }

    $current_chunk = $context['sandbox']['current_chunk'];
    if (isset($nodes_chunked[$current_chunk])) {
      $node_ids = $nodes_chunked[$current_chunk];
      $new_uid = $new_owner->id();

      // We do this with direct db queries so the content isn't 'saved' and
      // moderation states etc changed.
      db_update('node_field_data')
        ->fields(['uid' => $new_uid])
        ->condition('uid', $current_user->id())
        ->condition('nid', $node_ids, 'IN')
        ->execute();

      // Act on old revisions too.
      db_update('node_field_revision')
        ->fields(['uid' => $new_uid])
        ->condition('uid', $current_user->id())
        ->condition('nid', $node_ids, 'IN')
        ->execute();

      db_update('node_revision')
        ->fields(['revision_uid' => $new_uid])
        ->condition('revision_uid', $current_user->id())
        ->condition('nid', $node_ids, 'IN')
        ->execute();

      // Clear the cache for these nodes.
      $tags = [];
      foreach ($node_ids as $node_id) {
        $tags[] = 'node:' . $node_id;
      }

      \Drupal::entityTypeManager()->getStorage('node')->resetCache($node_ids);
      Cache::invalidateTags($tags);
    }

    $context['sandbox']['current_chunk']++;
    $context['finished'] = $context['sandbox']['current_chunk'] / $total_chunks;
    $context['message'] = t('Running batch @id of @total',
      ['@id' => $current_chunk, '@total' => $total_chunks]
    );
  }

  /**
   * Delete applications.
   */
  static public function removeApplications($applicant_submissions_chunked, $current_user, &$context) {
    if (empty($context['sandbox'])) {
      $context['sandbox'] = [];
      $context['sandbox']['current_chunk'] = 0;
      $context['results']['user'] = $current_user;
    }

    $total_chunks = count($applicant_submissions_chunked);
    $current_chunk = $context['sandbox']['current_chunk'];
    $applicant_submissions = $applicant_submissions_chunked[$current_chunk];
    foreach ($applicant_submissions as $applicant_submission) {
      // Get the group content entity.
      $group_content_result = GroupContent::loadByEntity($applicant_submission);
      if (!empty($group_content_result)) {
        /** @var GroupContent $group_content */
        $group_content = reset($group_content_result);
        $group_content->delete();
      }

      $applicant_submission->delete();
    }

    $context['sandbox']['current_chunk']++;
    $context['finished'] = $context['sandbox']['current_chunk'] / $total_chunks;
    $context['message'] = t('Running batch @id of @total',
      ['@id' => $current_chunk, '@total' => $total_chunks]
    );
  }

}
