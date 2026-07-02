#!/usr/bin/env bash
# Builds wordpress-theme/product-reviews.zip ready to upload via WP admin.
# Auto-bumps the theme patch version in style.css so the WP "Update theme"
# flow sees a new release each time you re-run this script.
set -euo pipefail
cd "$(dirname "$0")"

STYLE="product-reviews/style.css"
FUNCTIONS="product-reviews/functions.php"
if [[ -f "$STYLE" ]]; then
  current=$(grep -E '^Version:' "$STYLE" | head -1 | awk '{print $2}')
  if [[ -n "$current" ]]; then
    IFS='.' read -r major minor patch <<<"$current"
    patch=$((patch + 1))
    new="${major}.${minor}.${patch}"
    # macOS/Linux compatible in-place edit
    sed -i.bak -E "s/^Version: .*/Version: ${new}/" "$STYLE" && rm -f "$STYLE.bak"
    if [[ -f "$FUNCTIONS" ]]; then
      sed -i.bak -E "s/define\( 'PR_VERSION', '[^']+' \);/define( 'PR_VERSION', '${new}' );/" "$FUNCTIONS" && rm -f "$FUNCTIONS.bak"
    fi
    echo "Bumped theme version: ${current} → ${new}"
  fi
fi

rm -f product-reviews.zip
( cd . && zip -rq product-reviews.zip product-reviews -x "product-reviews/.*" )
echo "Built $(pwd)/product-reviews.zip"
