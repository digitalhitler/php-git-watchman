# Git watchman
This small script helps you to monitor `git status` of your repository instances. It's really good idea to monitor it in production to prevent unattended access.

## Requirements
* *nix based server
* PHP 5.5+ and Git installed
* Two minutes of time

## Installation
Clone repository somewhere outside of public accessible folders:
```
cd ~
git clone https://github.com/digitalhitler/php-git-watchman.git
```
Open `watchman.json` and edit script configuration.

`defaults`
* `to` - array or string of default recipients of notifications. They will receive message if you don't defined repository `to` property.
* `from` - default sender email.

`repos`
List of objects with repository configuration. Each of them must have two properties:
* `name` - human-friendly name of repository, e.g. "My app on Amazon"
* `path` - full path to repository root
You can provide `to` and `from` settings, otherwise values from `defaults` will be used.

Save configuration and check if it works:
```
php watchman.php
[ 29.11.2015 02:38:13 ]   VERBOSE    Started with 3 checks queued.
[ 29.11.2015 02:38:13 ]   VERBOSE    Processing VKCM main...
[ 29.11.2015 02:38:13 ]   VERBOSE    18 changes found, message sent.
[ 29.11.2015 02:38:13 ]   VERBOSE    Processing Console-Surf...
[ 29.11.2015 02:38:13 ]   VERBOSE    Processing Etc dir...
[ 29.11.2015 02:38:13 ]   VERBOSE    3 changes found, message sent.
[ 29.11.2015 02:38:15 ]   VERBOSE    Completed
```
Everything is good if there is no errors.

Next, add this task to cron via `crontab -e` command:
```
/usr/bin/php /full/path/to/watchman.php >/dev/null 2>&1
```
Change path to `watchman.php` file and save changes. That's all.

### Customizing
You can edit behavior of script as you want. For example you can modify it to sending push-notifications instead of emails.

## Possible issues
### Sending mail
Please check if your server really can send emails. In most cases out-of-the-box Linux-based operating system use `sendmail` package to handle outgoing mail. Most of public mail services doesn't like senders like `root@localhost` and your message can be not delivered or marked as junk. In this case you can use SMTP-relay for sendmail. There are lots of MTA tools available to do this, for example [E-Mail relay](http://emailrelay.sourceforge.net/).

## Details
This script I have used on the job to monitor production servers. Script has been changing through the years and now it's here.

## Todo
* Comment up and refactor code
* Rename GitWatchmanException for logging
