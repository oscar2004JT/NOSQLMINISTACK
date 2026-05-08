from mercado import DynamoDbMercadoRepository, MercadoService
from mercado.config import get_logger
from mercado.responses import error_response, json_response


LOGGER = get_logger(__name__)


def main(event, _context):
    LOGGER.info("Processing get order detail request: %s", event.get("pathParameters"))

    try:
        path_parameters = event["pathParameters"]
        service = MercadoService(DynamoDbMercadoRepository())
        return json_response(
            200,
            service.get_order_detail(path_parameters["userId"], path_parameters["orderId"]),
        )
    except Exception as error:  # noqa: BLE001
        LOGGER.exception("Unhandled error in get_order_detail lambda")
        return error_response(error)
