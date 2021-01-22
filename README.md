# Laravel Auto Deploy Envoy Script
Laravel projects deploy made easy and fast using Envoy task runner and this script.
Inspired by [sajadabasi/auto-deploy-scripts](https://github.com/sajadabasi/auto-deploy-scripts).

- This script will provide a zero-downtime deployment.
- You can config and change almost everything.
- You'll be notified how many seconds your deploy took at the end.
- Very easy to use. Start deploy just with one command.


## How to use
### Install Envoy
Documentation for Envoy can be found on the [Laravel website](https://laravel.com/docs/8.x/envoy).
### Add Files
Add `deploy.env` and `envoy.blade.php` to your Laravel project's root.
### Run
Run deploy script using this command :

    envoy run deploy

## Config Variables
You can change config variables in `deploy.env` file.

| Variable                 | Required | Description                                                                                     | Type    | Default | Example                                           |
|--------------------------|----------|-------------------------------------------------------------------------------------------------|---------|---------|---------------------------------------------------|
| GIT_REPOSITORY           | YES      | Project's git repository address                                                                | String  |         | https://github.com/arsamme/my-laravel-project.git |
| GIT_BRANCH               | NO       | Branch that project will be cloned from                                                         | String  | master  | develop                                           |
| DEPLOY_SERVER            | YES      | Deployment server                                                                               | String  |         | root@127.0.0.1                                    |
| DEPLOY_PATH              | YES      | Path in server to clone project inside                                                          | String  |         | /home/arsam/web/arsam.me/                         |
| DEPLOY_DESTINATION_PATH  | YES      | Path which will linked to `public` path of project                                              | String  |         | /home/arsam/web/arsam.me/public_html/             |
| DEPLOY_STORAGE_PATH      | YES      | Path which `storage` folder moved to                                                            | String  |         | /home/arsam/web/arsam.me/storage/                 |
| USER                     | NO       | This will used to define which user owns project's folder in server                             | String  | root    | admin                                             |
| USER_GROUP               | NO       | This will used to define which user group owns project's folder in server                       | String  | root    | admin                                             |
| COMPOSER_INSTALL         | NO       | Set whether to run `composer install` or not                                                    | Boolean | true    |                                                   |
| CREATE_ENV_FILE          | NO       | Set whether to create new `.env` file from `.env.prod` or `.env.example` or not                 | Boolean | true    |                                                   |
| GENERATE_APPLICATION_KEY | NO       | Set whether to generate `application key` using `artisan` command or not                        | Boolean | true    |                                                   |
| NPM_INSTALL              | NO       | Set whether to run `npm install` or not                                                         | Boolean | true    |                                                   |
| NPM_RUN_PROD             | NO       | Set whether to run `npm run prod` or not                                                        | Boolean | true    |                                                   |
| DATABASE_MIGRATION       | NO       | Set whether to run database migrations or not                                                   | Boolean | false   |                                                   |
| DATABASE_SEED            | NO       | Set whether to run database seeders or not                                                      | Boolean | false   |                                                   |
| STORAGE_SYMLINK          | NO       | Set whether to create storage symlink in `public` folder or not, this uses `artisan` command    | Boolean | true    |                                                   |
| CONFIG_CACHE             | NO       | Set whether to run `artisan` cache commands or not                                              | Boolean | true    |                                                   |
| EXTRA_BASH_SCRIPT        | NO       | Extra bash script to run at end of deploy, inside deploy path                                   | String  |         | ls -la                                            |
