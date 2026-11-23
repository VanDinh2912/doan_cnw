#!/bin/sh
# Cai dat git hook pre-push chan push len main (lop cuc bo).
# Chay mot lan tu goc repo:  sh scripts/git-hooks/cai-dat.sh
# Hoac copy thu cong: cp scripts/git-hooks/pre-push .git/hooks/pre-push
set -e
ROOT=$(git rev-parse --show-toplevel)
mkdir -p "$ROOT/.git/hooks"
cp "$ROOT/scripts/git-hooks/pre-push" "$ROOT/.git/hooks/pre-push"
chmod +x "$ROOT/.git/hooks/pre-push"
echo "Da cai .git/hooks/pre-push — push len main se bi chan tru khi ALLOW_PUSH_MAIN=1."
echo "Luu y: --no-verify van vuot duoc; lop chac chan la branch protection GitHub."
