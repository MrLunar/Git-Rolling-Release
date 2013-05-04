<?php

/**
 * List of releases for Git-Rolling-Release to manage.
 *
 * Releases will be processed in the order provided here.
 */
$releases = array
(
  'live' => array(                    // The reference used in Git-Rolling-Release commands
    'name'      => 'live',            // The prefixes of the generated tags
    'tag_from'  => 'tags/staging-current',    // Be explicit if tagging from another tag, otherwise just the name of the branch
    'auto'      => true,
    'days'      => array('tue'),
  ),

  'staging' => array(
    'name'      => 'staging',
    'tag_from'  => 'master',
    'auto'      => true,
    'days'      => array('tue', 'wed', 'thu', 'fri'),
  ),

  // A custom release may come in handy if you need a slower/faster release cycle for
  // a specific situation, but still require the ability to e.g. hotfix the release.
  // These will not be automatically generated ('auto' => false) during regular usage.
  'jibberjabber' => array(
    'name'      => 'release-jibberjabber',
    'tag_from'  => 'tags/live-current',
    'auto'      => false,
  ),
);