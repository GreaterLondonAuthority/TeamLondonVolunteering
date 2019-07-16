<?php

namespace Drupal\gla_opportunity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Application form element entities.
 *
 * @ingroup gla_opportunity
 */
class ApplicationFormElementListBuilder extends EntityListBuilder {


  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Application form element ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\gla_opportunity\Entity\ApplicationFormElement */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.application_form_element.edit_form',
      ['application_form_element' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
