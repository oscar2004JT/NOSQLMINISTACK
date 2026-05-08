#!/usr/bin/env bash
set -euo pipefail

cd /workspace/infra

echo "[cdk] Waiting for MiniStack on http://ministack:4566 ..."
until curl -sf http://ministack:4566/_ministack/health >/dev/null; do
  sleep 2
done

echo "[cdk] Bootstrapping CDK assets for MiniStack ..."
cdklocal bootstrap "aws://${CDK_DEFAULT_ACCOUNT:-000000000000}/${CDK_DEFAULT_REGION:-us-east-1}" || true

echo "[cdk] Environment ready."
echo "[cdk] Enter the container with: docker compose exec cdk bash"
echo "[cdk] Deploy with: cd /workspace/infra && cdklocal deploy"

tail -f /dev/null
