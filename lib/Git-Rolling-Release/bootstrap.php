<?php

define('APP_PATH', realpath(getcwd()));
define('LIB_PATH', realpath(getcwd()).'/lib/Git-Rolling-Release');

if (empty($_SERVER['argv']))
{
  fwrite(STDERR, 'Error: Can only be run through the command line.');
  exit(1);
}

if (!isset($_SERVER['argv'][1]))
{
  $_SERVER['argv'][1] = '';
}

// Load core functionality
require_once(LIB_PATH.'/core.php');

// Load class for given command
$coreClassFile = strtolower($_SERVER['argv'][1]).'.php';
@include(LIB_PATH.'/'.$coreClassFile);

if (!class_exists('Git_Rolling_Release_'.$_SERVER['argv'][1]))
{
  // Invalid command provided
  Git_Rolling_Release_Core::printUsage();
  exit();
}

$className = 'Git_Rolling_Release_'.ucfirst($_SERVER['argv'][1]);
$class = new $className;
$class->process();