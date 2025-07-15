#!/bin/bash
set -e
TASKS_JSON=$1
API_URL="https://project.thegivehub.com/handle_tasks.php"

jq -c '.[] | select(.tranche == "2" and .completed=="1") | {id, task_name}' "$TASKS_JSON" | while read -r row; do
  id=$(echo "$row" | jq -r '.id')
  name=$(echo "$row" | jq -r '.task_name')
  # escape special characters for grep
  search=$(echo "$name" | sed 's/[].*^$\\/]/\\&/g')
  commit=$(git log --grep="$search" -n 1 --pretty=format:%h || true)
  if [ -n "$commit" ]; then
    note="Completed in commit $commit"
  else
    note="Completed"
  fi
  curl -s -X POST "$API_URL" -d "action=update_notes&task_id=$id&notes=$(echo $note | sed 's/ /%20/g')" >/dev/null
  echo "Updated task $id with note: $note"
done

