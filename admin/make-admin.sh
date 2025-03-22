#!/usr/bin/env bash
#
# Script to make an existing user an admin in the GiveHub database.
# Uses mongosh which should already be installed.
#

# Default settings
HOST="localhost"
PORT="27017"
DB="givehub"

# Function to show usage
show_usage() {
  echo "Usage: $0 <username> [options]"
  echo ""
  echo "Options:"
  echo "  -h, --host HOST    MongoDB host (default: localhost)"
  echo "  -p, --port PORT    MongoDB port (default: 27017)"
  echo "  -d, --db DB        Database name (default: givehub)"
  echo "  --help             Show this help message"
  echo ""
  echo "Example: $0 johndoe --host 127.0.0.1 --db mydb"
  exit 1
}

# Check for help flag or no arguments
if [[ "$1" == "--help" || $# -eq 0 ]]; then
  show_usage
fi

# Get the username from the first argument
USERNAME="$1"
shift

# Parse remaining arguments
while [[ $# -gt 0 ]]; do
  case "$1" in
    -h|--host)
      HOST="$2"
      shift 2
      ;;
    -p|--port)
      PORT="$2"
      shift 2
      ;;
    -d|--db)
      DB="$2"
      shift 2
      ;;
    *)
      echo "Unknown option: $1"
      show_usage
      ;;
  esac
done

# Create MongoDB command to add admin role
MONGO_COMMAND="
const user = db.users.findOne({username: '$USERNAME'});
if (!user) {
  print('User $USERNAME not found.');
  quit();
}

const roles = user.roles || ['user'];
if (!roles.includes('admin')) {
  roles.push('admin');
  
  const result = db.users.updateOne(
    {_id: user._id},
    {\$set: {roles: roles}}
  );
  
  if (result.matchedCount > 0) {
    print('User $USERNAME is now an admin.');
  } else {
    print('Failed to update user roles.');
  }
} else {
  print('User $USERNAME is already an admin.');
}
"

# Run the mongosh command
mongosh --host "$HOST" --port "$PORT" "$DB" --eval "$MONGO_COMMAND"
