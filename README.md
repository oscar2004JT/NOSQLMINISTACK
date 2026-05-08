# Mi Mercado Global Monorepo Serverless Local con MiniStack

Este repositorio reutiliza la aplicacion actual y la extiende a una arquitectura serverless local basada en Docker, MiniStack y AWS CDK con Python.

No se creo un proyecto nuevo desde cero. La app existente en `laravel_app/` se conserva como frontend y referencia funcional del dominio, mientras que la infraestructura serverless vive en el mismo monorepo.

## Arquitectura final

- `laravel_app/`
  Aplicacion Laravel existente. Sigue funcionando como frontend y como app legacy mientras migras por etapas.
- `infra/`
  Infraestructura como codigo con AWS CDK en Python.
- `lambdas/`
  Handlers Lambda desacoplados, uno por endpoint.
- `app/`
  Capa compartida para Lambdas. Se publica como Lambda Layer y contiene repositorio, servicio, respuestas HTTP y datos demo compartidos.

## Estructura recomendada

```text
.
├── Dockerfile
├── docker-compose.yml
├── infra/
│   ├── app.py
│   ├── cdk.json
│   ├── mercado_stack.py
│   ├── requirements.txt
│   └── scripts/
│       └── start-cdk.sh
├── lambdas/
│   ├── create_order/
│   ├── get_order_detail/
│   ├── get_orders/
│   ├── get_user/
│   └── seed_demo/
├── app/
│   ├── data/
│   │   └── mercado_seed.json
│   └── python/
│       └── mercado/
└── laravel_app/
```

## Que se reutilizo del proyecto actual

- El modelo de datos actual basado en `PK` y `SK`
- Los endpoints existentes:
  - `GET /usuarios/{userId}`
  - `GET /usuarios/{userId}/pedidos`
  - `GET /usuarios/{userId}/pedidos/{orderId}`
- Los datos demo del proyecto
- El frontend Laravel actual, que ahora puede apuntar al API Gateway local con `SERVERLESS_API_BASE_URL`

## Tecnologias usadas

- Python
- Docker
- Docker Compose
- MiniStack
- AWS CDK
- Lambda
- API Gateway
- DynamoDB

## Nota sobre MiniStack y CDK

A fecha del 8 de mayo de 2026, MiniStack se presenta como un emulador local de AWS sin cuenta ni licencia, y documenta compatibilidad con:

- Lambda
- API Gateway v1 y v2
- DynamoDB
- CloudFormation
- CDK

Tambien documenta `cdklocal` como wrapper recomendado para deploys CDK contra el endpoint local `:4566`.

Fuentes:

- https://ministack.org/
- https://ministack.org/docs/
- https://github.com/marianoarias/ministack

## Flujo de trabajo

### 1. Levantar el entorno local

Desde la raiz del repo:

```bash
docker compose up --build
```

Esto hace lo siguiente:

- Levanta `ministack` con Lambda, API Gateway, CloudFormation, DynamoDB y Logs
- Levanta el contenedor `cdk`
- Monta el repo actual como volumen en `/workspace`
- Espera a que MiniStack quede sano
- Ejecuta `cdklocal bootstrap` automaticamente una vez al iniciar el contenedor `cdk`

### 2. Entrar al contenedor de desarrollo CDK

```bash
docker compose exec cdk bash
```

Luego:

```bash
cd /workspace/infra
```

### 3. Desplegar la infraestructura local

```bash
cdklocal deploy
```

Para iteracion rapida:

```bash
cdklocal deploy --hotswap
```

## Estado actual de validacion

El cambio a MiniStack ya quedo aplicado y validado en estas partes:

- `docker compose up --build` funciona
- `ministackorg/ministack:latest` existe y arranca bien
- `mi-mercado-ministack` queda `healthy`
- `mi-mercado-cdk` queda arriba
- `cdklocal` sintetiza el stack correctamente

Limitacion actual detectada durante la validacion:

