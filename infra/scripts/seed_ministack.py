#!/usr/bin/env python3
import json
import os
import time
from pathlib import Path

import boto3
from botocore.exceptions import ClientError


def main() -> None:
    table_name = os.getenv("DYNAMODB_TABLE", "MiMercadoLocal")
    region = os.getenv("AWS_DEFAULT_REGION", "us-east-1")
    endpoint_url = os.getenv("DYNAMODB_ENDPOINT", "http://ministack:4566")

    dynamodb = boto3.resource(
        "dynamodb",
        region_name=region,
        endpoint_url=endpoint_url,
        aws_access_key_id=os.getenv("AWS_ACCESS_KEY_ID", "test"),
        aws_secret_access_key=os.getenv("AWS_SECRET_ACCESS_KEY", "test"),
    )

    table = ensure_table(dynamodb, table_name)
    seed_items(table)


def ensure_table(dynamodb, table_name: str):
    table = dynamodb.Table(table_name)

    try:
        table.load()
        print(f"[seed] Table '{table_name}' already exists.")
        return table
    except ClientError as error:
        code = error.response.get("Error", {}).get("Code")
        if code != "ResourceNotFoundException":
            raise

    print(f"[seed] Creating table '{table_name}'...")
    table = dynamodb.create_table(
        TableName=table_name,
        KeySchema=[
            {"AttributeName": "PK", "KeyType": "HASH"},
            {"AttributeName": "SK", "KeyType": "RANGE"},
        ],
        AttributeDefinitions=[
            {"AttributeName": "PK", "AttributeType": "S"},
            {"AttributeName": "SK", "AttributeType": "S"},
        ],
        BillingMode="PAY_PER_REQUEST",
    )

    while True:
        table.load()
        if table.table_status == "ACTIVE":
            break
        time.sleep(1)

    print(f"[seed] Table '{table_name}' created.")
    return table


def seed_items(table) -> None:
    seed_path = Path("/workspace/app/data/mercado_seed.json")
    items = json.loads(seed_path.read_text(encoding="utf-8"))

    with table.batch_writer(overwrite_by_pkeys=["PK", "SK"]) as batch:
        for item in items:
            batch.put_item(Item=item)

    print(f"[seed] Seeded {len(items)} items into '{table.table_name}'.")


if __name__ == "__main__":
    main()
