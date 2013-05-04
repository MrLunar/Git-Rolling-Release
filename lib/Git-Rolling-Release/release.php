<?php

/**
 * Class Git_Rolling_Release_Release
 *
 * @author Mark Bowker
 */
class Git_Rolling_Release_Release extends Git_Rolling_Release_Core
{
  /**
   * @var string
   */
  protected $todaysDay;

  /**
   * @var array
   */
  protected $releasesToGenerate = array();

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
      'force-release' => false,
    );

    parent::__construct($params);

    $this->todaysDay = strtolower(date('D'));
  }

  /**
   * Runs the main release generation process
   */
  public function process()
  {
    $this->getReleasesToGenerate();
    if (empty($this->releasesToGenerate))
    {
      fwrite(STDOUT, "No releases to generate.\n");
      exit();
    }

    if ( ! $this->commandArguments['push'])
    {
      echo "\nRunning in DRY-RUN mode!\n";
    }

    $strOutput = "\nGenerating the following releases: ";
    foreach ($this->releasesToGenerate as $release)
      $strOutput .= $release['name'].", ";
    echo rtrim($strOutput, ', ')."\n";

    foreach ($this->releasesToGenerate as $release)
    {
      // Create normal release tags
      $generatedTags = $this->createTags($release['name'], $release['tag_from']);

      // Push tags to remotes
      $this->pushTags($generatedTags, $this->config['push_remotes'], !$this->commandArguments['push']);
    }
  }

  /**
   * Unless a release has been specified as an argument, determine which releases should be generated today.
   *
   * @return bool
   */
  public function getReleasesToGenerate()
  {
    if (!empty($this->commandArguments['force-release']))
    {
      if (!isset($this->releases[$this->commandArguments['force-release']]))
      {
        fwrite(STDERR, "ERROR: The release '{$this->commandArguments['force-release']}' was specified, but is not configured as a release.\n");
        exit(1);
      }

      $this->releasesToGenerate = array($this->releases[$this->commandArguments['force-release']]);
      return true;
    }

    foreach ($this->releases as $arrRelease)
    {
      if ($arrRelease['auto'] && in_array($this->todaysDay, $arrRelease['days']))
      {
        $this->releasesToGenerate[] = $arrRelease;
      }
    }

    return true;
  }

  /**
   * Echoes out CLI usage and switches.
   */
  public static function printUsage()
  {
    echo <<<EOL

Usage: {$_SERVER['argv'][0]} release [switches] project

Switches:

  --force-release=<release>   If a release is not meant to be generated today, force it to be generated.

  --push                      Pushes to remotes after generating the release. If not provided, the release will be
                              not take effect and will be scrapped on next run.


EOL;

  }
}
