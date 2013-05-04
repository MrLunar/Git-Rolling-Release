<?php

/**
 * Class Git_Rolling_Release_Rollback
 *
 * @author Mark Bowker
 */
class Git_Rolling_Release_Rollback extends Git_Rolling_Release_Core
{
  /**
   * The release to rollback
   *
   * @var array
   */
  protected $release;

  /**
   * Set default config parameters and general checks.
   *
   * @param array $params
   */
  public function __construct(array $params = array())
  {
    // Defaults
    $this->commandArguments = array(
      'push' => false,
    );

    parent::__construct($params);
  }

  /**
   * Runs the main release generation process
   */
  public function process()
  {
    if ( ! $this->commandArguments['push'])
    {
      echo "\nRunning in DRY-RUN mode!\n";
    }

    // Valid release?
    if ( ! isset($this->releases[$this->commandArguments[1]]))
    {
      fwrite(STDERR, "ERROR: The release provided '{$this->commandArguments[1]}' is not a valid release.\n");
      exit(1);
    }
    $this->release = $this->releases[$this->commandArguments[1]];

    if (empty($this->commandArguments[2]))
    {
      // List possible tags to rollback to
      // TODO: Determine which one 'current' already points to
      $tags = $this->runCommand("git tag -l '{$this->release['name']}*' | tail -n 6 | tac -");
      $tags = array_values(array_diff($tags, array($this->release['name'].'-current')));

      echo "\nSelect a recent release to roll back to:\n\n";
      for ($i = 0; $i < count($tags); $i++)
      {
        echo " ".($i+1).": ".$tags[$i]."\n";
      }
      echo "\nEnter [1-{$i}]: ";

      $selection = (int) stream_get_line(STDIN, 1024, PHP_EOL);

      if (!$selection)
      {
        fwrite(STDERR, "Error: Invalid input.\n");
        exit(1);
      }

      $tag = $tags[$selection-1];

      // Update the 'current release' tag
      $currentTag = $this->setCurrentReleaseTag($this->release['name'], 'tags/'.$tag);
      $this->pushTags(array($currentTag), $this->config['push_remotes'], !$this->commandArguments['push']);

      echo "\nSuccessfully rolled '{$currentTag}' back to '{$tag}'.\n";
    }
    else
    {
      // Verify the given tag exists and is downwind of the 'current' tree

    }

  }

  /**
   * Echoes out CLI usage and switches.
   */
  public static function printUsage()
  {
    echo <<<EOL

Usage: {$_SERVER['argv'][0]} rollback <project> <release> [<tag>] [<switches>]

Switches:

  --push         Pushes to remotes after generating the release. If not provided, the release will be
                 not take effect and will be scrapped on next run.


EOL;

  }
}
