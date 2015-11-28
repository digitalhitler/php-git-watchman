# Git watchman
This small script helps you to monitor `git status` of your repository instances. It's really good idea to monitor it in production to prevent unattended access.

## Requirements
* *nix based server
* PHP 5.5+
* Two minutes of time

## Installation
* Clone repository somewhere outside of public accessible folders:
```
cd ~

```
### Customizing
You can modify behavior of script as you want. For example you can modify it to sending push-notifications instead of emails.

## Possible issues
### Sending mail
Please check if your server really can send emails. In most cases out-of-the-box Linux-based operating system use `sendmail` package to handle outgoing mail. Most of public mail services doesn't like senders like `root@localhost` and your message can be not delivered or marked as junk. In this case you can use SMTP-relay for sendmail. There are lots of MTA tools available to do this, for example [E-Mail relay](http://emailrelay.sourceforge.net/).

## Details
This script I have used on the job to monitor production servers. Script has been changing through the years

## Todo
* Comment up and refactor code
* Rename GitWatchmanException for logging
