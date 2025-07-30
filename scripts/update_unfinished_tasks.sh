#!/bin/bash
# This script checks the project task API for tasks that are unfinished or missing notes
# and attempts to find matching git commits by task name. If a commit is found,
# the task notes are updated with the commit hash and the task is marked as completed.

set -e
API_URL="https://project.thegivehub.com/handle_tasks.php"

# Fetch all tasks
TASKS_JSON=$(curl -s "$API_URL")

echo "$TASKS_JSON" | jq -c '.[] | select(.completed=="0" or .notes==null or .notes=="") | {id, task_name, completed}' | while read -r row; do
    id=$(echo "$row" | jq -r '.id')
    name=$(echo "$row" | jq -r '.task_name')
    completed=$(echo "$row" | jq -r '.completed')
    search=$(echo "$name" | sed 's/[].*^$\/]/\\&/g')
    commit=$(git log --grep="$search" -n 1 --pretty=format:%h || true)
    if [ -n "$commit" ]; then
        note="Completed in commit $commit"
        # Update note
        curl -s -X POST "$API_URL" -d "action=update_notes&task_id=$id&notes=$(echo $note | sed 's/ /%20/g')" >/dev/null
        # Mark complete if not already
        if [ "$completed" = "0" ]; then
            curl -s -X POST "$API_URL" -d "action=update&task_id=$id&completed=1" >/dev/null
        fi
        echo "Updated task $id with commit $commit"
    else
        echo "No matching commit found for task $id - $name" >&2
    fi

done
