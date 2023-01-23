# Wordpress-Gitea-manager
Little single file page that help managing Gitea repository on wordpress

## Features
WGM Let you sync your wordpress folders, it can pull and download repository. 
- Download repository
- Pull from commit
- Revert to a previsous version
- Let you choose branch
- Will support private repository

To use the page, just access the php file directly in your browser, you might want to delete it or to put a good password

![image](https://user-images.githubusercontent.com/23726572/214162875-f62740c8-57e1-4a45-9f7d-e131f4672ce8.png)

## Installation
Put the file at the root of your wordpress instance

## Configuration

Add the confing in wp-config.php

```php
define( 'GIT_HOST', "YOUR_HOST" );
define( 'GIT_USER', "YOUR_USER" );
define( 'GIT_REPO', "YOUR_REPO" );
define( 'GIT_PASS', "YOUR_PASSWORD_TO_SECURIZE_THE_FILE" );
define( 'GIT_TARGET', "YOUR_TARGET_FOLDER" );
```

- GIT_HOST : URL to your Gitea host
- GIT_USER : Your Gitea username
- GIT_REPO : Wich repository you want to manage for your wordpress website
- GIT_PASS : Password to prevent anybody to mess with your wordpress files
- GIT_TARGET : The folder where all the files will be copied or deleted

