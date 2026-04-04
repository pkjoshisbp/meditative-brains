#!/bin/bash
# MongoDB User Setup Script
# This script creates/updates MongoDB users for the motivation database
# 
# Usage:
#   1. With MongoDB admin credentials:
#      ./setup_mongodb_users.sh <admin_username> <admin_password>
#   
#   2. Or run the mongo commands manually as MongoDB admin

set -e

ADMIN_USER="${1:-admin}"
ADMIN_PASS="${2}"

if [ -z "$ADMIN_PASS" ]; then
  echo "❌ Error: Admin password required"
  echo ""
  echo "Usage: $0 <admin_username> <admin_password>"
  echo "Example: $0 admin myAdminPassword123"
  echo ""
  echo "Or run these commands manually as MongoDB admin:"
  echo ""
  cat << 'MANUAL_COMMANDS'
mongosh "mongodb://YOUR_ADMIN_USER:YOUR_ADMIN_PASSWORD@127.0.0.1:27017/admin" << 'EOF'

// Switch to motivation database
use motivation

// Create/Update pawan user
try {
  db.createUser({
    user: "pawan",
    pwd: "pragati123..",
    roles: [{ role: "readWrite", db: "motivation" }]
  });
  print("✅ User 'pawan' created");
} catch (e) {
  if (e.code === 51003) {
    db.updateUser("pawan", { pwd: "pragati123.." });
    print("✅ User 'pawan' password updated");
  } else {
    print("❌ Error with 'pawan': " + e.message);
  }
}

// Create/Update pkjoshi user
try {
  db.createUser({
    user: "pkjoshi",
    pwd: "pragati123..",
    roles: [{ role: "readWrite", db: "motivation" }]
  });
  print("✅ User 'pkjoshi' created");
} catch (e) {
  if (e.code === 51003) {
    db.updateUser("pkjoshi", { pwd: "pragati123.." });
    print("✅ User 'pkjoshi' password updated");
  } else {
    print("❌ Error with 'pkjoshi': " + e.message);
  }
}

// Create/Update pawanjoshi user
try {
  db.createUser({
    user: "pawanjoshi",
    pwd: "pragati123..",
    roles: [{ role: "readWrite", db: "motivation" }]
  });
  print("✅ User 'pawanjoshi' created");
} catch (e) {
  if (e.code === 51003) {
    db.updateUser("pawanjoshi", { pwd: "pragati123.." });
    print("✅ User 'pawanjoshi' password updated");
  } else {
    print("❌ Error with 'pawanjoshi': " + e.message);
  }
}

print("\n📋 All users configured with password: pragati123..");
print("   Database: motivation");
print("   Users: pawan, pkjoshi, pawanjoshi");

EOF
MANUAL_COMMANDS
  exit 1
fi

echo "🔧 Setting up MongoDB users..."

# Create a temporary JS file
TMP_SCRIPT=$(mktemp /tmp/mongo_setup_XXXXX.js)
cat > "$TMP_SCRIPT" << 'EOF'
// Switch to motivation database
db = db.getSiblingDB('motivation');

// Create/Update pawan user
try {
  db.createUser({
    user: "pawan",
    pwd: "pragati123..",
    roles: [{ role: "readWrite", db: "motivation" }]
  });
  print("✅ User 'pawan' created successfully");
} catch (e) {
  if (e.code === 51003) {
    db.updateUser("pawan", { pwd: "pragati123.." });
    print("✅ User 'pawan' password updated");
  } else {
    print("❌ Error with 'pawan': " + e.message);
  }
}

// Create/Update pkjoshi user
try {
  db.createUser({
    user: "pkjoshi",
    pwd: "pragati123..",
    roles: [{ role: "readWrite", db: "motivation" }]
  });
  print("✅ User 'pkjoshi' created successfully");
} catch (e) {
  if (e.code === 51003) {
    db.updateUser("pkjoshi", { pwd: "pragati123.." });
    print("✅ User 'pkjoshi' password updated");
  } else {
    print("❌ Error with 'pkjoshi': " + e.message);
  }
}

// Create/Update pawanjoshi user
try {
  db.createUser({
    user: "pawanjoshi",
    pwd: "pragati123..",
    roles: [{ role: "readWrite", db: "motivation" }]
  });
  print("✅ User 'pawanjoshi' created successfully");
} catch (e) {
  if (e.code === 51003) {
    db.updateUser("pawanjoshi", { pwd: "pragati123.." });
    print("✅ User 'pawanjoshi' password updated");
  } else {
    print("❌ Error with 'pawanjoshi': " + e.message);
  }
}

print("\n📋 Summary - All users configured:");
print("   Database: motivation");
print("   Password: pragati123..");
print("   Users:");
print("     - pawan");
print("     - pkjoshi");
print("     - pawanjoshi");
EOF

# Execute the script
echo "Connecting to MongoDB as $ADMIN_USER..."
mongosh "mongodb://${ADMIN_USER}:${ADMIN_PASS}@127.0.0.1:27017/admin" --quiet "$TMP_SCRIPT"

# Clean up
rm -f "$TMP_SCRIPT"

echo ""
echo "✅ Setup complete!"
echo ""
echo "You can now connect using any of these users:"
echo "  mongodb://pawan:pragati123..@127.0.0.1:27017/motivation"
echo "  mongodb://pkjoshi:pragati123..@127.0.0.1:27017/motivation"
echo "  mongodb://pawanjoshi:pragati123..@127.0.0.1:27017/motivation"
