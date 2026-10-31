#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
exec php -S 127.0.0.1:8080 -t public public/index.php
