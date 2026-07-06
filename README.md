### Drupal 11 version of the Lake Champlain Yacht Club website

Deployment is done using github actions. The deployment script is in .github/workflows/deploy.yml.
Click on the Actions tab to see deployment examples.

To make updates on the staging branch, first sync up staging to latest main:

  * git checkout staging
  * git fetch origin
  * git reset --hard origin/main
  * git push origin staging --force

See what's outdated:
  * composer outdated "drupal/*"

Update core packages:
  * composer update "drupal/core-*" --with-all-dependencies

Update any other packages that need it, then
  * drush updatedb (if needed)
  * drush cr

Check function on sandbox , then checkin and push staging for final checks
