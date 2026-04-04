#!/bin/bash
# Test MongoDB User Connections
# This script tests if the MongoDB users can connect successfully

echo "🧪 Testing MongoDB User Connections"
echo "=================================="
echo ""

# Test pawan user
echo "Testing 'pawan' user..."
if mongosh "mongodb://pawan:pragati123..@127.0.0.1:27017/motivation" --quiet --eval "db.runCommand({connectionStatus: 1})" 2>&1 | grep -q '"ok" : 1'; then
  echo "✅ pawan: Connected successfully"
else
  echo "❌ pawan: Connection failed"
fi

# Test pkjoshi user
echo "Testing 'pkjoshi' user..."
if mongosh "mongodb://pkjoshi:pragati123..@127.0.0.1:27017/motivation" --quiet --eval "db.runCommand({connectionStatus: 1})" 2>&1 | grep -q '"ok" : 1'; then
  echo "✅ pkjoshi: Connected successfully"
else
  echo "❌ pkjoshi: Connection failed (may need to be created)"
fi

# Test pawanjoshi user
echo "Testing 'pawanjoshi' user..."
if mongosh "mongodb://pawanjoshi:pragati123..@127.0.0.1:27017/motivation" --quiet --eval "db.runCommand({connectionStatus: 1})" 2>&1 | grep -q '"ok" : 1'; then
  echo "✅ pawanjoshi: Connected successfully"
else
  echo "❌ pawanjoshi: Connection failed (may need to be created)"
fi

echo ""
echo "Current working user: pawan"
echo "To create missing users, run: ./setup_mongodb_users.sh <admin_user> <admin_password>"
