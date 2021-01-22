@setup
require __DIR__.'/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, "deploy.env");
try {
$dotenv->load();
$dotenv->required(['GIT_REPOSITORY', 'DEPLOY_SERVER', 'DEPLOY_PATH', 'DEPLOY_DESTINATION_PATH', 'DEPLOY_STORAGE_PATH'])->notEmpty();
} catch ( Exception $e )  {
echo $e->getMessage();
exit;
}

if (! function_exists('evalBool')) {
function evalBool($value) {
return strcasecmp($value, 'true') ? false : true;
}
}

$gitRepo = $_ENV['GIT_REPOSITORY'];
$gitBranch = $_ENV['GIT_BRANCH'] ?? "master";

$server = $_ENV['DEPLOY_SERVER'];

$deployPath = rtrim($_ENV['DEPLOY_PATH'], "/");
$destinationPath = rtrim($_ENV['DEPLOY_DESTINATION_PATH'], "/");
$storagePath = $_ENV['DEPLOY_STORAGE_PATH'];

$user = $_ENV['USER'] ?? "root";
$userGroup = $_ENV['USER_GROUP'] ?? "root";

$composerInstall = evalBool($_ENV['COMPOSER_INSTALL']);
$createEnvFile = evalBool($_ENV['CREATE_ENV_FILE']);
$generateApplicationKey = evalBool($_ENV['GENERATE_APPLICATION_KEY']);
$npmInstall = evalBool($_ENV['NPM_INSTALL']);
$npmRunProd = evalBool($_ENV['NPM_RUN_PROD']);
$databaseMigration = evalBool($_ENV['DATABASE_MIGRATION']);
$databaseSeed = evalBool($_ENV['DATABASE_SEED']);
$storageSymlink = evalBool($_ENV['STORAGE_SYMLINK']);
$configCache = evalBool($_ENV['CONFIG_CACHE']);

$extraBashScript = $_ENV['EXTRA_BASH_SCRIPT'] ?? null;

if (substr($deployPath, 0, 1) !== "/") throw new Exception('Careful - your deployment path does not begin with /');
@endsetup

@servers(['web' => $server])

@task("deploy")
# Echo connected message
IPADDR=$(ip addr show | grep 'inet ' | grep -v 127.0.0.1 | awk '{print $2}' | cut -d/ -f1)
printf "\033[0;32mConnected to %s\033[0m\n" "$IPADDR"

# Set start time
DEPLOY_START=$(date +%s)

# Exit on commands failure
set -e

# Set new build directory
BUILD1={{$deployPath}}"/build1"
BUILD2={{$deployPath}}"/build2"
if [ -d $BUILD1 ]; then
NEW_BUILD=$BUILD2
OLD_BUILD=$BUILD1
else
NEW_BUILD=$BUILD1
OLD_BUILD=$BUILD2
fi
rm -rf "$NEW_BUILD"
mkdir "$NEW_BUILD"
cd "$NEW_BUILD"
printf "\033[0;32mSelected build directory : %s \033[0m\n" "$NEW_BUILD"

# Clone repository
git clone -b "{{$gitBranch}}" --depth 1 "{{$gitRepo}}" .
printf "\033[0;32mRepository cloned.\033[0m\n"

# Composer install
# --no-interaction          Do not ask any interactive question.
# --no-dev                  Disables installation of require-dev packages.
# --prefer-dist             Forces installation from package dist even for dev versions.
# --optimize-autoloader     Optimises generated autoloader file.
@if($composerInstall===true)
    composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader
    printf "\033[0;32mComposer install done.\033[0m\n"
@endif

# Make .env file from .env.prod or .env.example
@if($createEnvFile===true)
    SOURCE_ENV=".env.prod"
    if [ -f ".env.prod" ]; then
    cp .env.prod .env
    else
    cp .env.example .env
    SOURCE_ENV=".env.example";
    fi
    printf "\033[0;32m.env file created from %s\033[0m\n" $SOURCE_ENV
@endif

# Generate application key using artisan command
@if($generateApplicationKey===true)
    php artisan key:generate
    printf "\033[0;32mApplication key generated.\033[0m\n"
@endif

# Install npm packages and run `npm run prod`
@if($npmInstall===true)
    npm i
    printf "\033[0;32mNPM install done.\033[0m\n"
@endif
@if($npmRunProd===true)
    npm run prod
    printf "\033[0;32mNPM run prod done.\033[0m\n"
@endif

# Run migrations, --force is needed in production
@if($databaseMigration===true)
    php artisan migrate --force
    printf "\033[0;32mDatabase migrated successfully.\033[0m\n"
@endif

# Run db seeds
@if($databaseSeed===true)
    php artisan db:seed
    printf "\033[0;32mDatabase seeded successfully.\033[0m\n"
@endif

# Move storage folder to defined storage path
if [ -d "{{$storagePath}}" ]; then
rm -rf storage
else
mv storage "{{$storagePath}}"
fi
ln -s "{{$storagePath}}" ./

# Create storage symlink in public folder using artisan command
@if($storageSymlink===true)
    php artisan storage:link
    printf "\033[0;32mStorage symlink created successfully.\033[0m\n"
@endif

# Set file and folder permissions
chown -R "{{$user}}":"{{$userGroup}}" "$(pwd)"
chown -R "{{$user}}":"{{$userGroup}}" "$(pwd)"
find "$(pwd)" -type d -exec chmod 775 {} \;
find "$(pwd)" -type f -exec chmod 664 {} \;

# Set file and folder permissions of storage folder
chown -R "{{$user}}":"{{$userGroup}}" "{{$storagePath}}"
find "{{$storagePath}}" -type d -exec chmod 775 {} \;
find "{{$storagePath}}" -type f -exec chmod 664 {} \;

# Execute Laravel cache commands
@if($configCache===true)
    php artisan config:clear
    php artisan cache:clear
    php artisan route:clear
    php artisan view:clear
    php artisan optimize
    printf "\033[0;32mCache configured successfully.\033[0m\n"
@endif

@if(!empty($extraBashScript))
    printf "\033[0;32mExecuting extra bash script.\033[0m\n"
    {{$extraBashScript}}
    printf "\033[0;32mExtra bash script executed successfully.\033[0m\n"
@endif

# Go back to base directory
cd "{{$deployPath}}"

# Create symlink of build directory
rm -rf "{{$destinationPath}}"
ln -sf "$NEW_BUILD" "{{$destinationPath}}"

# Delete old build directory
rm -rf "$OLD_BUILD"

DEPLOY_END=$(date +%s)
DEPLOY_TIME=$((DEPLOY_END - DEPLOY_START))
printf "\033[0;32mDeploy took %s seconds.\033[0m\n" $DEPLOY_TIME
@endtask
