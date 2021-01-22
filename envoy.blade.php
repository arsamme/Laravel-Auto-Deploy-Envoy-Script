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

function arsBool($value) {
if(is_bool($value)){
return $value;
}
return strcasecmp($value, 'true') ? false : true;
}

$gitRepo = $_ENV['GIT_REPOSITORY'];
$gitBranch = $_ENV['GIT_BRANCH'] ?? "master";

$server = $_ENV['DEPLOY_SERVER'];

$deployPath = rtrim($_ENV['DEPLOY_PATH'], "/");
$destinationPath = rtrim($_ENV['DEPLOY_DESTINATION_PATH'], "/");
$storagePath = $_ENV['DEPLOY_STORAGE_PATH'];

$user = $_ENV['USER'] ?? "root";
$userGroup = $_ENV['USER_GROUP'] ?? "root";

$composerInstall = arsBool($_ENV['COMPOSER_INSTALL'] ?? true);
$createEnvFile = arsBool($_ENV['CREATE_ENV_FILE'] ?? true);
$generateApplicationKey = arsBool($_ENV['GENERATE_APPLICATION_KEY'] ?? true);
$npmInstall = arsBool($_ENV['NPM_INSTALL'] ?? true);
$npmRunProd = arsBool($_ENV['NPM_RUN_PROD'] ?? true);
$databaseMigration = arsBool($_ENV['DATABASE_MIGRATION'] ?? false);
$databaseSeed = arsBool($_ENV['DATABASE_SEED'] ?? false);
$storageSymlink = arsBool($_ENV['STORAGE_SYMLINK'] ?? true);
$configCache = arsBool($_ENV['CONFIG_CACHE'] ?? true);

$extraBashScript = $_ENV['EXTRA_BASH_SCRIPT'] ?? null;

$backupOldBuild = arsBool($_ENV['BACKUP_OLD_BUILD'] ?? false);

if (substr($deployPath, 0, 1) !== "/") throw new Exception('Careful - your deployment path does not begin with /');
@endsetup

@servers(['web' => $server])

@task("deploy")
# Revert changes on error
function revertChanges {
printf "\033[0;31mSomething went wrong. Reverting changes...\033[0m\n"

if [ ! -z "${NEW_BUILD+x}" ] && [ -d "${NEW_BUILD}" ]; then
rm -rf "${NEW_BUILD}"
fi

if [ ! -z "${NEW_BUILD_BACKUP+x}" ] && [ -d "${NEW_BUILD_BACKUP}" ]; then
mv "${NEW_BUILD_BACKUP}" "${NEW_BUILD}"
setPermissions "${NEW_BUILD}"
fi

if [ ! -z "${OLD_BUILD_BACKUP+x}" ] && [ ! -z "${OLD_BUILD_ORIG+x}" ] && [ -d "${OLD_BUILD_BACKUP}" ] || [ -f "${OLD_BUILD_BACKUP}" ]; then
mv "${OLD_BUILD_BACKUP}" "${OLD_BUILD_ORIG}"
setPermissions "${OLD_BUILD_ORIG}"
fi

if [ ! -z "${DESTINATION_PATH_BACKUP+x}" ] && [ ! -z "${DESTINATION_PATH_ORIG+x}" ] && [ -d "${DESTINATION_PATH_BACKUP}" ] || [ -f "${DESTINATION_PATH_BACKUP}" ]; then
rm -rf "${DESTINATION_PATH_ORIG}"
mv "${DESTINATION_PATH_BACKUP}" "${DESTINATION_PATH_ORIG}"
setPermissions "${DESTINATION_PATH_ORIG}"
else
if [ ! -z "${IS_DESTINATION_PATH_SYMLINK+x}" ] && [ ! -z "${DESTINATION_SYMLINK_TARGET+x}" ]; then
rm -rf "${DESTINATION_PATH_ORIG}"
ln -s "${DESTINATION_SYMLINK_TARGET}" "${DESTINATION_PATH_ORIG}"
fi
fi

printf "\033[0;32mChanges reverted.\033[0m\n"
}

