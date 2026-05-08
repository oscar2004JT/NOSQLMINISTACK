from mercado import DynamoDbMercadoRepository, MercadoService
from mercado.config import get_logger
from mercado.responses import error_response, json_response, parse_json_body


LOGGER = get_logger(__name__)


def main(event, _context):
    LOGGER.info("Processing create order request: %s", event.get("pathParameters"))

    try:
        user_id = event["pathParameters"]["userId"]
        payload = parse_json_body(event)
        service = MercadoService(DynamoDbMercadoRepository())
        return json_response(201, service.create_order(user_id, payload))
    except Exception as error:  # noqa: BLE001
        LOGGER.exception("Unhandled error in create_order lambda")
        return error_response(error)
