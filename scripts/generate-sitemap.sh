#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

php artisan sitemap:generate
