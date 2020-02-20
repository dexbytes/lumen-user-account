# Lumen PHP Framework

[![Build Status](https://travis-ci.org/laravel/lumen-framework.svg)](https://travis-ci.org/laravel/lumen-framework)
[![Total Downloads](https://poser.pugx.org/laravel/lumen-framework/d/total.svg)](https://packagist.org/packages/laravel/lumen-framework)
[![Latest Stable Version](https://poser.pugx.org/laravel/lumen-framework/v/stable.svg)](https://packagist.org/packages/laravel/lumen-framework)
[![Latest Unstable Version](https://poser.pugx.org/laravel/lumen-framework/v/unstable.svg)](https://packagist.org/packages/laravel/lumen-framework)
[![License](https://poser.pugx.org/laravel/lumen-framework/license.svg)](https://packagist.org/packages/laravel/lumen-framework)

Laravel Lumen is a stunningly fast PHP micro-framework for building web applications with expressive, elegant syntax. We believe development must be an enjoyable, creative experience to be truly fulfilling. Lumen attempts to take the pain out of development by easing common tasks used in the majority of web projects, such as routing, database abstraction, queueing, and caching.

## Official Documentation

Documentation for the framework can be found on the [Lumen website](https://lumen.laravel.com/docs).

## Security Vulnerabilities

If you discover a security vulnerability within Lumen, please send an e-mail to Taylor Otwell at taylor@laravel.com. All security vulnerabilities will be promptly addressed.

## License

The Lumen framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Getting Started 

## First, clone the repo:

 git clone https://github.com/dexbytes/lumen-user-account.git

## Update composer

 composer update

## Create env 

 cp .env.example .env

## Add Your database deatils

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=db_name
DB_USERNAME=*****
DB_PASSWORD=*****

## Add Your email deatils
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=

## Run migration 

 php artisan migrate

## Run Seeders

composer dump-autoload
php artisan db:seed

## Run passport

 php artisan passport:install

 Output:
 Personal access client created successfully.
  Client ID: 1
  Client secret: 0uO2POKFnrl04g0SFbt6uMLHQMI2EVOj7HYCj0jy
 Password grant client created successfully.
  Client ID: 2
  Client secret: paISi2R3PKGLcEyORkYXkUG5TPcQFfanM5ldgopV

## Set in your env file 
    ex. 
    PASSWORD_CLIENT_ID=2
    PASSWORD_CLIENT_SECRET=paISi2R3PKGLcEyORkYXkUG5TPcQFfanM5ldgopV

## Run command for permission

   sudo chmod -R 777 storage/
   sudo chmod -R 777 public/



