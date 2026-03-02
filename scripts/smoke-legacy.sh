#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost:8080}"

pages=(
  "/ListEvents.php"
  "/PeopleDashboard.php"
  "/FamilyEditor.php"
  "/VolunteerOpportunityEditor.php"
  "/sundayschool/SundaySchoolDashboard.php"
  "/GroupList.php"
  "/PersonEditor.php"
  "/PersonView.php"
  "/EventEditor.php"
  "/FirstTimers.php"
)

failures=0

for page in "${pages[@]}"; do
  url="${BASE_URL}${page}"
  status="$(curl -s -o /dev/null -w "%{http_code}" "$url")"

  if [[ "$status" == "500" ]]; then
    echo "FAIL 500: $url"
    failures=$((failures + 1))
  else
    echo "OK ${status}: $url"
  fi
done

if [[ "$failures" -gt 0 ]]; then
  echo "Smoke test failed: ${failures} endpoint(s) returned 500."
  exit 1
fi

echo "Smoke test passed."
