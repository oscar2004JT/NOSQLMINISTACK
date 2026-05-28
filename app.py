import os

import aws_cdk as cdk

from infra.api_stack import ApiStack
from infra.core_stack import CoreStack
from infra.persistence_stack import PersistenceStack


def env_or_default(name: str, default: str) -> str:
    value = os.getenv(name)
    return value if value else default


app = cdk.App()

project_name = app.node.try_get_context("project_name") or env_or_default(
    "PROJECT_NAME", "ecommerce-local"
)

environment = cdk.Environment(
    account=env_or_default("CDK_DEFAULT_ACCOUNT", "000000000000"),
    region=env_or_default("CDK_DEFAULT_REGION", env_or_default("AWS_REGION", "us-east-1")),
)

core_stack = CoreStack(
    app,
    "CoreStack",
    project_name=project_name,
    env=environment,
)

persistence_stack = PersistenceStack(
    app,
    "PersistenceStack",
    project_name=project_name,
    env=environment,
)

api_stack = ApiStack(
    app,
    "ApiStack",
    project_name=project_name,
    table=persistence_stack.orders_table,
    products_table=persistence_stack.products_table,
    bucket=core_stack.assets_bucket,
    env=environment,
)

api_stack.add_dependency(core_stack)
api_stack.add_dependency(persistence_stack)

app.synth()
