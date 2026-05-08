from decimal import Decimal

from boto3.dynamodb.conditions import Key

from mercado.config import build_dynamodb_resource, table_name


class DynamoDbMercadoRepository:
    def __init__(self, table=None) -> None:
        dynamodb_table = table or build_dynamodb_resource().Table(table_name())
        self.table = dynamodb_table

    def get_user_profile(self, user_id: str) -> dict | None:
        items = self._query_user_partition(user_id)
        for item in items:
            if item.get("Tipo") == "USER":
                return {
                    "user_id": user_id,
                    "nombre": item.get("nombre"),
                    "email": item.get("email"),
                    "direcciones": item.get("direcciones", []),
                    "pagos": item.get("pagos", []),
                }
        return None

    def get_user_orders(self, user_id: str) -> list[dict]:
        orders = [
            {
                "user_id": user_id,
                "order_id": record["SK"].replace("ORDER#", ""),
                "estado": record.get("estado"),
                "fecha": record.get("fecha"),
                "direccion": record.get("direccion"),
                "total": self._to_int(record.get("total", 0)),
            }
            for record in self._query_user_partition(user_id)
            if record.get("Tipo") == "ORDER"
        ]
        return sorted(orders, key=lambda order: order["order_id"])

    def get_order(self, user_id: str, order_id: str) -> dict | None:
        response = self.table.get_item(
            Key={
                "PK": f"USER#{user_id}",
                "SK": f"ORDER#{order_id}",
            }
        )
        item = response.get("Item")
        if not item:
            return None

        return {
            "user_id": user_id,
            "order_id": order_id,
            "estado": item.get("estado"),
            "fecha": item.get("fecha"),
            "direccion": item.get("direccion"),
            "total": self._to_int(item.get("total", 0)),
        }

    def get_order_items(self, user_id: str, order_id: str) -> list[dict]:
        response = self.table.query(
            KeyConditionExpression=Key("PK").eq(f"USER#{user_id}")
            & Key("SK").begins_with(f"ORDER#{order_id}#ITEM#")
        )
        items = [
            {
                "user_id": user_id,
                "order_id": order_id,
                "item_id": record["SK"].split("#")[-1],
                "producto": record.get("producto"),
                "cantidad": self._to_int(record.get("cantidad", 0)),
                "precio": self._to_int(record.get("precio", 0)),
                "subtotal": self._to_int(record.get("subtotal", 0)),
            }
            for record in response.get("Items", [])
        ]
        return sorted(items, key=lambda item: item["item_id"])

    def create_order(self, user_id: str, order_record: dict, item_records: list[dict]) -> dict:
        with self.table.batch_writer(overwrite_by_pkeys=["PK", "SK"]) as batch:
            batch.put_item(Item=order_record)
            for item in item_records:
                batch.put_item(Item=item)

        return {
            "pedido": self.get_order(user_id, order_record["SK"].replace("ORDER#", "")),
            "items": self.get_order_items(user_id, order_record["SK"].replace("ORDER#", "")),
        }

    def seed_items(self, items: list[dict]) -> int:
        with self.table.batch_writer(overwrite_by_pkeys=["PK", "SK"]) as batch:
            for item in items:
                batch.put_item(Item=item)

        return len(items)

    def _query_user_partition(self, user_id: str) -> list[dict]:
        response = self.table.query(KeyConditionExpression=Key("PK").eq(f"USER#{user_id}"))
        return response.get("Items", [])

    @staticmethod
    def _to_int(value) -> int:
        if isinstance(value, Decimal):
            return int(value)
        return int(value or 0)
