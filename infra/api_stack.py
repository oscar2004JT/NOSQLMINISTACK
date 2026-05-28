import os

from aws_cdk import CfnOutput, Duration, Stack
from aws_cdk import aws_apigateway as apigateway
from aws_cdk import aws_dynamodb as dynamodb
from aws_cdk import aws_lambda as lambda_
from aws_cdk import aws_s3 as s3
from constructs import Construct


class ApiStack(Stack):
    """API Gateway and PHP Lambda running on a ZIP custom runtime."""

    def __init__(
        self,
        scope: Construct,
        construct_id: str,
        project_name: str,
        table: dynamodb.ITable,
        products_table: dynamodb.ITable,
        bucket: s3.IBucket,
        **kwargs,
    ):
        super().__init__(scope, construct_id, **kwargs)

        function_name = f"{project_name}-php-ecommerce"

        self.ecommerce_lambda = lambda_.Function(
            self,
            "EcommercePhpLambda",
            function_name=function_name,
            runtime=lambda_.Runtime.PROVIDED_AL2023,
            handler="lambdas/php/ecommerce/index.php",
            code=lambda_.Code.from_asset(
                os.getcwd(),
                exclude=[
                    ".git",
                    ".devcontainer",
                    "cdk.out",
                    "docker",
                    "storage/mercado_data.json",
                    "__pycache__",
                    "*.pyc",
                ],
            ),
            architecture=lambda_.Architecture.X86_64,
            memory_size=256,
            timeout=Duration.seconds(15),
            environment={
                "APP_ENV": "local",
                "TABLE_NAME": table.table_name,
                "USERS_TABLE_NAME": table.table_name,
                "PRODUCTS_TABLE_NAME": products_table.table_name,
                "BUCKET_NAME": bucket.bucket_name,
                "AWS_ENDPOINT_URL": os.getenv(
                    "LOCALSTACK_ENDPOINT", "http://localstack:4566"
                ),
                "DYNAMODB_ENDPOINT": os.getenv(
                    "LOCALSTACK_ENDPOINT", "http://localstack:4566"
                ),
                "S3_ENDPOINT": os.getenv(
                    "LOCALSTACK_ENDPOINT", "http://localstack:4566"
                ),
                "REDIS_HOST": os.getenv("REDIS_HOST", "redis"),
                "REDIS_PORT": os.getenv("REDIS_PORT", "6379"),
            },
        )

        table.grant_read_write_data(self.ecommerce_lambda)
        products_table.grant_read_write_data(self.ecommerce_lambda)
        bucket.grant_read_write(self.ecommerce_lambda)

        integration = apigateway.LambdaIntegration(
            self.ecommerce_lambda,
            proxy=True,
        )

        self.api = apigateway.RestApi(
            self,
            "EcommerceApi",
            rest_api_name=f"{project_name}-api",
            description="LocalStack API Gateway fronting a PHP Lambda custom runtime.",
            cloud_watch_role=False,
            endpoint_types=[apigateway.EndpointType.REGIONAL],
            deploy_options=apigateway.StageOptions(
                stage_name="local",
                metrics_enabled=False,
                logging_level=apigateway.MethodLoggingLevel.OFF,
            ),
            default_cors_preflight_options=apigateway.CorsOptions(
                allow_origins=apigateway.Cors.ALL_ORIGINS,
                allow_methods=apigateway.Cors.ALL_METHODS,
                allow_headers=["Content-Type", "Authorization", "X-Amz-Date"],
            ),
        )

        self.api.root.add_method("ANY", integration)
        self.api.root.add_resource("{proxy+}").add_method("ANY", integration)

        CfnOutput(
            self,
            "EcommerceLambdaName",
            value=self.ecommerce_lambda.function_name,
            description="PHP Lambda function name.",
        )

        CfnOutput(
            self,
            "EcommerceApiLocalUrl",
            value=(
                "http://localhost:4566/restapis/"
                f"{self.api.rest_api_id}/local/_user_request_/ecommerce"
            ),
            description="LocalStack URL for the ecommerce endpoint.",
        )
