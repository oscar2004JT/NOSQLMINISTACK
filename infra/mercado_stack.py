import os

from aws_cdk import (
    CfnOutput,
    CustomResource,
    Duration,
    Fn,
    RemovalPolicy,
    Stack,
    aws_apigateway as apigateway,
    aws_dynamodb as dynamodb,
    aws_lambda as lambda_,
    aws_logs as logs,
    custom_resources as cr,
)
from constructs import Construct


class MercadoMiniStack(Stack):
    def __init__(self, scope: Construct, construct_id: str, **kwargs) -> None:
        super().__init__(scope, construct_id, **kwargs)

        stage_name = os.getenv("DEPLOY_STAGE", "local")
        table_name = os.getenv("DYNAMODB_TABLE", "MiMercadoLocal")
        log_level = os.getenv("LOG_LEVEL", "INFO")
        use_aws_emulator = os.getenv("USE_AWS_EMULATOR", "true").lower()
        repository_root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        emulator_host = os.getenv("AWS_EMULATOR_HOST", "ministack")
        emulator_endpoint = f"http://{emulator_host}:4566"

        shared_layer = lambda_.LayerVersion(
            self,
            "MercadoSharedLayer",
            code=lambda_.Code.from_asset(os.path.join(repository_root, "app")),
            compatible_runtimes=[lambda_.Runtime.PYTHON_3_12],
            description="Shared Mercado domain, repository and utilities for local serverless lambdas",
        )

        table = dynamodb.Table(
            self,
            "MercadoTable",
            table_name=table_name,
            partition_key=dynamodb.Attribute(name="PK", type=dynamodb.AttributeType.STRING),
            sort_key=dynamodb.Attribute(name="SK", type=dynamodb.AttributeType.STRING),
            billing_mode=dynamodb.BillingMode.PAY_PER_REQUEST,
            point_in_time_recovery=False,
            removal_policy=RemovalPolicy.DESTROY,
        )

        shared_environment = {
            "APP_AWS_ENDPOINT_URL": emulator_endpoint,
            "APP_AWS_REGION": os.getenv("AWS_DEFAULT_REGION", "us-east-1"),
            "AWS_EMULATOR_HOST": emulator_host,
            "DEPLOY_STAGE": stage_name,
            "APP_DYNAMODB_ENDPOINT": emulator_endpoint,
            "DYNAMODB_TABLE": table.table_name,
            "EDGE_PORT": "4566",
            "LOG_LEVEL": log_level,
            "MINISTACK_HOST": emulator_host,
            "USE_AWS_EMULATOR": use_aws_emulator,
        }

        get_user_fn = self._create_lambda(
            "GetUserFunction",
            "get-user",
            "get_user",
            repository_root,
            shared_layer,
            shared_environment,
        )
        get_orders_fn = self._create_lambda(
            "GetOrdersFunction",
            "get-orders",
            "get_orders",
            repository_root,
            shared_layer,
            shared_environment,
        )
        get_order_detail_fn = self._create_lambda(
            "GetOrderDetailFunction",
            "get-order-detail",
            "get_order_detail",
            repository_root,
            shared_layer,
            shared_environment,
        )
        create_order_fn = self._create_lambda(
            "CreateOrderFunction",
            "create-order",
            "create_order",
            repository_root,
            shared_layer,
            shared_environment,
        )
        seed_demo_fn = self._create_lambda(
            "SeedDemoFunction",
            "seed-demo",
            "seed_demo",
            repository_root,
            shared_layer,
            shared_environment,
            timeout=Duration.seconds(60),
        )

        table.grant_read_data(get_user_fn)
        table.grant_read_data(get_orders_fn)
        table.grant_read_data(get_order_detail_fn)
        table.grant_read_write_data(create_order_fn)
        table.grant_read_write_data(seed_demo_fn)

        api_access_logs = logs.LogGroup(
            self,
            "MercadoApiAccessLogs",
            retention=logs.RetentionDays.ONE_WEEK,
            removal_policy=RemovalPolicy.DESTROY,
        )

        api = apigateway.RestApi(
            self,
            "MercadoApi",
            rest_api_name=f"mi-mercado-{stage_name}",
            deploy_options=apigateway.StageOptions(
                stage_name=stage_name,
                access_log_destination=apigateway.LogGroupLogDestination(api_access_logs),
                access_log_format=apigateway.AccessLogFormat.json_with_standard_fields(
                    caller=True,
                    http_method=True,
                    ip=True,
                    protocol=True,
                    request_time=True,
                    resource_path=True,
                    response_length=True,
                    status=True,
                    user=True,
                ),
                data_trace_enabled=True,
                logging_level=apigateway.MethodLoggingLevel.INFO,
                metrics_enabled=True,
            ),
            default_cors_preflight_options=apigateway.CorsOptions(
                allow_origins=apigateway.Cors.ALL_ORIGINS,
                allow_headers=["Content-Type", "Authorization"],
                allow_methods=["GET", "POST", "OPTIONS"],
            ),
        )

        usuarios = api.root.add_resource("usuarios")
        usuario = usuarios.add_resource("{userId}")
        pedidos = usuario.add_resource("pedidos")
        pedido = pedidos.add_resource("{orderId}")

        usuario.add_method("GET", apigateway.LambdaIntegration(get_user_fn))
        pedidos.add_method("GET", apigateway.LambdaIntegration(get_orders_fn))
        pedidos.add_method("POST", apigateway.LambdaIntegration(create_order_fn))
        pedido.add_method("GET", apigateway.LambdaIntegration(get_order_detail_fn))

        seed_provider = cr.Provider(
            self,
            "SeedDemoProvider",
            on_event_handler=seed_demo_fn,
        )

        CustomResource(
            self,
            "SeedDemoData",
            service_token=seed_provider.service_token,
            properties={
                "SeedVersion": "2026-05-07",
                "TableName": table.table_name,
            },
        )

        local_api_url = Fn.join(
            "",
            [
                "http://localhost:4566/restapis/",
                api.rest_api_id,
                "/",
                stage_name,
                "/_user_request_",
            ],
        )

        CfnOutput(self, "ApiUrlLocal", value=local_api_url)
        CfnOutput(self, "ApiStage", value=stage_name)
        CfnOutput(self, "DynamoTableName", value=table.table_name)
        CfnOutput(self, "LaravelServerlessApiBaseUrl", value=local_api_url)
        CfnOutput(self, "CreateOrderFunctionName", value=create_order_fn.function_name)
        CfnOutput(self, "GetUserFunctionName", value=get_user_fn.function_name)

    def _create_lambda(
        self,
        construct_id: str,
        function_suffix: str,
        asset_folder: str,
        repository_root: str,
        shared_layer: lambda_.ILayerVersion,
        environment: dict[str, str],
        timeout: Duration = Duration.seconds(30),
    ) -> lambda_.Function:
        stage_name = os.getenv("DEPLOY_STAGE", "local")

        return lambda_.Function(
            self,
            construct_id,
            function_name=f"mi-mercado-{function_suffix}-{stage_name}",
            runtime=lambda_.Runtime.PYTHON_3_12,
            handler="handler.main",
            code=lambda_.Code.from_asset(os.path.join(repository_root, "lambdas", asset_folder)),
            memory_size=256,
            timeout=timeout,
            layers=[shared_layer],
            environment=environment,
        )