- `cdklocal deploy` todavia se atasca en la publicacion de assets a S3 por el modo de direccionamiento virtual-hosted del wrapper `aws-cdk-local`
- En este repo, el error observado termina en resolucion DNS del bucket bootstrap durante los checks y uploads de assets

En otras palabras:

- el cambio a MiniStack si esta hecho y operativo a nivel de contenedores
- el paso pendiente es cerrar el workaround de publicacion de assets CDK hacia S3 dentro del emulador

## Bootstrap de infraestructura

El bootstrap ya queda automatizado por `infra/scripts/start-cdk.sh`.

Si quieres ejecutarlo manualmente:

```bash
cd /workspace/infra
cdklocal bootstrap aws://000000000000/us-east-1
```

## Que despliega el stack

El stack CDK crea:

- Una tabla DynamoDB con particion `PK` y orden `SK`
- Cuatro Lambdas de negocio
- Un Lambda adicional para seed de datos demo
- Un API Gateway REST
- Un Lambda Layer con codigo compartido
- Logs del API

## Endpoints desplegados

- `GET /usuarios/{userId}`
- `GET /usuarios/{userId}/pedidos`
- `GET /usuarios/{userId}/pedidos/{orderId}`
- `POST /usuarios/{userId}/pedidos`

## Como obtener el endpoint final del API Gateway local

### Opcion 1. Ver outputs del deploy

Al terminar `cdklocal deploy`, CDK mostrara el output:

- `ApiUrlLocal`

Ejemplo:

```text
http://localhost:4566/restapis/abc123/local/_user_request_
```

### Opcion 2. Consultarlo despues

Dentro del contenedor `cdk`:

```bash
awslocal cloudformation describe-stacks \
  --stack-name MercadoMiniStack \
  --query "Stacks[0].Outputs[?OutputKey=='ApiUrlLocal'].OutputValue" \
  --output text
```

## Como probar endpoints con curl

Si `ApiUrlLocal` es:

```text
http://localhost:4566/restapis/abc123/local/_user_request_
```

### GET usuario

```bash
curl "http://localhost:4566/restapis/abc123/local/_user_request_/usuarios/123"
```

### GET pedidos

```bash
curl "http://localhost:4566/restapis/abc123/local/_user_request_/usuarios/123/pedidos"
```

### GET detalle de pedido

```bash
curl "http://localhost:4566/restapis/abc123/local/_user_request_/usuarios/123/pedidos/555"
```

### POST crear pedido

```bash
curl -X POST "http://localhost:4566/restapis/abc123/local/_user_request_/usuarios/123/pedidos" \
  -H "Content-Type: application/json" \
  -d '{
    "estado": "Preparando envio",
    "direccion": "Cra 15 # 93-47, Bogota",
    "items": [
      {
        "producto": "Teclado mecanico",
        "cantidad": 1,
        "precio": 320
      },
      {
        "producto": "Mouse gamer",
        "cantidad": 2,
        "precio": 180
      }
    ]
  }'
```

## Variables de entorno

El repo ya incluye `.env.serverless` con valores listos para MiniStack:

```env
AWS_ACCESS_KEY_ID=test
AWS_SECRET_ACCESS_KEY=test
AWS_DEFAULT_REGION=us-east-1
CDK_DEFAULT_ACCOUNT=000000000000
DEPLOY_STAGE=local
DYNAMODB_TABLE=MiMercadoLocal
LOG_LEVEL=INFO
MINISTACK_IMAGE_TAG=latest
MINISTACK_LOG_LEVEL=INFO
MINISTACK_DOCKER_NETWORK=mercado-serverless
```

### Variables de Laravel

En `laravel_app/.env` puedes usar:

```env
AWS_ACCESS_KEY_ID=test
AWS_SECRET_ACCESS_KEY=test
AWS_DEFAULT_REGION=us-east-1
DYNAMODB_ENDPOINT=http://localhost:4566
DYNAMODB_TABLE=MiMercadoLocal
SERVERLESS_API_BASE_URL=
```

