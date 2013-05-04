<?php

/**
 * Class Git_Rolling_Release_Core
 *
 * @author Mark Bowker
 */
abstract class Git_Rolling_Release_Core
{
  /**
   * Internal configuration
   *
   * @var array
   */
  protected $config = array();

  /**
   * Arguments provided for the command.
   *
   * @var array
   */
  protected $commandArguments = array();

  /**
   * Projects working directory.
   *
   * TODO: Move to separate config.
   *
   * @var string
   */
  protected $workingDir = "projects";

  /**
   * The specified project to work on.
   *
   * @var string
   */
  protected $project;

  /**
   * Loaded from the releases.php config file.
   *
   * @var array
   */
  protected $releases;

  /**
   * Set default config parameters and general checks.
   *
   * @param array $params
   */
  public function __construct(array $params = array())
  {
    $this->processConfig();

    $this->workingDir = realpath($this->workingDir);
    if (empty($this->workingDir))
    {
      fwrite(STDERR, "Error: Could not locate working directory.\n");
      exit(1);
    }

    // Obtain the releases from the config file
    require(APP_PATH.'/releases.php');
    if (empty($releases))
    {
      fwrite(STDERR, "Error: No releases provided in releases.php file.\n");
      exit(1);
    }
    $this->releases = $releases;

    // Parse the command arguments
    $args = $_SERVER['argv'];
    array_shift($args);
    $this->commandArguments = array_merge($this->commandArguments, $this->parseArgs($args));

    // We always need a project provided
    if (empty($this->commandArguments[0]))
    {
      fwrite(STDERR, "Error: No project provided.\n");
      self::printUsage();
      exit(1);
    }

    $this->project = $this->commandArguments[0];

    // Does it exist?
    if (!file_exists($this->workingDir.'/'.$this->project) || !is_dir($this->workingDir.'/'.$this->project))
    {
      fwrite(STDERR, "Error: The '$this->project' project does not exist in {$this->workingDir}\n");
      exit(1);
    }
    $this->workingDir = $this->workingDir.'/'.$this->project;

    // Move into the project directory
    chdir($this->workingDir);

    // Is the project git controlled?
    if ( ! $this->runCommand("git status", false))
    {
      fwrite(STDERR, "Error: {$this->workingDir} does not appear to be git controlled.\n");
      exit(1);
    }

    // Reset the git project and remote(s) to origin/HEAD
    $this->resetProject();
  }

  /**
   * Runs the command
   */
  public abstract function process();

  /**
   * Imports configuration files.
   */
  private function processConfig()
  {
    $configFiles = array(LIB_PATH.'/config-core.php', APP_PATH.'/config.php');

    foreach ($configFiles as $configFile)
    {
      @include($configFile);

      if (empty($config))
      {
        fwrite(STDERR, "Error: Could not read {$configFile} file.\n");
        exit(1);
      }

      $this->config = array_merge($this->config, $config);
    }

    // TODO: Run some basic checks on config

    return true;
  }

  /**
   * Fetches remote updates then resets master and jarvis/master to origin/master
   */
  protected function resetProject()
  {
    if (isset($this->commandArguments['no-remote-update']) && $this->commandArguments['no-remote-update'] === true)
      return true;

    // Fetch all remotes
    $remotesAll = array_values(array_unique(array_merge(array($this->config['pull_remote']), $this->config['push_remotes'])));
    foreach ($remotesAll as $remote)
    {
      echo "Fetching remote updates ({$remote})...\n";
      $this->runCommand("git fetch {$remote}");
    }

    // Reset local master
    echo "Resetting local master to {$this->config['pull_remote']}/master...\n";
    $this->runCommand("git checkout -q master && git reset --hard {$this->config['pull_remote']}/master");

    // Reset all push remote masters to pull_remote's master branch
    echo "Resetting remotes to {$this->config['pull_remote']}/master...\n";
    $remotesToReset = array_diff($this->config['push_remotes'], array($this->config['pull_remote']));
    foreach ($remotesToReset as $remote)
    {
      echo "  {$remote}: ";
      $this->runCommand("git push {$remote} master");
    }

    return true;
  }

