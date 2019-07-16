<?php

namespace Drupal\gla_site\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\gla_provider\ProviderProcessor;
use Drupal\group\Entity\Group;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form base for admin provider management.
 */
abstract class ProviderManagementBaseForm extends FormBase {

  /**
   * @var ProviderProcessor
   */
  protected $providerProcessor;

  /**
   * @var string
   */
  protected $actionText;

  /**
   * @var array
   */
  protected $actions;

  /**
   * @var string
   */
  protected $backRoute = 'view.admin_provider_user_management.page_1';

  /**
   * @param ProviderProcessor $provider_processor
   */
  public function __construct(ProviderProcessor $provider_processor) {
    $this->providerProcessor = $provider_processor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('gla_provider.processor')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity = NULL) {

    if ($entity instanceof Group) {
      $provider_profile = $this->providerProcessor->getProviderProfileFromEntity($entity);
      if ($provider_profile) {
        $title = $provider_profile->getTitle();
        $form['name'] = [
          '#markup' => '<h1>' . t('Selected organisation') . ': ' . $title . '</h1>',
        ];
      }
    }
    elseif ($entity instanceof User) {
      $title = $entity->getEmail();
      $form['name'] = [
        '#markup' => '<h1>' . t('Selected user') . ': ' . $title . '</h1>',
      ];
    }

    $form['description'] = [
      '#markup' => $this->getDescriptionText(),
    ];

    $form['action'] = [
      '#type' => 'checkbox',
      '#required' => TRUE,
      '#title' => $this->t($this->actionText)
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Confirm'),
      '#suffix' => Link::createFromRoute($this->t('Cancel and go back'), $this->backRoute)->toString()
    ];

    $form_state->setStorage([
      'entity' => $entity,
    ]);

    return $form;
  }

  /**
   * @return string
   */
  public function getDescriptionText() {
    if (empty($this->actions)) {
      return '';
    }

    $text = '<strong><p>This will:</p></strong>';
    $text .= '<ul>';
    foreach ($this->actions as $action) {
      $text .= "<li>$action</li>";
    }
    $text .= '</ul>';

    return $text;
  }

}