Si `SERVERLESS_API_BASE_URL` esta vacia, el frontend sigue usando `/api/...` de Laravel.

Si defines `SERVERLESS_API_BASE_URL` con el valor de `ApiUrlLocal`, el frontend empieza a consumir el API Gateway local.

## Como reutilizar el frontend actual

El frontend existente en `laravel_app/public/js/mercado-app.jsx` ya fue adaptado para trabajar en dos modos:

- Modo actual: consume `Laravel /api/...`
- Modo serverless: consume `API Gateway` usando `SERVERLESS_API_BASE_URL`

## Como ver logs

### Logs del contenedor MiniStack

```bash
docker compose logs -f ministack
```

### Logs de una Lambda especifica

Dentro del contenedor `cdk`:

```bash
awslocal logs tail /aws/lambda/mi-mercado-get-user-local --follow
```

Otros nombres utiles:

- `mi-mercado-get-orders-local`
- `mi-mercado-get-order-detail-local`
- `mi-mercado-create-order-local`
- `mi-mercado-seed-demo-local`

## Como depurar Lambdas localmente

### Opcion 1. Editar y redeploy rapido

Edita:

- `app/python/mercado/`
- `lambdas/*/handler.py`

Luego:

```bash
cd /workspace/infra
cdklocal deploy --hotswap
```

### Opcion 2. Invocar la Lambda directamente

```bash
awslocal lambda invoke \
  --function-name mi-mercado-get-user-local \
  --payload '{"pathParameters":{"userId":"123"}}' \
  /tmp/get-user.json
cat /tmp/get-user.json
```

## Como agregar multiples Lambdas

1. Crea una carpeta nueva en `lambdas/`, por ejemplo `lambdas/get_inventory/handler.py`.
2. Reutiliza `DynamoDbMercadoRepository` o `MercadoService` desde `app/python/mercado/`.
3. Declara la nueva Lambda en `infra/mercado_stack.py`.
4. Dale permisos al recurso necesario.
5. Agrega el recurso y metodo en API Gateway.
6. Ejecuta `cdklocal deploy`.

## Como conectar bases de datos locales

### DynamoDB en MiniStack

Las Lambdas usan:

- `DYNAMODB_TABLE`
- `AWS_DEFAULT_REGION`
- `USE_AWS_EMULATOR=true`
- `AWS_ENDPOINT_URL=http://ministack:4566`

El endpoint local se resuelve automaticamente hacia MiniStack.

## Como dejarlo listo para AWS real

El stack usa constructos estandar de AWS CDK, no un modelo especial solo para MiniStack.

Para migrar a AWS real:

1. Usa credenciales reales de AWS.
2. Ejecuta `cdk bootstrap` en la cuenta real.
3. Quita `USE_AWS_EMULATOR`.
4. Elimina el `AWS_ENDPOINT_URL` local.
5. Despliega con `cdk deploy`.

## Resumen rapido de arranque

```bash
docker compose up --build
docker compose exec cdk bash
cd /workspace/infra
cdklocal deploy
```

Luego:

1. Toma el output `ApiUrlLocal`
2. Pruebalo con `curl`
3. Si quieres que Laravel consuma el API Gateway, pega ese valor en `SERVERLESS_API_BASE_URL`

## Archivos clave

- [docker-compose.yml](/c:/Users/oscar/Downloads/NOSQL/V1/docker-compose.yml)
- [Dockerfile](/c:/Users/oscar/Downloads/NOSQL/V1/Dockerfile)
- [infra/mercado_stack.py](/c:/Users/oscar/Downloads/NOSQL/V1/infra/mercado_stack.py)
- [app/python/mercado/service.py](/c:/Users/oscar/Downloads/NOSQL/V1/app/python/mercado/service.py)
- [lambdas/create_order/handler.py](/c:/Users/oscar/Downloads/NOSQL/V1/lambdas/create_order/handler.py)
- [laravel_app/public/js/mercado-app.jsx](/c:/Users/oscar/Downloads/NOSQL/V1/laravel_app/public/js/mercado-app.jsx)
