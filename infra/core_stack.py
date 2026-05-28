from aws_cdk import CfnOutput, RemovalPolicy, Stack
from aws_cdk import aws_s3 as s3
from constructs import Construct


def _bucket_safe_name(project_name: str) -> str:
    return project_name.lower().replace("_", "-")


class CoreStack(Stack):
    """Shared platform resources for the local AWS simulation."""

    def __init__(self, scope: Construct, construct_id: str, project_name: str, **kwargs):
        super().__init__(scope, construct_id, **kwargs)

        bucket_name = f"{_bucket_safe_name(project_name)}-assets-local"

        self.assets_bucket = s3.Bucket(
            self,
            "AssetsBucket",
            bucket_name=bucket_name,
            block_public_access=s3.BlockPublicAccess.BLOCK_ALL,
            encryption=s3.BucketEncryption.S3_MANAGED,
            removal_policy=RemovalPolicy.DESTROY,
            versioned=True,
        )

        CfnOutput(
            self,
            "AssetsBucketName",
            value=self.assets_bucket.bucket_name,
            description="S3 bucket used by the local ecommerce workload.",
        )
