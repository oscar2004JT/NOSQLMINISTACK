class MercadoError(Exception):
    status_code = 400


class ValidationError(MercadoError):
    status_code = 400


class NotFoundError(MercadoError):
    status_code = 404
