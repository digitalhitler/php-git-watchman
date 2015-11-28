#!/usr/bin/php -d display_errors -d display_startup_errors
<?php
/*
 *
 * Git Watchman
 *
 * Small utility for e-mail monitoring of git status.
 *
 * https://github.com/digitalhitler/php-git-watchman
 *
 * @todo:
 * - comment everything
 * -
 *
 * Apache 2 Licensed.
 *
 * Copyright (c) 2015, Sergey Petrenko
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED.
 *
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL | ~E_NOTICE);

/**
 * Exceptions handler
 */
class GitWatchmanException extends Exception
{
  private $previous;
  private $level;
  const VERBOSE_LOG_FILE = 'watchman.log';
  const ERRORS_LOG_FILE = 'watchman_errors.log';
  const APP_FULL_DIR = __DIR__.DIRECTORY_SEPARATOR;
  const APP_DATE_FORMAT = "d.m.Y H:i:s";

  public function __construct($message, $level = 'FATAL')
  {
    $this->checkLogFiles();
    $this->level = $level;
    $this->log($message, $this->level);
  }

  static function checkLogFiles() {
    if(!file_exists(self::APP_FULL_DIR.self::VERBOSE_LOG_FILE)) {

      touch (self::APP_FULL_DIR.self::VERBOSE_LOG_FILE);
      file_put_contents(
        self::APP_FULL_DIR.self::VERBOSE_LOG_FILE,
        "Git Watchman everything log file\nStarted at ".date(self::APP_DATE_FORMAT)."\n\n"
      );

    }

    if(!file_exists(self::APP_FULL_DIR.self::ERRORS_LOG_FILE)) {

      touch (self::APP_FULL_DIR.self::ERRORS_LOG_FILE);
      file_put_contents(
        self::APP_FULL_DIR.self::ERRORS_LOG_FILE,
        "Git Watchman errors & fatals log file\nStarted at ".date(self::APP_DATE_FORMAT)."\n\n"
      );

    }
  }

  static function log($message, $level = 'VERBOSE') {

    $logTo = array(self::APP_FULL_DIR . self::VERBOSE_LOG_FILE);

    if(in_array($level, [ "FATAL", "ERROR", "WARNING", "PHPERROR" ])) {
      $logTo[] = self::APP_FULL_DIR . self::ERRORS_LOG_FILE;
    }

    $displayPrefix = '[ '.date(self::APP_DATE_FORMAT, time()).' ] ' .  str_pad($level, 12, " ", STR_PAD_BOTH). ' ';
    $filePrefix = date(self::APP_DATE_FORMAT) . ' ' . $level . ' ';

    echo $displayPrefix . $message."\n";

    foreach($logTo as $file) {
      file_put_contents($file, $filePrefix . $message . "\n", FILE_APPEND);
    }
  }
}

function errorHandler($errNo, $errStr, $errFile, $errLine) {
  throw new GitWatchmanException($errStr . " (errno " . $errNo . " on line ". $errLine . ")", 'PHPERROR');
}

function exceptionHandler($e) {
  if(get_class($e) !== 'GitWatchmanException') {
    $e->log($e->getMessage(), 'PHPEXCEPTION');
  }
}

set_error_handler('errorHandler');
set_exception_handler('exceptionHandler');

class GitRepo
{
  const APP_CONFIG = __DIR__.'/watchman.json';

  static $types = [
    '?' => "untracked",
    'M' => "modified",
    'D' => "deleted",
    'A' => "added",
    'R' => "renamed",
    'C' => "copied",
    'U' => "unmerged"
  ];

  public function __construct($conf, $defaults = false) {
    $path = realpath($conf["path"]) . DIRECTORY_SEPARATOR;
    if(!is_dir($path)) throw new GitWatchmanException("{$path} is not a directory.");
    if(!is_dir($path.".git")) throw new GitWatchmanException("{$path} is not a git repository.");

    if($defaults === false) {
      $defaults = self::getDefaultRepoValues();
    }

    foreach($defaults as $k => $item) {
      if(!isset($conf[$k])) $conf[$k] = $item;
    }

    $this->path = $path;
    $this->name = $conf["name"];

    foreach($conf as $k => $v) {
      if(!is_array($conf[$k])) {
        $conf[$k] = array($v);
      }
    }

    $this->from = $conf["from"];
    $this->to = $conf["to"];
  }

  private function getBranchName() {
    return trim(`git name-rev --name-only HEAD`);
  }

  protected function executeInDirectory($command) {
    $prevDir = getcwd();
    chdir($this->path);
    $result = shell_exec($command);
    chdir($prevDir);
    return $result;
  }

  private function getHashName() {
    return trim($this->executeInDirectory('git rev-parse HEAD'));
  }

