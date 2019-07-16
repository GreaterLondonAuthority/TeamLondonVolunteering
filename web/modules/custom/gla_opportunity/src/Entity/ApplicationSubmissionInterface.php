<?php

namespace Drupal\gla_opportunity\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Application submission entities.
 *
 * @ingroup gla_opportunity
 */
interface ApplicationSubmissionInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Application submission name.
   *
   * @return string
   *   Name of the Application submission.
   */
  public function getName();

  /**
   * Sets the Application submission name.
   *
   * @param string $name
   *   The Application submission name.
   *
   * @return \Drupal\gla_opportunity\Entity\ApplicationSubmissionInterface
   *   The called Application submission entity.
   */
  public function setName($name);

  /**
   * Gets the Application submission creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Application submission.
   */
  public function getCreatedTime();

  /**
   * Sets the Application submission creation timestamp.
   *
   * @param int $timestamp
   *   The Application submission creation timestamp.
   *
   * @return \Drupal\gla_opportunity\Entity\ApplicationSubmissionInterface
   *   The called Application submission entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Application submission published status indicator.
   *
   * Unpublished Application submission are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Application submission is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Application submission.
   *
   * @param bool $published
   *   TRUE to set this Application submission to published, FALSE to set it to unpublished.
   *
   * @return \Drupal\gla_opportunity\Entity\ApplicationSubmissionInterface
   *   The called Application submission entity.
   */
  public function setPublished($published);

}