# Set file and folder permissions
function setPermissions {
SELECTED_PATH="$1"

chown -R "{{$user}}":"{{$userGroup}}" "${SELECTED_PATH}"
find "${SELECTED_PATH}" -type d -exec chmod 775 {} \;
find "${SELECTED_PATH}" -type f -exec chmod 664 {} \;
}

# Echo connected message
IPADDR=$(ip addr show | grep 'inet ' | grep -v 127.0.0.1 | awk '{print $2}' | cut -d/ -f1)
printf "\033[0;32mConnected to %s\033[0m\n" "$IPADDR"

# Set start time
DEPLOY_START=$(date +%s)

# Exit on commands failure and call revertChanges
set -e
trap revertChanges ERR
trap revertChanges SIGINT
trap revertChanges SIGTERM

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
if [ -d $NEW_BUILD ] || [ -f $NEW_BUILD ]; then
NEW_BUILD_ORIG_PATH=${NEW_BUILD%/}
NOW=$(date +%s)
NEW_BUILD_BACKUP="${NEW_BUILD_ORIG_PATH}_new_${NOW}_backup"
mv "$NEW_BUILD" "$NEW_BUILD_BACKUP"
setPermissions "${NEW_BUILD_BACKUP}";
rm -rf "$NEW_BUILD"
fi
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
ln -s "{{$storagePath}}" ./storage

# Create storage symlink in public folder using artisan command
@if($storageSymlink===true)
    php artisan storage:link
    printf "\033[0;32mStorage symlink created successfully.\033[0m\n"
@endif

# Set file and folder permissions
setPermissions "$(pwd)";

# Set file and folder permissions of storage folder
setPermissions "{{$storagePath}}";

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
if [ -d {{$destinationPath}} ] || [ -f {{$destinationPath}} ]; then
if [ -L {{$destinationPath}} ]; then
IS_DESTINATION_PATH_SYMLINK=true
DESTINATION_PATH_ORIG={{$destinationPath}}
DESTINATION_SYMLINK_TARGET=$(readlink -f {{$destinationPath}})
else
DESTINATION_PATH_ORIG={{$destinationPath}}
DESTINATION_PATH_ORIG=${DESTINATION_PATH_ORIG%/}
NOW=$(date +%s)
DESTINATION_PATH_BACKUP="${DESTINATION_PATH_ORIG}_destination_${NOW}_backup"
mv "$DESTINATION_PATH_ORIG" "$DESTINATION_PATH_BACKUP"
setPermissions "${DESTINATION_PATH_BACKUP}";
fi
fi
rm -rf "{{$destinationPath}}"
ln -sf "$NEW_BUILD" "{{$destinationPath}}"

# Delete old build directory
OLD_BUILD_ORIG="${OLD_BUILD%/}"
NOW=$(date +%s)
OLD_BUILD_BACKUP="${OLD_BUILD_ORIG}_old_${NOW}_backup"
mv "$OLD_BUILD_ORIG" "$OLD_BUILD_BACKUP"
setPermissions "${OLD_BUILD_BACKUP}";

@if($backupOldBuild===false)
    if [ ! -z "${OLD_BUILD_BACKUP+x}" ] && [ -d "${OLD_BUILD_BACKUP}" ] || [ -f "${OLD_BUILD_BACKUP}" ]; then
    rm -rf "${OLD_BUILD_BACKUP}"
    fi

    if [ ! -z "${NEW_BUILD_BACKUP+x}" ] && [ -d "${NEW_BUILD_BACKUP}" ] || [ -f "${NEW_BUILD_BACKUP}" ]; then
    rm -rf "${NEW_BUILD_BACKUP}"
    fi
@endif

DEPLOY_END=$(date +%s)
DEPLOY_TIME=$((DEPLOY_END - DEPLOY_START))
printf "\033[0;32mDeploy took %s seconds.\033[0m\n" $DEPLOY_TIME
@endtask
