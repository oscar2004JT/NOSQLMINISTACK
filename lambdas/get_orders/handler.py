from mercado import DynamoDbMercadoRepository, MercadoService
from mercado.config import get_logger
from mercado.responses import error_response, json_response


LOGGER = get_logger(__name__)


def main(event, _context):
    LOGGER.info("Processing get orders request: %s", event.get("pathParameters"))

    try:
        user_id = event["pathParameters"]["userId"]
        service = MercadoService(DynamoDbMercadoRepository())
        return json_response(200, service.get_orders(user_id))
    except Exception as error:  # noqa: BLE001
        LOGGER.exception("Unhandled error in get_orders lambda")
        return error_response(error)
