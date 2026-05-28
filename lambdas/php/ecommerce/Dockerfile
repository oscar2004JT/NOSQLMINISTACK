FROM php:8.3-cli-alpine

RUN apk add --no-cache curl

WORKDIR /var/task

COPY lambdas/php/ecommerce/index.php /var/task/index.php
COPY src /var/task/src
COPY public /var/task/public
COPY infra/seeds /var/task/infra/seeds
COPY lambdas/php/ecommerce/bootstrap /usr/local/bin/bootstrap

RUN chmod 755 /usr/local/bin/bootstrap

ENTRYPOINT ["/usr/local/bin/bootstrap"]
CMD ["index.php"]
