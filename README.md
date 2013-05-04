#Git-Rolling-Release#

A PHP implementation of the [Git-Rolling-Release model](http://mrlunar.github.io/Git-Rolling-Release).

##Installation##
```shell
git clone https://github.com/MrLunar/Git-Rolling-Release.git
cd Git-Rolling-Release
cp releases-example.php releases.php
cd projects
git clone <your repo>
```
Edit the `releases.php` file to suite your release requirements for each project.

##Usage##
```shell
./git-rolling-release <command> <project> [<global args>] [<command args>]
```
Possible commands are
* release
* hotfix
* rollback

##Configuration##
All possible configuration options are available in `lib/Git-Rolling-Release/config-core.php` and can be copied into `config.php` and altered.

##Generating Releases##
Simply by running the following command regularly (e.g. daily), releases will be automatically generated based on the rules set in `releases.php`.

```shell
./git-rolling-release release <project>
```

To generate a specific release, use:

```shell
./git-rolling-release release <project> --force-release=<release>
```