FROM public.ecr.aws/lambda/provided:al2023

RUN dnf install -y php-cli curl-minimal \
    && dnf clean all \
    && rm -rf /var/cache/dnf
