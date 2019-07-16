<?php

namespace Drupal\gla_opportunity\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Application form element entities.
 *
 * @ingroup gla_opportunity
 */
interface ApplicationFormElementInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets the Application form element name.
   *
   * @return string
   *   Name of the Application form element.
   */
  public function getName();

  /**
   * Sets the Application form element name.
   *
   * @param string $name
   *   The Application form element name.
   *
   * @return \Drupal\gla_opportunity\Entity\ApplicationFormElementInterface
   *   The called Application form element entity.
   */
  public function setName($name);

  /**
   * Gets the Application form element creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Application form element.
   */
  public function getCreatedTime();

  /**
   * Sets the Application form element creation timestamp.
   *
   * @param int $timestamp
   *   The Application form element creation timestamp.
   *
   * @return \Drupal\gla_opportunity\Entity\ApplicationFormElementInterface
   *   The called Application form element entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Application form element published status indicator.
   *
   * Unpublished Application form element are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Application form element is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Application form element.
   *
   * @param bool $published
   *   TRUE to set this Application form element to published, FALSE to set it to unpublished.
   *
   * @return \Drupal\gla_opportunity\Entity\ApplicationFormElementInterface
   *   The called Application form element entity.
   */
  public function setPublished($published);

}
