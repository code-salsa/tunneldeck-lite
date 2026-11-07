#!/bin/bash
set -e

DIR="$(cd "$(dirname "$0")" && pwd)"
echo "Building macOS package..."
node "$DIR/builder/build-pkg.js"

