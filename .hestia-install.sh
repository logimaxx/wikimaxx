#!/usr/bin/env bash
# Hestia git-deploy INSTALL_CMD entrypoint.
# Panel setting:  INSTALL_CMD='bash .hestia-install.sh'
#                 OUTPUT_DIR=.
#                 EXCLUDE=.git,.env,.env.*,node_modules,tests,.github
# Then point the domain custom docroot Directory at: public
#
# secrets.env (build-only) may set:
#   SESSION_SECRET, ADMIN_USER, ADMIN_PASSWORD, ADMIN_EMAIL
#   CONTENT_ROOT, USERS_PATH, WIKIMAXX_RUNTIME, SEED_CONTENT=yes
set -euo pipefail

composer install --no-dev --optimize-autoloader

# git-src → /home/<user>/web/<domain>/git-src
# Runtime under Hestia private/ (site user can write there; domain root often cannot)
SITE_ROOT="$(cd .. && pwd)"
RUNTIME="${WIKIMAXX_RUNTIME:-${SITE_ROOT}/private/wikimaxx-runtime}"
mkdir -p "${RUNTIME}/data" "${RUNTIME}/content"

RUNTIME_ENV="${RUNTIME}/dotenv"
CONTENT_ROOT="${CONTENT_ROOT:-${RUNTIME}/content}"
USERS_PATH="${USERS_PATH:-${RUNTIME}/data/users.json}"

if [[ ! -f "${RUNTIME_ENV}" || "${FORCE_ENV_REFRESH:-}" == "1" ]]; then
	if [[ -z "${SESSION_SECRET:-}" || "${SESSION_SECRET}" == "change-me"* ]]; then
		echo "ERROR: set SESSION_SECRET in git-deploy secrets.env before first deploy" >&2
		exit 1
	fi
	umask 077
	cat >"${RUNTIME_ENV}" <<EOF
CONTENT_ROOT=${CONTENT_ROOT}
USERS_PATH=${USERS_PATH}
SESSION_SECRET=${SESSION_SECRET}
ADMIN_USER=${ADMIN_USER:-admin}
ADMIN_PASSWORD=${ADMIN_PASSWORD:-}
ADMIN_EMAIL=${ADMIN_EMAIL:-admin@example.com}
EOF
	chmod 600 "${RUNTIME_ENV}"
	echo "Wrote ${RUNTIME_ENV}"
else
	echo "Reusing ${RUNTIME_ENV}"
fi

# Ship as dotenv (not .env*) so the release rsync keeps it
cp -f "${RUNTIME_ENV}" ./dotenv
chmod 600 ./dotenv

if [[ "${SEED_CONTENT:-}" == "yes" ]] && [[ ! -e "${CONTENT_ROOT}/index.md" ]] && [[ -d seed ]]; then
	echo "Seeding content into ${CONTENT_ROOT}"
	php bin/migrate-content.php seed
fi

if [[ -n "${ADMIN_PASSWORD:-}" ]] && [[ ! -f "${USERS_PATH}" ]]; then
	php bin/seed-admin.php
fi

echo "WikiMaxx install OK (CONTENT_ROOT=${CONTENT_ROOT})"
