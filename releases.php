<?php

/**
 * List of releases for Git-Rolling-Release to manage.
 */
$releases = array
(
  'live' => array(
    'name'      => 'release-live',
    'tag_from'  => 'tags/release-staging-current',
    'auto'      => true,
    'days'      => array('mon'),
  ),

  'staging' => array(
    'name'      => 'release-staging',
    'tag_from'  => 'master',
    'auto'      => true,
    'days'      => array('mon', 'tue', 'wed', 'thu'),
  ),

  'mitre' => array(
    'name'      => 'release-mitre',
    'tag_from'  => 'live',
    'auto'      => false,
  ),
);