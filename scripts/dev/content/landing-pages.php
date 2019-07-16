<?php

// From the web directory, run drush scr ../scripts/dev/content/landing-pages.php

use \Drupal\node\Entity\Node;

$node_data = [
  // VOLUNTEERS.
  // Create account landing page.
  'create_account' => [
    'title' => 'Create your account',
    'body' => '<p>Creating an account will allow you to set up a volunteer profile. You’ll then be able to:</p>

<ul>
  <li>register your interest in volunteering roles</li>
  <li>see your recommended roles</li>
  <li>save progress and review your volunteering history</li>
</ul>

<p>It takes around 5 minutes to create an account.</p>

<p>We’ll ask for your name and email address and then you’ll be asked to complete 6 equal opportunities monitoring questions.</p>

<p><a class="button button--alt" href="/volunteer/register">Start now</a></p>

<p>If you already have an account you can <a href="/user/login">sign in</a>.</p>',
    'alias' => '/volunteer/register/start',
  ],
  // Equal opportunities landing page.
  'equal_opportunities_monitoring' => [
    'title' => 'Equal opportunities monitoring',
    'body' => '<p>The Greater London Authority (GLA) is committed to providing equal opportunities.</p>

<p>The following questions are for GLA monitoring purposes only and will not be shared on any volunteer applications you make.</p>

<p>You can change your answers at any time by visiting your volunteer account.</p>

<p><a class="button button--alt" href="/volunteer-start">Continue</a></p>',
    'alias' => '/volunteer/equal-opportunities',
  ],
  // Equal opportunities submitted.
  'equal_opportunities_submitted' => [
    'title' => 'Equal opportunities submitted',
    'body' => '<p>Your Equal opportunities have been submitted. You can change them at any time by visiting your account profile.</p>

<strong>Set up your profile - next steps</strong>

<p>Choose the types of opportunities you are looking for and register your interest.</p>

<p><a class="button button--alt" href="/volunteer/dashboard">Continue</a></p>',
    'alias' => '/volunteer/equal-opportunities-submitted',
  ],
  // PROVIDERS.
  // Apply to advertise landing page.
  'provider_apply' => [
    'title' => 'Register as an organisation',
    'body' => '<p>Once your organisation registration has been approved, you will be able to:</p>

<ul>
  <li>create your organisation profile</li>
  <li>advertise volunteering roles in London</li>
  <li>recruit volunteers</li>
  <li>display your organisation profile on the Team London website
  </li>
</ul>

<p>There are 2 questions to answer and we estimate initial registration will take around 2 minutes.</p>

<p><a class="button button--alt" href="/provider/register">Start now</a></p>

<p>If you already have an account you can <a href="/user/login">sign in</a>.</p>',
    'alias' => '/provider/register/start',
  ],
  // Create provider profile landing page.
  'create_profile' => [
    'title' => 'Create your organisation profile',
    'body' => '<p>Before you can add volunteering roles you will need to create a profile.</p>

<p>There are 14 questions to answer and it takes around 25 minutes to complete.</p>

<p><strong>To create your profile you will need to provide:</strong></p>

<ul>
  <li>contact details for your organisation</li>
  <li>a description of your organisation’s mission and aims
  (max. 150 words)</li>
  <li>a description of how you achieve these aims (max. 150 words)</li>
  <li>a description of who benefits from your work (max. 150 words)</li>
  <li>a logo and an image to represent your organisation</li>
</ul>

<p><a class="button button--alt" href="/provider-start">Start now</a>

<p>You can save your progress at any time and return to complete this form later.</p>

<p><strong>Please note:</strong> we will review and approve your profile before listing it on our website.</p>',
    'alias' => '/provider/information',
  ],
  // Create provider post submit landing page.
  'post_submit' => [
    'title' => 'Your profile has been submitted',
    'body' => '<p>Thank you for completing your profile.</p>

<p><strong>What’s next?</strong></p>

<p>We will review and approve or provide feedback on your profile. We’ll let you know via the email address you registered with.</p>

<p>Once your profile has been approved, you’ll be able to start creating volunteering roles.</p>

<p><a href="/provider-start">Return to your profile page</a></p>
<p><a href="/">Read more about Team London</a></p>',
    'alias' => '/provider/post/submit',
  ],
  // Create opportunity post submit landing page.
  'post_submit_opportunity' => [
    'title' => 'Your role has been submitted',
    'body' => '<p>Thank you for completing your role.</p>

<p><strong>What\'s next?</strong></p>

<p>We will review and approve or provide feedback on your role. We\'ll let you know via the email address you registered with.</p>

<p>Once your role has been approved, it will be available to volunteers.</p>

<p><a href="/provider/dashboard">Return to your dashboard</a></p>',
    'alias' => '/provider/role/submit',
  ],
  // GENERIC.
  // Sign up page - both.
  'sign_up_start' => [
    'title' => 'Sign up',
    'body' => '<p><strong>I want to volunteer</strong>

<p>Sign up to register your interest in volunteer roles in London.</p>

<p><a href="/volunteer/register/start">Sign up as a volunteer</a>

<p><strong>I want to advertise volunteering roles</strong>

<p>Sign up as an organisation to:</p>

<ul>
  <li>advertise volunteering roles in London</li>
  <li>recruit volunteers</li>
  <li>list your organisation on our website</li>
</ul>

<p>All organisations are approved by a Team London administrator before they are listed on our website.</p>

<p><a href="/provider/register/start">Sign up as an organisation</a>



<p>If you already have an account you can <a href="/user/login">sign in</a>.</p>
',
    'alias' => '/sign-up',
  ],
];

// Custom site settings.
$config = \Drupal::service('config.factory')->getEditable('gla_site.registration_flow_settings');
foreach ($node_data as $name => $data) {
  $node_config_name = 'node:' . $name;

  // Check if this is already created.
  if ($nid = $config->get($node_config_name)) {
    $node = Node::load($nid);
    if (!$node) {
      $node = Node::create(['type' => 'page']);
    }
  }
  else {
    $node = Node::create(['type' => 'page']);
  }

  $node->set('title', $data['title']);
  $node->set('body', [
    'format' => 'full_html',
    'value' => $data['body'],
  ]);
  $node->set('path', ['alias' => $data['alias']]);
  $node->setPublished(TRUE);
  $node->isDefaultRevision(TRUE);
  $node->set('uid', 1);
  $node->set('moderation_state', 'published');
  $node->save();

  // Save the nid to our config.
  $config->set($node_config_name, $node->id())->save();
}
