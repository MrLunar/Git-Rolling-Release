<?php
/**
 * Core Git-Rolling-Release configuration.
 *
 * DO NOT ALTER THIS FILE. Override the values you want in config.php
 */
$config = array(

  // Folder where projects are located. Can be relative or absolute.
  'working_dir' => 'projects',

  // The master branch of this remote is deemed as the working branch.
  'pull_remote' => 'origin',

  // If tags should be pushed to other remotes, include them here.
  // WARNING: Git-Rolling-Release will also reset the master branch of these remotes.
  'push_remotes' => array(
    'origin',
  ),
);