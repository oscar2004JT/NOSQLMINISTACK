from aws_cdk import CfnOutput, RemovalPolicy, Stack
from aws_cdk import aws_dynamodb as dynamodb
from constructs import Construct


def _table_safe_name(project_name: str) -> str:
    return project_name.lower().replace("_", "-")


class PersistenceStack(Stack):
    """Persistence resources for the local ecommerce domain."""

    def __init__(self, scope: Construct, construct_id: str, project_name: str, **kwargs):
        super().__init__(scope, construct_id, **kwargs)

        self.orders_table = dynamodb.Table(
            self,
            "OrdersTable",
            table_name=f"{_table_safe_name(project_name)}-orders",
            partition_key=dynamodb.Attribute(
                name="PK",
                type=dynamodb.AttributeType.STRING,
            ),
            sort_key=dynamodb.Attribute(
                name="SK",
                type=dynamodb.AttributeType.STRING,
            ),
            billing_mode=dynamodb.BillingMode.PAY_PER_REQUEST,
            removal_policy=RemovalPolicy.DESTROY,
        )

        self.orders_table.add_global_secondary_index(
            index_name="TipoIndex",
            partition_key=dynamodb.Attribute(
                name="Tipo",
                type=dynamodb.AttributeType.STRING,
            ),
            sort_key=dynamodb.Attribute(
                name="PK",
                type=dynamodb.AttributeType.STRING,
            ),
            projection_type=dynamodb.ProjectionType.ALL,
        )

        self.products_table = dynamodb.Table(
            self,
            "ProductsTable",
            table_name="productos",
            partition_key=dynamodb.Attribute(
                name="PK",
                type=dynamodb.AttributeType.STRING,
            ),
            sort_key=dynamodb.Attribute(
                name="SK",
                type=dynamodb.AttributeType.STRING,
            ),
            billing_mode=dynamodb.BillingMode.PAY_PER_REQUEST,
            removal_policy=RemovalPolicy.DESTROY,
        )

        self.products_table.add_global_secondary_index(
            index_name="TipoIndex",
            partition_key=dynamodb.Attribute(
                name="Tipo",
                type=dynamodb.AttributeType.STRING,
            ),
            sort_key=dynamodb.Attribute(
                name="PK",
                type=dynamodb.AttributeType.STRING,
            ),
            projection_type=dynamodb.ProjectionType.ALL,
        )

        CfnOutput(
            self,
            "OrdersTableName",
            value=self.orders_table.table_name,
            description="DynamoDB table used by the local ecommerce workload.",
        )

        CfnOutput(
            self,
            "ProductsTableName",
            value=self.products_table.table_name,
            description="DynamoDB single-table catalog named productos.",
        )
