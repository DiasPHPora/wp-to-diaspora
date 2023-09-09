#!/bin/bash

# Based on:
# - https://github.com/deanc/wordpress-plugin-git-svn
# - https://gist.github.com/kloon/6487562
# - https://github.com/GaryJones/wordpress-plugin-svn-deploy

echo
echo "WordPress Plugin from Git to SVN"
echo "================================"
echo

# Main config.
CURRENT_DIR="$(pwd)"
PLUGIN_SLUG="wp-to-diaspora"
PLUGIN_PATH="${CURRENT_DIR}" # this file should be in the base of your git repository
PLUGIN_FILE="${PLUGIN_PATH}/${PLUGIN_SLUG}.php" # this should be the name of your main php file in the WordPress plugin

# SVN config.
SVN_PATH="/tmp/${PLUGIN_SLUG}-${RANDOM}${RANDOM}" # path to a temp SVN repo. No trailing slash required and don't add trunk.
SVN_URL="https://plugins.svn.wordpress.org/${PLUGIN_SLUG}" # Remote SVN repo on wordpress.org, with no trailing slash
SVN_USER="gutobenn" # your svn username
SVN_IGNORE="
.editorconfig
.git
.gitignore
.scrutinizer.yml
.travis.yml
composer.json
composer.lock
deploy.sh
phpcs.xml.dist
phpunit.xml.dist
README.md
tests
vendor-bin"

# Set the default editors for the commit messages.
EDITOR="${EDITOR:-${SVN_EDITOR}}"
EDITOR="${EDITOR:-vi}"
SVN_EDITOR="${SVN_EDITOR:-${EDITOR}}"

###
# Before we continue, let's make sure that all prerequisites are met.
###
# Check version in readme.txt is the same as in the plugin file.
NEW_VERSION="$(grep "Stable tag" ${PLUGIN_PATH}/readme.txt | awk -F' ' '{ print $NF}')"
VERSION_CHECK="$(grep "Version" ${PLUGIN_FILE} | awk -F' ' '{ print $NF}')"
if [[ "${NEW_VERSION}" != "${VERSION_CHECK}" ]]; then
  echo "Plugin file and readme.txt versions don't match. Exiting..."
  exit 1
fi

# Make sure this version hasn't already been tagged.
if git show-ref --tags --quiet --verify -- "refs/tags/v${NEW_VERSION}"; then
  echo "Git tag v${NEW_VERSION} already exists. Exiting...."
  exit 1
fi

# For collaborators to enter their own username.
printf "Your WordPress repo SVN username (${SVN_USER}): "
read -e input
SVN_USER="${input:-${SVN_USER}}" # Populate with default if empty
echo

echo "--------"
echo "Plugin slug:      ${PLUGIN_SLUG}"
echo "Version:          ${NEW_VERSION}"
echo "Temp SVN path:    ${SVN_PATH}"
echo "Remote SVN repo:  ${SVN_URL}"
echo "SVN username:     ${SVN_USER}"
echo "Plugin directory: ${PLUGIN_PATH}"
echo "Main file:        ${PLUGIN_FILE}"
echo "--------"
echo

printf "Get this show on the road (Y|n)? "
read -e input
if [[ "${input:-y}" != "y" ]]; then echo "Exiting..."; exit 1; fi
echo

# Let's begin...
echo "--------"
echo "Preparing to deploy ${PLUGIN_SLUG} version ${NEW_VERSION}"
echo "--------"

echo

cd "${PLUGIN_PATH}"

printf "Checkout master branch..."
git checkout master &>/dev/null
echo " Done."

printf "Tagging new version in git..."
git tag -a "v${NEW_VERSION}"
echo " Done."

printf "Pushing tags to master..."
git push origin master --tags &>/dev/null
echo " Done."

echo

echo "Creating local copy of SVN repo..."
printf " - assets..."
svn co "${SVN_URL}/assets" "${SVN_PATH}/assets" &>/dev/null
echo " Done."
printf " - trunk..."
svn co "${SVN_URL}/trunk" "${SVN_PATH}/trunk" &>/dev/null
echo " Done."

echo

printf "Exporting the HEAD of master from git to the trunk of SVN..."
# Remove files from the SVN trunk to ensure a clean export from git.
rm -rf "${SVN_PATH}/trunk"/*
git checkout-index -a -f --prefix="${SVN_PATH}/trunk/"
mv -f "${SVN_PATH}/trunk/assets"/* "${SVN_PATH}/assets"
rm -rf "${SVN_PATH}/trunk/assets"
echo " Done."

echo

###
# Update assets
###
echo "Updating assets..."
cd "${SVN_PATH}/assets/"

# Add all new files.
printf " - Adding new files..."
svn status | grep -v "^.[ \t]*\..*" | grep "^?" | awk '{print $2"@"}' | xargs svn add
echo " Done."
# Remove any deleted files.
printf " - Removing deleted files..."
svn status | grep -v "^.[ \t]*\..*" | grep "^\!" | awk '{print $2"@"}' | xargs svn del
echo " Done."
printf " - Commit assets..."
svn commit --username="${SVN_USER}" -m "Version ${NEW_VERSION}"
echo " Done!"

echo

###
# Update trunk
###
echo "Updating trunk..."
cd "${SVN_PATH}/trunk/"

# Install the dependencies with composer
printf " - Bring dependencies up to date (composer install)..."
composer install
composer bin build install
composer compose

composer install --no-dev --prefer-dist --optimize-autoloader
composer dump-autoload

# We don't need the vendor bin folder.
rm -rf vendor/bin &>/dev/null
echo " Done."

printf " - Ignore GitHub specific files..."
svn propset svn:ignore "${SVN_IGNORE}" . &>/dev/null
echo " Done."

# Add all new files.
printf " - Adding new files..."
svn status | grep -v "^.[ \t]*\..*" | grep "^?" | awk '{print $2"@"}' | xargs svn add &>/dev/null
echo " Done."
# Remove any deleted files.
printf " - Removing deleted files..."
svn status | grep -v "^.[ \t]*\..*" | grep "^\!" | awk '{print $2"@"}' | xargs svn del &>/dev/null
echo " Done."

printf " - Commit to trunk..."
svn commit --username="${SVN_USER}" -m "Version ${NEW_VERSION}"
echo " Done!"

echo

###
# Tag new version on SVN
###
printf "Creating new SVN tag..."
svn copy "${SVN_URL}/trunk" "${SVN_URL}/tags/${NEW_VERSION}" -m "Version ${NEW_VERSION}"
echo " Done."

echo

printf "Removing temporary directory ${SVN_PATH}..."
rm -fr "${SVN_PATH}"
echo " Done."

echo
echo "--------"
echo "All good! ${PLUGIN_SLUG} is now on version ${NEW_VERSION}"
echo "--------"
