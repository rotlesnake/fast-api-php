# Install
```
composer install rotlesnake/fast-api-php

```



# 
# 
# Init app
application folder structure
```
+App
 |----Auth
 |    |------Controllers
 |    |      |--------------LoginController.php
 |    |------Models
 |           |--------------Users.php
 |----ModuleOne
      |------Controllers
      |      |--------------IndexController.php
      |------Models
             |--------------Items.php
.htaccess
index.php
```

/.htaccess
```
Options All -Indexes
<IfModule mod_headers.c>
    Header add Access-Control-Allow-Origin "*"
    Header add Access-Control-Allow-Headers "authorization, token, origin, content-type"
    Header add Access-Control-Allow-Methods "GET, POST, PUT, DELETE, PATCH, OPTIONS"
</IfModule>

RewriteCond %{REQUEST_URI} /www/
RewriteRule ^(.*)$ $1 [L,QSA]

RedirectMatch 404 /App/
RedirectMatch 404 /vendor/

RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-l
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [L,QSA]
```

/index.php
```
require("vendor/autoload.php");
define("APP_PATH",   str_replace("/", DIRECTORY_SEPARATOR, realpath(__DIR__)."/App/") );
define("ROOT_URL",   str_replace("//", "/", dirname($_SERVER["SCRIPT_NAME"])."/") );

$settings = [
        'debug'       => true,
        'timezone'    => 'Etc/GMT-3',
        'locale'      => 'ru_RU.UTF-8',

        'database' => [
            'driver'    => 'sqlite',
            'database'  => APP_PATH."database.db",
            'prefix'    => '',
        ],
//      --- or ---
        'database' => [
            'driver'    => 'mysql',
            'host'      => 'localhost',
            'port'      => '3306',
            'database'  => 'learn',
            'username'  => 'root',
            'password'  => '',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => 'prj_',
            'engine'    => 'InnoDB', //'InnoDB' 'MyISAM'
        ],

];
ini_set('date.timezone', $settings['timezone']);
date_default_timezone_set($settings['timezone']);
setlocale(LC_TIME, $settings['locale']);
ignore_user_abort(true);

$APP = new \FastApiPHP\App(APP_PATH, ROOT_URL);
$APP->init($settings);
$APP->run();
```
