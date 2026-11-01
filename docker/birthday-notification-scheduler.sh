#!/usr/bin/env bash
# Runs the daily birthday notification job for the churchcrm-cron sidecar.
#
# The job runs once per day at or after BIRTHDAY_NOTIFICATION_TIME (HH:MM,
# container local time set by TZ). If the container starts after that time
# and today's run has not completed yet, it runs straight away. The PHP job
# itself never sends a recipient more than one email per day, so a re-run is
# harmless.

set -u

RUN_AT="${BIRTHDAY_NOTIFICATION_TIME:-08:00}"
SCRIPT="/var/www/html/src/cron/send-birthday-notifications.php"
STATE_FILE="/tmp/birthday-notifications.last-success"
CHECK_INTERVAL="${SCHEDULER_CHECK_INTERVAL:-30}"

if ! [[ "$RUN_AT" =~ ^([01][0-9]|2[0-3]):[0-5][0-9]$ ]]; then
  echo "[birthday-scheduler] Invalid BIRTHDAY_NOTIFICATION_TIME '$RUN_AT' (expected HH:MM)" >&2
  exit 1
fi

echo "[birthday-scheduler] Started. Daily run at ${RUN_AT} (TZ=${TZ:-system default})."

while true; do
  today="$(date +%F)"
  now="$(date +%H:%M)"
  last="$(cat "$STATE_FILE" 2>/dev/null || true)"

  if [[ "$now" > "$RUN_AT" || "$now" == "$RUN_AT" ]] && [[ "$last" != "$today" ]]; then
    echo "[birthday-scheduler] $(date '+%F %T') Running birthday notifications"
    output="$(php "$SCRIPT" 2>&1)"
    code=$?
    echo "$output"
    # Only mark the day done when the job actually ran (it prints a JSON status)
    # and nothing failed. Otherwise retry on the next check, e.g. while the
    # database is waiting for an upgrade or SMTP is temporarily down.
    if [[ $code -eq 0 && "$output" == *'"status"'* ]]; then
      echo "$today" > "$STATE_FILE"
    else
      echo "[birthday-scheduler] Run did not complete (exit $code); will retry in 15 minutes" >&2
      sleep 900
    fi
  fi

  sleep "$CHECK_INTERVAL"
done
