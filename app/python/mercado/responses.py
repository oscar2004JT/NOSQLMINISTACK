import base64
import json

from mercado.exceptions import MercadoError, ValidationError


def json_response(status_code: int, body: dict, headers: dict | None = None) -> dict:
    response_headers = {
        "Access-Control-Allow-Origin": "*",
        "Access-Control-Allow-Headers": "Content-Type,Authorization",
        "Access-Control-Allow-Methods": "GET,POST,OPTIONS",
        "Content-Type": "application/json",
    }
    if headers:
        response_headers.update(headers)

    return {
        "statusCode": status_code,
        "headers": response_headers,
        "body": json.dumps(body, ensure_ascii=False),
    }


def parse_json_body(event: dict) -> dict:
    raw_body = event.get("body") or "{}"

    if isinstance(raw_body, dict):
        return raw_body

    if event.get("isBase64Encoded"):
        raw_body = base64.b64decode(raw_body).decode("utf-8")

    try:
        return json.loads(raw_body)
    except json.JSONDecodeError as error:
        raise ValidationError("El cuerpo de la solicitud debe ser JSON valido.") from error


def error_response(error: Exception) -> dict:
    if isinstance(error, MercadoError):
        return json_response(error.status_code, {"error": str(error)})

    return json_response(500, {"error": "Error interno no controlado."})
