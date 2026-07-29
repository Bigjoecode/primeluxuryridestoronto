#!/usr/bin/env bash
#
# Deploy to production.
#
#   ./deploy.sh            push current main and deploy it
#   ./deploy.sh --status   show what is live without changing anything
#   ./deploy.sh --rollback go back one commit on the server
#
# Runs over SSH using the key in ~/.ssh/primeluxury_deploy_ed25519 and the
# "plr" host entry in ~/.ssh/config.
#
# The server is a git checkout of this repository. Deploying is a fetch and
# a hard reset, so the tree always matches a known commit and rollback is
# just resetting to an earlier one.
#
# Never touched by a deploy, because both are git-ignored:
#   includes/config.php   production credentials
#   uploads/              customer-uploaded photographs
#   logs/                 mail and error logs
#
set -euo pipefail

REMOTE=plr
APP_DIR='~/public_html'
BRANCH=main

ssh_run() {
  # One connection per invocation: this host rate-limits rapid SSH dials.
  ssh -o ControlMaster=no -o ControlPath=none -o BatchMode=yes \
      -o ConnectTimeout=25 "$REMOTE" "export TERM=dumb; $1"
}

case "${1:-deploy}" in

  --status)
    echo "Live on production:"
    ssh_run "cd $APP_DIR && git log -1 --format='  %h  %s%n  %an, %ar'"
    echo
    echo "Local HEAD:"
    git log -1 --format='  %h  %s'
    ;;

  --rollback)
    echo "Rolling back one commit..."
    ssh_run "cd $APP_DIR && git reset --hard HEAD~1 -q && git log -1 --format='  now at %h  %s'"
    echo "Done. Check the site, then deploy again when the fix is ready."
    ;;

  deploy)
    # Refuse to deploy work that is not committed and pushed, otherwise the
    # server would end up on a commit that does not exist anywhere else.
    if [ -n "$(git status --porcelain)" ]; then
      echo "Uncommitted changes present. Commit them first:"
      git status --short | sed 's/^/  /'
      exit 1
    fi

    echo "Pushing $BRANCH..."
    git push origin "$BRANCH"

    echo "Deploying..."
    ssh_run "cd $APP_DIR
      before=\$(git rev-parse --short HEAD)
      git fetch --depth 1 origin $BRANCH -q
      git reset --hard origin/$BRANCH -q

      # Re-assert the things git does not track.
      chmod 600 includes/config.php
      mkdir -p uploads/vehicles uploads/site logs
      chmod 755 uploads uploads/vehicles uploads/site logs

      after=\$(git rev-parse --short HEAD)
      if [ \"\$before\" = \"\$after\" ]; then
        echo \"  already up to date at \$after\"
      else
        echo \"  \$before -> \$after\"
        git log --oneline \"\$before..\$after\" | sed 's/^/    /'
      fi"

    echo
    echo "Checking the site responds..."
    code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 30 \
             -A 'Mozilla/5.0' https://primeluxuryridestoronto.ca/ || echo 000)
    if [ "$code" = "200" ]; then
      echo "  https://primeluxuryridestoronto.ca/  ->  $code"
    else
      echo "  WARNING: home page returned $code — check the site before walking away."
      echo "  Roll back with: ./deploy.sh --rollback"
      exit 1
    fi
    ;;

  *)
    echo "Usage: ./deploy.sh [--status|--rollback]"
    exit 1
    ;;
esac
