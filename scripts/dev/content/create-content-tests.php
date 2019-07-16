<?php

use \Drupal\node\Entity\Node;

$node = Node::create(['type' => 'landing_page']);
$node->set('title', 'Create your volunteering account');
$node->set('body', [
  'format' => 'full_html',
  'value' => '<blockquote>
<p>Initial registration is quick, with&nbsp;only 2 questions to answer. After this you will be asked 6 equal opportunities questions that we&nbsp;estimate will take around 5 minutes to complete</p>
</blockquote>

<p>&nbsp;</p>

<p>When you create an account you will&nbsp;be able to:</p>

<ul>
	<li>Create your volunteer profile</li>
	<li>Register your interest in volunteer&nbsp;opportunities</li>
	<li>Save progress when registering&nbsp;interest</li>
	<li>Re-use interest submissions to apply&nbsp;for future volunteering opportunities</li>
</ul>

<p>If you already have an account you can&nbsp;<a class="btn" href="/user/login">sign in</a>.</p>

<p>&nbsp;</p>

<p><a class="btn" href="/volunteer/register">Get started</a></p>',
]);
$node->set('path', ['alias' => '/volunteer/register/start']);
$node->setPublished(TRUE);
$node->save();


$node = Node::create(['type' => 'landing_page']);
$node->set('title', 'Equal opportunities monitoring');
$node->set('body', [
  'format' => 'full_html',
  'value' => '<p>The Greater London Authority are committed to providing equal opportunities for all.</p>

<p>&nbsp;</p>

<p>The following questions are for GLA monitoring purposes only and will not be shared with any volunteer applications you make.</p>

<p>&nbsp;</p>

<p>You can change/update these details at any time by visiting your volunteer account profile.</p>

<p>&nbsp;</p>

<blockquote>
<p>We estimate answering these question will take around 5 minutes of your time</p>
</blockquote>

<p><a class="button button--alt" href="/volunteer/register">Get started</a></p>',
]);
$node->set('path', ['alias' => '/volunteer/register/start']);
$node->setPublished(TRUE);
$node->save();
