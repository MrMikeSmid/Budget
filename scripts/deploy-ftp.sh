#!/usr/bin/env bash

set -Eeuo pipefail

readonly REQUIRED_VARIABLES=(
  FTP_SERVER
  FTP_USERNAME
  FTP_PASSWORD
  FTP_SERVER_DIR
)

for variable in "${REQUIRED_VARIABLES[@]}"; do
  if [[ -z "${!variable:-}" ]]; then
    printf 'Fout: de omgevingsvariabele %s is niet ingesteld.\n' "$variable" >&2
    exit 1
  fi

  if [[ "${!variable}" == *$'\n'* || "${!variable}" == *$'\r'* ]]; then
    printf 'Fout: de omgevingsvariabele %s mag geen regeleinde bevatten.\n' "$variable" >&2
    exit 1
  fi
done

if ! command -v lftp >/dev/null 2>&1; then
  printf 'Fout: lftp is vereist maar niet geïnstalleerd.\n' >&2
  exit 1
fi

# Escape values before inserting them into lftp's quoted command syntax.
escape_lftp_value() {
  local value=$1
  value=${value//\\/\\\\}
  value=${value//\"/\\\"}
  printf '%s' "$value"
}

ftp_server=$(escape_lftp_value "$FTP_SERVER")
ftp_username=$(escape_lftp_value "$FTP_USERNAME")
ftp_password=$(escape_lftp_value "$FTP_PASSWORD")
ftp_server_dir=$(escape_lftp_value "$FTP_SERVER_DIR")

printf 'Deploy naar %s gestart...\n' "$FTP_SERVER_DIR"

lftp <<EOF_LFTP
set cmd:fail-exit true
set net:max-retries 2
set net:timeout 20
set ftp:passive-mode true
set ftp:ssl-allow true
set ssl:verify-certificate true
open "$ftp_server"
user "$ftp_username" "$ftp_password"
mirror --reverse \
  --delete \
  --verbose \
  --parallel=4 \
  --exclude='(^|/)\.git(/|$)' \
  --exclude='(^|/)\.github(/|$)' \
  --exclude='(^|/)\.gitkeep$' \
  ./ "$ftp_server_dir"
bye
EOF_LFTP

printf 'Deploy succesvol afgerond.\n'
