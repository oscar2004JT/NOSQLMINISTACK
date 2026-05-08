import time

from mercado.config import build_dynamodb_resource, get_logger, table_name
from mercado.repository import DynamoDbMercadoRepository
from mercado.sample_data import load_items


LOGGER = get_logger(__name__)


def main(event, _context):
    LOGGER.info("Received custom resource event: %s", event)

    if event.get("RequestType") == "Delete":
        return {
            "PhysicalResourceId": f"{table_name()}-seed",
            "Status": "SKIPPED",
        }

    dynamodb_resource = build_dynamodb_resource()
    table = dynamodb_resource.Table(table_name())
    table.wait_until_exists()

    # MiniStack may expose the table before it is fully queryable.
    time.sleep(2)

    repository = DynamoDbMercadoRepository(table=table)
    seeded = repository.seed_items(load_items())

    return {
        "PhysicalResourceId": f"{table_name()}-seed",
        "Data": {
            "itemsSeeded": seeded,
        },
    }
