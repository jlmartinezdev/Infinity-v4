#!/bin/bash
set -euo pipefail

RADDB=/etc/freeradius/3.0

RADIUS_DB_HOST="${RADIUS_DB_HOST:-radius-db}"
RADIUS_DB_PORT="${RADIUS_DB_PORT:-3306}"
RADIUS_DB_NAME="${RADIUS_DB_NAME:-radius}"
RADIUS_DB_USER="${RADIUS_DB_USER:-radius}"
RADIUS_DB_PASSWORD="${RADIUS_DB_PASSWORD:-radius}"
RADIUS_SECRET="${RADIUS_SECRET:-cambiar-este-secreto-radius}"
RADIUS_CLIENT_NETWORK="${RADIUS_CLIENT_NETWORK:-0.0.0.0/0}"

escape_sed() {
    printf '%s' "$1" | sed -e 's/[\/&]/\\&/g'
}

sed \
    -e "s/__RADIUS_CLIENT_NETWORK__/$(escape_sed "$RADIUS_CLIENT_NETWORK")/g" \
    -e "s/__RADIUS_SECRET__/$(escape_sed "$RADIUS_SECRET")/g" \
    "$RADDB/clients.conf.template" > "$RADDB/clients.conf"

sed \
    -e "s/__RADIUS_DB_HOST__/$(escape_sed "$RADIUS_DB_HOST")/g" \
    -e "s/__RADIUS_DB_PORT__/$(escape_sed "$RADIUS_DB_PORT")/g" \
    -e "s/__RADIUS_DB_USER__/$(escape_sed "$RADIUS_DB_USER")/g" \
    -e "s/__RADIUS_DB_PASSWORD__/$(escape_sed "$RADIUS_DB_PASSWORD")/g" \
    -e "s/__RADIUS_DB_NAME__/$(escape_sed "$RADIUS_DB_NAME")/g" \
    "$RADDB/mods-available/sql.infinity" > "$RADDB/mods-available/sql"

ln -sfn ../mods-available/sql "$RADDB/mods-enabled/sql"
ln -sfn ../mods-available/expr "$RADDB/mods-enabled/expr"
rm -f "$RADDB/mods-enabled/eap"

rm -f "$RADDB/sites-enabled/default" "$RADDB/sites-enabled/inner-tunnel"
ln -sfn ../sites-available/infinity "$RADDB/sites-enabled/infinity"

# dictionary.mikrotik ya viene en el paquete; nos aseguramos de incluirlo.
if ! grep -q 'dictionary.mikrotik' "$RADDB/dictionary"; then
    echo '$INCLUDE /usr/share/freeradius/dictionary.mikrotik' >> "$RADDB/dictionary"
fi
if ! grep -q 'dictionary.infinity' "$RADDB/dictionary"; then
    echo '$INCLUDE /etc/freeradius/3.0/dictionary.infinity' >> "$RADDB/dictionary"
fi

chown -R freerad:freerad "$RADDB" || true

for i in $(seq 1 40); do
    if timeout 1 bash -c "echo > /dev/tcp/${RADIUS_DB_HOST}/${RADIUS_DB_PORT}" 2>/dev/null; then
        break
    fi
    sleep 1
done

exec freeradius "$@"
