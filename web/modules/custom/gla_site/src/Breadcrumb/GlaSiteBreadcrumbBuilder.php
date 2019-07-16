<?php

namespace Drupal\gla_site\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

class GlaSiteBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  /**
   * Breadcrumb text.
   */
  const VOLUNTEER_ACCOUNT_CREATE = 'Create your volunteer account';
  const PROVIDER_ACCOUNT_CREATE = 'Create your organisation account';
  const CHECK_EMAIL = 'Create account';
  const PROVIDER_PROFILE_CREATE = 'Create your organisation profile';
  const PROVIDER_ORG_DASHBOARD = 'Your organisation dashboard';
  const PROVIDER_OPPORTUNITY_CREATE = 'Create a volunteering opportunity';
  const OPPORTUNITY_APPLY = 'Register my interest in @opportunity';
  const NEW_PROVIDER_PROFILE = 'New provider profile';
  const NEW_OPPORTUNITY = 'New role';

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    // Only alter the breadcrumbs we need to.
    $breadcrumb_data = $this->determineBreadcrumb($route_match);
    if (!empty($breadcrumb_data)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    // Get the breadcrumb text and link to the current page.
    $breadcrumb_data = $this->determineBreadcrumb($route_match);

    // Create the breadcrumb item.
    $breadcrumb = new Breadcrumb();
    foreach ($breadcrumb_data as $breadcrumb_datum) {
      // If the supplied data is just text, then use nolink.
      if (!is_array($breadcrumb_datum)) {
        $breadcrumb->addLink(Link::createFromRoute($breadcrumb_datum, '<nolink>'));
      }
      else {
        // Otherwise use given info.
        $breadcrumb->addLink(Link::createFromRoute($breadcrumb_datum['text'], $breadcrumb_datum['route'], $breadcrumb_datum['params']));
      }
    }

    // The breadcrumb links (i.e. back to dashboard etc) will depend on the user.
    $breadcrumb->addCacheContexts(['user']);

    // London.gov home and team london home breadcrumb items are added later in
    // gla_site_system_breadcrumb_alter().
    return $breadcrumb;
  }

  /**
   * Determine what the breadcrumb should be on this page (if we should override
   * it at all).
   */
  public function determineBreadcrumb(RouteMatchInterface $route_match) {

    /** @var Node $node */
    $breadcrumb = [];
    $route_name = $route_match->getRouteName();
    switch ($route_name) {
      // Registration page.
      case 'multiple_registration.role_registration_page':
        $role = $route_match->getParameter('rid');
        if ($role == 'volunteer') {
          $breadcrumb = [self::VOLUNTEER_ACCOUNT_CREATE];
        }
        else {
          $breadcrumb = [self::PROVIDER_ACCOUNT_CREATE];
        }
        break;

      // Check email page.
      case 'gla_user.check_email':
        $breadcrumb = [self::CHECK_EMAIL];
        break;

      // User edit page.
      case 'entity.user.edit_form':
        $roles = $route_match->getParameter('user')->getRoles();
        if (in_array('volunteer', $roles)) {
          $breadcrumb = [self::VOLUNTEER_ACCOUNT_CREATE];
        }
        else {
          $breadcrumb = [self::PROVIDER_ACCOUNT_CREATE];
        }
        break;

      // Provider profile pages.
      case 'gla_provider.application_overview':
      case 'gla_provider.application_check':
      case 'gla_provider.saved':
        $breadcrumb = [self::PROVIDER_PROFILE_CREATE];
        break;

      // Node edit pages.
      case 'entity.node.edit_form':
        $node = $route_match->getParameter('node');
        $node_type = $node->bundle();
        if ($node_type == 'provider_profile') {
          $breadcrumb = [self::PROVIDER_PROFILE_CREATE];
        }
        elseif ($node_type == 'opportunity') {
          $breadcrumb = [[
            'text' => self::PROVIDER_ORG_DASHBOARD,
            'route' => 'gla_provider.dashboard',
            'params' => [],
          ]];
          $breadcrumb[] = self::PROVIDER_OPPORTUNITY_CREATE;
        }
        break;

      // Volunteer account/equal opps etc pages.
      case 'gla_volunteer.equal_opportunities_check':
      case 'gla_volunteer.equal_opportunities':
      case 'gla_volunteer.preferences':
      case 'gla_volunteer.preferences_check':
      case 'gla_volunteer.preferences_overview':
      case 'gla_volunteer.edit_account_overview':
        $breadcrumb = [self::VOLUNTEER_ACCOUNT_CREATE];
        break;

      // Opportunities.
      case 'gla_opportunity.saved':
      case 'gla_opportunity.opportunity_check':
        $breadcrumb = [[
          'text' => self::PROVIDER_ORG_DASHBOARD,
          'route' => 'gla_provider.dashboard',
          'params' => [],
        ]];
        $breadcrumb[] = self::PROVIDER_OPPORTUNITY_CREATE;
        break;

      // Application forms.
      case 'gla_opportunity.apply_overview':
      case 'gla_opportunity.apply':
        $node = $route_match->getParameter('node');
        $opportunity_title = $node->getTitle();
        $breadcrumb[] = str_replace('@opportunity', $opportunity_title, self::OPPORTUNITY_APPLY);
        break;

      // Node view pages.
      case 'entity.node.canonical':
      case 'entity.node.latest_version':
        $node = $route_match->getParameter('node');
        $node_title = $node->getTitle();
        $node_type = $node->bundle();

        if (trim($node_title)) {
          $breadcrumb = [$node_title];
        }
        else {
          // This opportunity/provider profile is still being created.
          if ($node_type == 'provider_profile') {
            $breadcrumb = [self::NEW_PROVIDER_PROFILE];
          }
          elseif ($node_type == 'opportunity') {
            $breadcrumb = [self::NEW_OPPORTUNITY];
          }
        }
        break;

      // Webform.
      case 'entity.webform.canonical':
      case 'entity.webform.confirmation':
        /** @var \Drupal\webform\Entity\Webform $webform */
        $webform = $route_match->getParameter('webform');
        if ($webform) {
          $webform_title = $webform->label();
          $breadcrumb = [$webform_title];
        }
        break;
    }

    return $breadcrumb;
  }


}