  /**
   * For the name, create/update 2 tags. One for the 'current' release tag, and one for the 'milestone' release tag.
   *
   * @param string      $release
   * @param string      $head
   * @param string|null $suffix             Optional suffix for the milestone tag.
   * @param string|null $message            Optional descriptive message for milestone tag.
   * @param bool        $setCurrentRelease  Set this as the current release.
   *
   * @return array Names of the tags generated
   */
  public function createTags($release, $head, $suffix = null, $message = null, $setCurrentRelease = true)
  {
    // Is the given head valid?
    if ( ! $this->runCommand("git show {$head}", false))
    {
      fwrite(STDERR, "Error: The given head to tag from '{$head} does not appear to be valid.\n");
      exit(1);
    }

    $generatedTags = array();

    // First, create the milestone tag.
    // This is a full annotated tag.
    $tagName = $release."-".date("Y.m.d-Hi").($suffix ? '-'.$suffix : '');
    $tagMessage = "Milestone tag for {$release}.\n\n".$message;
    $this->runCommand("git tag -f {$tagName} {$head} -m '{$tagMessage}'");

    $generatedTags[] = $tagName;

    // Second, create the lightweight tag as a marker for the current release.
    if ($setCurrentRelease)
    {
      $generatedTags[] = $this->setCurrentReleaseTag($release, $head);
    }

    return $generatedTags;
  }

  /**
   * Sets the lightweight 'current release' pointer to the given tag.
   *
   * @param string $release
   * @param string $head
   *
   * @return string
   */
  public function setCurrentReleaseTag($release, $head)
  {
    // TODO: Check if the current release is already at the given head

    $tagName = $release.'-current';
    $this->runCommand("git tag -f {$tagName} {$head}");

    return $tagName;
  }

  /**
   * Pushes tags to remotes.
   *
   * @param array $tags
   * @param array $remotes
   * @param bool  $dryRun
   */
  public function pushTags(array $tags, array $remotes, $dryRun = true)
  {
    foreach ($tags as $tag)
    {
      foreach ($remotes as $remote)
      {
        echo "\n".($dryRun ? 'DRY RUN: ' : '')."Pushing {$tag} to {$remote} ...\n";

        $push_dry_run_arg = $dryRun ? '--dry-run' : '';
        $this->runCommand("git push -f {$push_dry_run_arg} {$remote} tags/{$tag}");
      }
    }
  }

  /**
   * @param       $strCommand
   * @param bool  $blnExit      If true, a failed command will not kill the script, but instead return false.
   *
   * @return mixed
   */
  protected function runCommand($strCommand, $blnExit = true)
  {
    exec($strCommand, $output, $return);

    if ($return != 0)
    {
      if ($blnExit)
      {
        fwrite(STDERR, "Error occurred running command: '{$strCommand}'.\n");
        exit(1);
      }
      else
      {
        return false;
      }
    }

    return $output;
  }

  /**
   * parseArgs Command Line Interface (CLI) utility function.
   *
   * @author Patrick Fisher <patrick@pwfisher.com>
   * @see    https://github.com/pwfisher/CommandLine.php
   */
  protected static function parseArgs(array $argv = array())
  {
    $argv = $argv ? $argv : $_SERVER['argv'];
    array_shift($argv);
    $o = array();
    foreach ($argv as $a) {
      if (substr($a, 0, 2) == '--') {
        $eq = strpos($a, '=');
        if ($eq !== false) {
          $o[substr($a, 2, $eq - 2)] = substr($a, $eq + 1);
        } else {
          $k = substr($a, 2);
          if (!isset($o[$k])) {
            $o[$k] = true;
          }
        }
      } else if (substr($a, 0, 1) == '-') {
        if (substr($a, 2, 1) == '=') {
          $o[substr($a, 1, 1)] = substr($a, 3);
        } else {
          foreach (str_split(substr($a, 1)) as $k) {
            if (!isset($o[$k])) {
              $o[$k] = true;
            }
          }
        }
      } else {
        $o[] = $a;
      }
    }

    return $o;
  }

  /**
   * Echoes out CLI usage and switches.
   */
  public static function printUsage()
  {
    echo <<<EOL

Usage: {$_SERVER['argv'][0]}  <command> <project> [<global args>] [<command args>]

Global arguments:

  --no-remote-update  Does not update remotes before running command. (Be careful, may lead to out-of-date releases.)

Commands:

  release
  hotfix
  rollback


EOL;

  }
}
