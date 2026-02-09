### Drupal 11 version of the Lake Champlain Yacht Club website

Deployment is done using github actions. The deployment script is in .github/workflows/deploy.yml.
Click on the Actions tab to see deployment examples.

To make updates on the staging branch, first sync up stagibg to latest main:

  * git checkout staging
  * get fetch origin
  * git reset --jard origin/main
  * git push origin staging --force
  