<?php

/**
 * Class Git_Rolling_Release_Hotfix
 *
 * @author Mark Bowker
 */
class Git_Rolling_Release_Hotfix extends Git_Rolling_Release_Core
{
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
    if (empty($this->commandArguments[1]) || empty($this->commandArguments[2]))
    {
      // No release or SHA provided
      $this->printUsage();
      exit(1);
    }

    if ( ! $this->commandArguments['push'])
    {
      echo "\nRunning in DRY-RUN mode!\n";
    }

    $release = $this->commandArguments[1];
    $hotfix_sha = $this->commandArguments[2];

    // Check the release exists
    if ( ! isset($this->releases[$release]))
    {
      fwrite(STDERR, "Error: '{$release}' is not a valid release.\n");
      exit(1);
    }

    $release = $this->releases[$release];

    // Check the SHA is a valid commit
    $output = $this->runCommand("git log --pretty=%P -n 1 {$hotfix_sha}");
    if (empty($output[0]) || !is_array($output))
    {
      fwrite(STDERR, "ERROR: Unexpected output when trying to obtain information about '{$hotfix_sha}'. Exiting.\n");
      exit(1);
    }

    // Valid commits will only have one parent. You cannot cherry-pick a merge.
    $parents = explode(' ', $output[0]);
    if (count($parents) > 1)
    {
      fwrite(STDERR, "ERROR: The given SHA '{$hotfix_sha}' is not a valid hotfix commit (is it a merge?). Exiting.\n");
      exit(1);
    }

    // Verify that this commit is on the master branch. Otherwise when the release is next updated, the fix will be lost.
    $branches = $this->runCommand("git branch --contains {$hotfix_sha}");
    foreach ($branches as &$branch)
    {
      $branch = trim(str_replace('* ', '', $branch));
    }
    if (empty($branches) || !in_array('master', $branches))
    {
      fwrite(STDERR, "ERROR: The given SHA '{$hotfix_sha}' does not exist in the master branch.\n");
      exit(1);
    }

    // Get the SHA of the tag
    $output = $this->runCommand("git rev-list tags/{$release['name']}-current | head -n 1");
    if (empty($output[0]))
    {
      fwrite(STDERR, "ERROR: Couldn't identify the commit for the tag '{$release['name']}-current'.\n");
      exit(1);
    }
    $release_sha = $output[0];

    // Checks done. We will now create a branch from the release tag then cherry-pick the commit.
    $this->runCommand("git show-ref --verify --quiet refs/heads/temp-branch && git checkout -q master && git branch -D temp-branch", false);
    $output = $this->runCommand("git checkout -q -b temp-branch {$release_sha} && git cherry-pick {$hotfix_sha}", false);

    if ($output === false)
    {
      fwrite(STDERR, "\nError: Failed to cherry-pick the given commit, probably due to a conflict.\n");
      fwrite(STDERR, "Error: Or this commit is already part of the release's tree, therefore already exists.\n");
      fwrite(STDERR, "Error: If this is an old commit, you should consider bringing the release forward.\n");

      // TODO: automatically determine if the given commit already exists in the release tree
      fwrite(STDERR, "\nError: First 20 lines of the merged diff (if below is empty, this commit already exists):\n\n");
      $diff = $this->runCommand("git diff --diff-filter=U --color");
      fwrite(STDERR, implode("\n", array_slice($diff, 0, 20))."\n");

      // Cleanup
      $this->runCommand("git reset --hard && git checkout -q master && git branch -D temp-branch");

      exit(1);
    }

    // Create hotfix release tags
    $message = "Created due to cherry-picked hotfix.";
    $generatedTags = $this->createTags($release['name'], 'temp-branch', 'hotfix', $message);

    // Push tags to remotes
    $this->pushTags($generatedTags, $this->config['push_remotes'], !$this->commandArguments['push']);

    echo "\nThe new tree for this tag ({$release['name']}):\n\n";
    $output = $this->runCommand("git log --graph --format=format:'%Cred%h%Creset -%C(yellow)%d%Creset %Cgreen%an%Creset %Cblue%ad%Creset %s' --date=relative tags/{$release['name']}-current | head -n 10");
    foreach ($output as $line)
      echo str_replace(', temp-branch', '', $line)."\n";
    echo ".\n.\n\n";

    // Clean-up
    $this->runCommand("git checkout -q master && git branch -D temp-branch");
  }

  /**
   * Echoes out CLI usage and switches.
   */
  public static function printUsage()
  {
    echo <<<EOL

Usage: {$_SERVER['argv'][0]} hotfix <project> <release> <SHA> [<switches>]

Switches:

  --push     Pushes to remotes after generating the release. If not provided, the release will be
             not take effect and will be scrapped on next run.


EOL;

  }
}
