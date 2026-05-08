import logging
import os

import boto3


def aws_region() -> str:
    return os.getenv("APP_AWS_REGION", os.getenv("AWS_DEFAULT_REGION", "us-east-1"))


def table_name() -> str:
    return os.getenv("DYNAMODB_TABLE", "MiMercadoLocal")


def use_aws_emulator() -> bool:
    return os.getenv("USE_AWS_EMULATOR", os.getenv("USE_LOCALSTACK", "true")).lower() in {
        "1",
        "true",
        "yes",
        "on",
    }


def resolve_aws_endpoint() -> str | None:
    explicit_endpoint = (
        os.getenv("APP_AWS_ENDPOINT_URL")
        or os.getenv("APP_DYNAMODB_ENDPOINT")
        or os.getenv("AWS_ENDPOINT_URL")
        or os.getenv("DYNAMODB_ENDPOINT")
    )
    if explicit_endpoint:
        return explicit_endpoint

    if not use_aws_emulator():
        return None

    emulator_host = (
        os.getenv("AWS_EMULATOR_HOST")
        or os.getenv("MINISTACK_HOST")
        or os.getenv("LOCALSTACK_HOST")
        or "ministack"
    )
    return f"http://{emulator_host}:{os.getenv('EDGE_PORT', '4566')}"


def get_logger(name: str) -> logging.Logger:
    log_level = os.getenv("LOG_LEVEL", "INFO").upper()
    logging.basicConfig(level=getattr(logging, log_level, logging.INFO))
    return logging.getLogger(name)


def build_dynamodb_resource():
    session = boto3.session.Session(region_name=aws_region())
    resource_kwargs: dict[str, str] = {}
    endpoint_url = resolve_aws_endpoint()

    if endpoint_url:
        resource_kwargs["endpoint_url"] = endpoint_url

    if use_aws_emulator():
        resource_kwargs["aws_access_key_id"] = os.getenv("AWS_ACCESS_KEY_ID", "test")
        resource_kwargs["aws_secret_access_key"] = os.getenv("AWS_SECRET_ACCESS_KEY", "test")

    return session.resource("dynamodb", **resource_kwargs)