  private function parseStatusOutput($output) {
    $changedItems = false;
    if(preg_match_all('/^.+?\\s(.*)$/m', $output, $changes, PREG_SET_ORDER)){
      $changedItems = array();
      foreach( $changes  as $changedItem ){

        list($type, $file) = explode(" ", trim($changedItem[0]));
        $type = trim($type);
        if(strlen($type) > 1) $type = $type[0];

        $changedItems[self::$types[$type]][] = [
          "raw" => $changedItem[0],
          "file" => $changedItem[1],
          "type" => self::$types[$type]
        ];

      }
    }
    return $changedItems;
  }

  public function getLastCommits() {
    return trim($this->executeInDirectory('git log --oneline -n 5'));
  }

  public function getSummary() {
    return [
      "Branch" => $this->getBranchName(),
      "Hash" => $this->getHashName()
    ];
  }

  public function getChanges($getRawAndParsed = false) {
    $gitStatusOutput = $this->executeInDirectory('git status --porcelain');
    $parsedOutput = $this->parseStatusOutput($gitStatusOutput);
    if($getRawAndParsed === true) return [
      "raw" => $gitStatusOutput,
      "parsed" => $parsedOutput
    ];
    else return $parsedOutput;
  }

  public static function getDefaultRepoValues() {
    global $defaults;
    if(is_array($defaults)) return $defaults;
    else {
      return self::getConfiguration("defaults");
    }
  }

  public static function getConfiguration($onlySection = false) {
    $conf = json_decode(file_get_contents(self::APP_CONFIG), true);
    if($onlySection !== false) return $conf[$onlySection];
    else return $conf;
  }
}

class GitRepoQueue extends SplQueue
{

  private $defaults;

  public function __construct($defaultRepoValues = false) {

    if($defaultRepoValues === false) {
      $defaultRepoValues = GitRepo::getDefaultRepoValues();
    }

    if(!is_array($defaultRepoValues)
      || !$defaultRepoValues["to"]
      || !$defaultRepoValues["from"]
    ) throw new GitWatchmanException("Cannot initiate queue: defaults in watchman.json is wrong.");

    $this->defaults = $defaultRepoValues;

  }

  public function refillWith($list) {
    if(!is_array($list)) throw new GitWatchmanException("Cannon enqueue list that not a list");
    foreach($list as $item) {
      $this->enqueue(new GitRepo($item));
    }
    $this->rewind();
  }

}

$conf = GitRepo::getConfiguration();
$defaults = $conf["defaults"];
global $defaults;

$queue = new GitRepoQueue();
$queue->refillWith($conf["repos"]);
GitWatchmanException::Log("Started with ".sizeof($conf["repos"])." checks queued.");

while($item = $queue->current()) {
  $repo = $queue->current();

  if(get_class($repo) !== 'GitRepo') {
    throw new GitWatchmanException("It's not GitRepo class item in queue.", "PHPERROR");
  }

  GitWatchmanException::Log("Processing {$repo->name}...");
  $gitStatus = $repo->getChanges(true);
  $changes = $gitStatus["parsed"];
  if($changes !== false) {
    $changesTypes = array();
    $totalChanges = 0;
    $message = '';
    $listing = '';

    foreach($changes as $type => $list) {
      $changesTypes[$type] = $type;
      $listing.=strtoupper($type).":\n";
      foreach($list as $item) {
        $listing.="  ".$item["file"]."\n";
        $totalChanges++;
      }
      $listing.="\n";
    }

    $typesList = implode($changesTypes, ", ");
    $message = "Hey! I found {$totalChanges} uncommited change(s) in git status of {$repo->name}.\n\n";
    $message.= "SUMMARY:\n";
    $summary = $repo->getSummary();
    $summary["Current time"] = date("r");
    foreach($summary as $name => $val) {
      $message.="  {$name}: {$val}\n";
    }
    $message.="\n{$listing}LAST COMMITS:\n".$repo->getLastCommits()."\n\nRaw git status output (quoted):\n";
    $message.=preg_replace('/.+/', '> $0', $gitStatus["raw"]);
    $message.="\n\nYours, \nWatchman\n".getHostByName(getHostName())."\n";
    $message = wordwrap($message, 70, "\n");

    $subject = "⚠️ {$repo->name}: {$totalChanges} ".$typesList . " file(s)";

    $messageTo = implode($repo->to,", ");
    $headers = "From: {$repo->from[0]}\r\n".
    "X-Mailer: Git Watchman\r\n";
    mail(
      $messageTo,
      $subject,
      $message,
      $headers
    );
    GitWatchmanException::Log("{$totalChanges} changes found, message sent.");
  }

  $queue->next();
}

GitWatchmanException::Log("Completed");
