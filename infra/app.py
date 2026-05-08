#!/usr/bin/env python3
import os

import aws_cdk as cdk

from mercado_stack import MercadoMiniStack


app = cdk.App()

MercadoMiniStack(
    app,
    os.getenv("STACK_NAME", "MercadoMiniStack"),
    env=cdk.Environment(
        account=os.getenv("CDK_DEFAULT_ACCOUNT", "000000000000"),
        region=os.getenv("CDK_DEFAULT_REGION", os.getenv("AWS_DEFAULT_REGION", "us-east-1")),
    ),
)

app.synth()
