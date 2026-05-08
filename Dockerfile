FROM python:3.12-slim

ENV DEBIAN_FRONTEND=noninteractive \
    PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1 \
    NPM_CONFIG_UPDATE_NOTIFIER=false

RUN apt-get update && apt-get install -y --no-install-recommends \
    bash \
    ca-certificates \
    curl \
    git \
    jq \
    nodejs \
    npm \
    unzip \
    zip \
 && npm install -g aws-cdk@2 aws-cdk-local \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /workspace

COPY infra/requirements.txt /tmp/infra-requirements.txt

RUN pip install --upgrade pip \
 && pip install -r /tmp/infra-requirements.txt

CMD ["bash"]
