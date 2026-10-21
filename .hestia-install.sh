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
#   LIBRARIES_PATH  optional JSON registry; omit to keep CONTENT_ROOT at /
#   FORCE_ENV_REFRESH=1  — regenerate runtime dotenv from secrets (wipes manual edits)
set -euo pipefail

composer install --no-dev --optimize-autoloader

# git-src → /home/<user>/web/<domain>/git-src
# Runtime under Hestia private/ (site user can write there; domain root often cannot)
SITE_ROOT="$(cd .. && pwd)"
RUNTIME="${WIKIMAXX_RUNTIME:-${SITE_ROOT}/private/wikimaxx-runtime}"
mkdir -p "${RUNTIME}/data" "${RUNTIME}/content"

RUNTIME_ENV="${RUNTIME}/dotenv"
LEGACY_ENV="${SITE_ROOT}/wikimaxx-runtime/dotenv"
# Previous release (still on disk while this INSTALL_CMD runs in git-src)
LIVE_ENV="${SITE_ROOT}/public_html/dotenv"

CONTENT_ROOT="${CONTENT_ROOT:-${RUNTIME}/content}"
USERS_PATH="${USERS_PATH:-${RUNTIME}/data/users.json}"
ROLES_PATH="${ROLES_PATH:-${RUNTIME}/data/roles.json}"

write_runtime_env() {
	umask 077
	lib_env=""
	if [[ -n "${LIBRARIES_PATH:-}" ]]; then
		lib_env="LIBRARIES_PATH=${LIBRARIES_PATH}"$'\n'
	fi
	cat >"${RUNTIME_ENV}" <<EOF
CONTENT_ROOT=${CONTENT_ROOT}
${lib_env}USERS_PATH=${USERS_PATH}
ROLES_PATH=${ROLES_PATH}
SESSION_SECRET=${SESSION_SECRET}
ADMIN_USER=${ADMIN_USER:-admin}
ADMIN_PASSWORD=${ADMIN_PASSWORD:-}
ADMIN_EMAIL=${ADMIN_EMAIL:-admin@example.com}
EOF
	chmod 600 "${RUNTIME_ENV}"
	echo "Wrote ${RUNTIME_ENV}"
}

if [[ "${FORCE_ENV_REFRESH:-}" == "1" ]]; then
	if [[ -z "${SESSION_SECRET:-}" || "${SESSION_SECRET}" == "change-me"* ]]; then
		SESSION_SECRET="$(openssl rand -hex 32)"
		echo "Generated SESSION_SECRET (stored in ${RUNTIME_ENV})"
	fi
	write_runtime_env
elif [[ -f "${LIVE_ENV}" ]]; then
	# Preserve manual edits made on the live release
	cp -f "${LIVE_ENV}" "${RUNTIME_ENV}"
	chmod 600 "${RUNTIME_ENV}"
	echo "Synced live dotenv → ${RUNTIME_ENV}"
elif [[ -f "${RUNTIME_ENV}" ]]; then
	echo "Reusing ${RUNTIME_ENV}"
elif [[ -f "${LEGACY_ENV}" ]]; then
	mkdir -p "$(dirname "${RUNTIME_ENV}")"
	cp -f "${LEGACY_ENV}" "${RUNTIME_ENV}"
	chmod 600 "${RUNTIME_ENV}"
	echo "Migrated legacy dotenv → ${RUNTIME_ENV}"
else
	if [[ -z "${SESSION_SECRET:-}" || "${SESSION_SECRET}" == "change-me"* ]]; then
		SESSION_SECRET="$(openssl rand -hex 32)"
		echo "Generated SESSION_SECRET (stored in ${RUNTIME_ENV})"
	fi
	write_runtime_env
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
