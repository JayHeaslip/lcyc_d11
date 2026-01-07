This is the Drupal 11 version of the Lake Champlain Yacht Club website.

To deploy to production, on the production server:
* cd "web site root"
* git pull
* composer install --no-dev
* drush -l "site url" updb
* drush cim -y
* In web/themes/custom/lcyc_radix
  * npm run production
* drush cr
