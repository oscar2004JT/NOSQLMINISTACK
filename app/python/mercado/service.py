from datetime import UTC, datetime

from mercado.exceptions import NotFoundError, ValidationError


class MercadoService:
    def __init__(self, repository) -> None:
        self.repository = repository

    def get_user_data(self, user_id: str) -> dict:
        profile = self.repository.get_user_profile(user_id)
        if profile is None:
            raise NotFoundError("Usuario no encontrado.")

        orders = self.repository.get_user_orders(user_id)
        items: list[dict] = []
        for order in orders:
            items.extend(self.repository.get_order_items(user_id, order["order_id"]))

        return {
            "perfil": profile,
            "pedidos": orders,
            "productos": items,
        }

    def get_orders(self, user_id: str) -> dict:
        profile = self.repository.get_user_profile(user_id)
        if profile is None:
            raise NotFoundError("Usuario no encontrado.")

        return {
            "pedidos": self.repository.get_user_orders(user_id),
        }

    def get_order_detail(self, user_id: str, order_id: str) -> dict:
        profile = self.repository.get_user_profile(user_id)
        if profile is None:
            raise NotFoundError("Usuario no encontrado.")

        order = self.repository.get_order(user_id, order_id)
        if order is None:
            raise NotFoundError("Pedido no encontrado.")

        return {
            "pedido": order,
            "items": self.repository.get_order_items(user_id, order_id),
        }

    def create_order(self, user_id: str, payload: dict) -> dict:
        profile = self.repository.get_user_profile(user_id)
        if profile is None:
            raise NotFoundError("Usuario no encontrado.")

        items = payload.get("items")
        if not isinstance(items, list) or not items:
            raise ValidationError("Debes enviar al menos un item en el campo 'items'.")

        order_id = str(payload.get("order_id") or int(datetime.now(UTC).timestamp()))
        estado = str(payload.get("estado") or "Pendiente")
        fecha = str(payload.get("fecha") or datetime.now(UTC).isoformat().replace("+00:00", "Z"))
        direccion = str(
            payload.get("direccion") or (profile["direcciones"][0] if profile["direcciones"] else "")
        )

        if not direccion:
            raise ValidationError("La direccion del pedido es obligatoria.")

        item_records: list[dict] = []
        total = 0

        for index, item in enumerate(items, start=1):
            producto = str(item.get("producto") or "").strip()
            cantidad = int(item.get("cantidad") or 0)
            precio = int(item.get("precio") or 0)

            if not producto:
                raise ValidationError("Cada item debe incluir 'producto'.")
            if cantidad <= 0:
                raise ValidationError("Cada item debe incluir una 'cantidad' mayor que cero.")
            if precio < 0:
                raise ValidationError("Cada item debe incluir un 'precio' valido.")

            item_id = str(item.get("item_id") or index)
            subtotal = cantidad * precio
            total += subtotal

            item_records.append(
                {
                    "PK": f"USER#{user_id}",
                    "SK": f"ORDER#{order_id}#ITEM#{item_id}",
                    "Tipo": "ITEM",
                    "producto": producto,
                    "cantidad": cantidad,
                    "precio": precio,
                    "subtotal": subtotal,
                }
            )

        order_record = {
            "PK": f"USER#{user_id}",
            "SK": f"ORDER#{order_id}",
            "Tipo": "ORDER",
            "estado": estado,
            "fecha": fecha,
            "direccion": direccion,
            "total": total,
        }

        created = self.repository.create_order(user_id, order_record, item_records)
        return {
            "message": "Pedido creado correctamente.",
            **created,
        }
