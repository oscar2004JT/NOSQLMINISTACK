FROM python:3.12-slim-bookworm

ARG DEBIAN_FRONTEND=noninteractive

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        bash \
        ca-certificates \
        curl \
        docker.io \
        git \
        jq \
        make \
        nodejs \
        npm \
        php-cli \
        php-curl \
        php-mbstring \
        php-xml \
        php-zip \
        unzip \
        zip \
    && rm -rf /var/lib/apt/lists/*

RUN npm install -g aws-cdk@2.160.0 aws-cdk-local

RUN python -m pip install --upgrade pip \
    && python -m pip install awscli awscli-local

COPY requirements.txt /tmp/requirements.txt
RUN python -m pip install -r /tmp/requirements.txt

WORKDIR /workspace

CMD ["sleep", "infinity"]
