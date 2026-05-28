# AWS LocalStack CDK PHP Monorepo

Monorepo local para simular una arquitectura AWS con Docker, Docker Compose,
LocalStack, AWS CDK en Python y una Lambda PHP ejecutada con runtime custom.

Este proyecto no usa AWS real. Todos los recursos se crean dentro de
LocalStack y usan credenciales dummy.

## Arquitectura

```text
proyecto/
|-- infra/
|   |-- api_stack.py
|   |-- core_stack.py
|   `-- persistence_stack.py
|-- lambdas/
|   `-- php/
|       `-- ecommerce/
|           |-- Dockerfile
|           |-- bootstrap
|           `-- index.php
|-- docker/
|   |-- cdk-local/
|   |   `-- Dockerfile
|   `-- localstack-php-runtime/
|       `-- Dockerfile
|-- scripts/
|   `-- prepare-lambda-runtime.ps1
|-- .devcontainer/
|   `-- devcontainer.json
|-- docker-compose.yml
|-- app.py
|-- requirements.txt
|-- cdk.json
|-- .env.example
|-- .gitignore
`-- README.md
```

## Servicios AWS simulados

- API Gateway REST API
- Lambda PHP con runtime custom ZIP
- DynamoDB
- S3
- CloudFormation, IAM, STS, CloudWatch Logs y S3 assets para soporte del despliegue CDK

## Flujo de despliegue

1. `scripts/prepare-lambda-runtime.ps1` prepara `lambda-runtime/` con PHP CLI.
2. `cdk-local` sintetiza la aplicacion CDK.
3. CDK empaqueta la Lambda como ZIP custom runtime.
4. CloudFormation en LocalStack crea S3, DynamoDB, Lambda y API Gateway.
5. API Gateway invoca la Lambda PHP localmente por el puerto `4566`.

## Requisitos

- Docker Desktop o Docker Engine
- Docker Compose v2
- VS Code con extension Dev Containers, opcional

No necesitas Python, Node, CDK, PHP ni AWS CLI instalados en tu maquina host:
todo viene dentro del contenedor `cdk-local`.

## Variables

Puedes copiar `.env.example` a `.env` si quieres modificar nombres o region.
Los valores por defecto son:

```env
PROJECT_NAME=ecommerce-local
AWS_REGION=us-east-1
AWS_DEFAULT_REGION=us-east-1
CDK_DEFAULT_ACCOUNT=000000000000
CDK_DEFAULT_REGION=us-east-1
LOCALSTACK_ENDPOINT=http://localstack:4566
```

## Levantar el entorno

Prepara el runtime PHP que se empaqueta dentro del ZIP de Lambda:

```powershell
.\scripts\prepare-lambda-runtime.ps1
```

```powershell
docker compose up -d --build
```

Verifica que los contenedores estan activos:

```powershell
docker compose ps
```

El compose solo levanta LocalStack y el contenedor `cdk-local`; la interfaz
EcoCart se sirve desde API Gateway + Lambda despues del deploy.

## Bootstrap CDK local

```powershell
docker compose exec -T -e LOCALSTACK_HOSTNAME=localstack -e AWS_ENVAR_ALLOWLIST=AWS_ACCESS_KEY_ID,AWS_SECRET_ACCESS_KEY,AWS_DEFAULT_REGION,AWS_REGION -e AWS_ACCESS_KEY_ID=test -e AWS_SECRET_ACCESS_KEY=test -e AWS_DEFAULT_REGION=us-east-1 -e AWS_REGION=us-east-1 cdk-local cdklocal bootstrap aws://000000000000/us-east-1
```

## Deploy local

```powershell
docker compose exec -T -e LOCALSTACK_HOSTNAME=localstack -e AWS_ENVAR_ALLOWLIST=AWS_ACCESS_KEY_ID,AWS_SECRET_ACCESS_KEY,AWS_DEFAULT_REGION,AWS_REGION -e AWS_ACCESS_KEY_ID=test -e AWS_SECRET_ACCESS_KEY=test -e AWS_DEFAULT_REGION=us-east-1 -e AWS_REGION=us-east-1 cdk-local cdklocal deploy --all --require-approval never
```

## Tabla usuarios, ordenes e items

El stack `PersistenceStack` crea una tabla DynamoDB llamada
`ecommerce-local-orders` para el modelo transaccional de usuario, ordenes e
items.

| PK | SK | Tipo | Datos principales |
| --- | --- | --- | --- |
| USER#123 | PROFILE | USER | nombre, email, direcciones, pagos |
| USER#123 | ORDER#{id} | ORDER | estado, fecha, direccion, total |
| USER#123 | ORDER#{id}#ITEM#{n} | ITEM | producto, cantidad, precio, subtotal |

La tabla usa:

- Partition key: `PK`
- Sort key: `SK`
- GSI: `TipoIndex` con `Tipo` como partition key y `PK` como sort key

El seed inicial solo crea el perfil del usuario. Las ordenes y los items se
insertan despues, cuando el usuario compra productos desde la tabla
`productos`.

Cargar perfil inicial:

```powershell
docker compose exec -T -e AWS_ACCESS_KEY_ID=test -e AWS_SECRET_ACCESS_KEY=test -e AWS_DEFAULT_REGION=us-east-1 -e AWS_REGION=us-east-1 cdk-local aws --endpoint-url=http://localstack:4566 dynamodb batch-write-item --request-items file://infra/seeds/users_orders.json
```

Consultar todo el agregado de un usuario:

```powershell
docker compose exec -T -e AWS_ACCESS_KEY_ID=test -e AWS_SECRET_ACCESS_KEY=test -e AWS_DEFAULT_REGION=us-east-1 -e AWS_REGION=us-east-1 cdk-local aws --endpoint-url=http://localstack:4566 dynamodb query --table-name ecommerce-local-orders --key-condition-expression "PK = :pk" --expression-attribute-values '{":pk":{"S":"USER#123"}}'
```

Consultar una orden y sus items:

```powershell
docker compose exec -T -e AWS_ACCESS_KEY_ID=test -e AWS_SECRET_ACCESS_KEY=test -e AWS_DEFAULT_REGION=us-east-1 -e AWS_REGION=us-east-1 cdk-local aws --endpoint-url=http://localstack:4566 dynamodb query --table-name ecommerce-local-orders --key-condition-expression "PK = :pk AND begins_with(SK, :sk)" --expression-attribute-values '{":pk":{"S":"USER#123"},":sk":{"S":"ORDER#555"}}'
```

Ese ultimo query devolvera datos cuando ya se hayan insertado ordenes e items
de compra.

## Tabla productos

El stack `PersistenceStack` crea una tabla DynamoDB llamada `productos` con
modelo single-table para catalogo, inventario, categorias, marcas y ofertas.

| PK | SK | Tipo |
| --- | --- | --- |
| PRODUCT#100 | INFO | PRODUCT |
| PRODUCT#100 | INVENTORY | STOCK |
| CATEGORY#ELECTRONICA | PRODUCT#100 | PRODUCT_REF |
| BRAND#APPLE | PRODUCT#100 | PRODUCT_REF |
| OFFER#1 | PRODUCT#100 | OFFER_ITEM |

El seed `infra/seeds/productos.json` agrega esos registros base y tambien
productos adicionales para la interfaz EcoCart:

- Teléfono Inteligente X100
- Portátil WorkPro 15
- Auriculares Bluetooth Z5
- Reloj Inteligente FitTrack
- Mochila de Viaje
- Camiseta Algodón Hombre

La tabla usa:

- Partition key: `PK`
- Sort key: `SK`
- GSI: `TipoIndex` con `Tipo` como partition key y `PK` como sort key

Despues del deploy, carga los datos iniciales:

```powershell
docker compose exec -T -e AWS_ACCESS_KEY_ID=test -e AWS_SECRET_ACCESS_KEY=test -e AWS_DEFAULT_REGION=us-east-1 -e AWS_REGION=us-east-1 cdk-local aws --endpoint-url=http://localstack:4566 dynamodb batch-write-item --request-items file://infra/seeds/productos.json
```

Consulta todos los items:

```powershell
docker compose exec -T -e AWS_ACCESS_KEY_ID=test -e AWS_SECRET_ACCESS_KEY=test -e AWS_DEFAULT_REGION=us-east-1 -e AWS_REGION=us-east-1 cdk-local aws --endpoint-url=http://localstack:4566 dynamodb scan --table-name productos
```

Consulta el producto base:

```powershell
docker compose exec -T -e AWS_ACCESS_KEY_ID=test -e AWS_SECRET_ACCESS_KEY=test -e AWS_DEFAULT_REGION=us-east-1 -e AWS_REGION=us-east-1 cdk-local aws --endpoint-url=http://localstack:4566 dynamodb query --table-name productos --key-condition-expression "PK = :pk" --expression-attribute-values '{":pk":{"S":"PRODUCT#100"}}'
```

El frontend llama API Gateway en LocalStack, que invoca la Lambda PHP y consulta
DynamoDB:

```text
GET http://localhost:4566/restapis/{apiId}/local/_user_request_/api/productos
```

## Abrir EcoCart por API Gateway

Obtiene el ID de la API:

```powershell
$apiId = docker compose exec -T -e AWS_ACCESS_KEY_ID=test -e AWS_SECRET_ACCESS_KEY=test -e AWS_DEFAULT_REGION=us-east-1 -e AWS_REGION=us-east-1 cdk-local aws --endpoint-url=http://localstack:4566 apigateway get-rest-apis --query "items[?name=='ecommerce-local-api'].id | [0]" --output text
```

Abre la interfaz EcoCart:

```powershell
curl "http://localhost:4566/restapis/$apiId/local/_user_request_/ecommerce"
```

En el navegador usa esa misma URL:

```text
http://localhost:4566/restapis/{apiId}/local/_user_request_/ecommerce
```

Probar productos por API Gateway:

```powershell
curl "http://localhost:4566/restapis/$apiId/local/_user_request_/api/productos"
```

Tambien puedes invocar la Lambda directamente:

```powershell
docker compose exec -T -e AWS_ACCESS_KEY_ID=test -e AWS_SECRET_ACCESS_KEY=test -e AWS_DEFAULT_REGION=us-east-1 -e AWS_REGION=us-east-1 cdk-local aws --endpoint-url=http://localstack:4566 lambda invoke --function-name ecommerce-local-php-ecommerce --payload "{}" /tmp/response.json
docker compose exec cdk-local cat /tmp/response.json
```

## Comandos utiles

Sintetizar CloudFormation:

```powershell
docker compose exec cdk-local cdklocal synth
```

Listar stacks:

```powershell
docker compose exec cdk-local cdklocal list
```

Listar tablas DynamoDB:

```powershell
docker compose exec -T -e AWS_ACCESS_KEY_ID=test -e AWS_SECRET_ACCESS_KEY=test -e AWS_DEFAULT_REGION=us-east-1 -e AWS_REGION=us-east-1 cdk-local aws --endpoint-url=http://localstack:4566 dynamodb list-tables
```

Listar buckets S3:

```powershell
docker compose exec -T -e AWS_ACCESS_KEY_ID=test -e AWS_SECRET_ACCESS_KEY=test -e AWS_DEFAULT_REGION=us-east-1 -e AWS_REGION=us-east-1 cdk-local aws --endpoint-url=http://localstack:4566 s3 ls
```

Ver logs de LocalStack:

```powershell
docker compose logs -f localstack
```

Destruir la infraestructura local:

```powershell
docker compose exec cdk-local cdklocal destroy --all --force
```

Detener contenedores sin borrar persistencia:

```powershell
docker compose down
```

Borrar tambien la persistencia de LocalStack:

```powershell
docker compose down -v
```

## VS Code DevContainer

Abre la carpeta en VS Code y ejecuta:

```text
Dev Containers: Reopen in Container
```

VS Code entrara al servicio `cdk-local`, con el workspace montado en
`/workspace` y con acceso a LocalStack por `http://localstack:4566`.

Dentro del DevContainer puedes ejecutar directamente:

```bash
cdklocal bootstrap aws://000000000000/us-east-1
cdklocal deploy --all --require-approval never
```

## Separacion de stacks

- `CoreStack`: recursos compartidos, actualmente S3.
- `PersistenceStack`: capa de datos, actualmente DynamoDB con `orders` y `productos`.
- `ApiStack`: API Gateway y Lambda PHP.

Esta separacion mantiene responsabilidades claras y permite evolucionar cada
capa sin acoplar la infraestructura completa a un unico archivo.

## Lambda PHP custom runtime

La Lambda se empaqueta como ZIP con runtime `provided.al2023`.

- `docker/localstack-php-runtime/Dockerfile`: genera un PHP CLI compatible
  con Amazon Linux 2023.
- `scripts/prepare-lambda-runtime.ps1`: copia `php` y sus librerias a
  `lambda-runtime/` para incluirlos en el ZIP.
- `bootstrap`: implementa el loop del Lambda Runtime API y ejecuta el PHP
  empaquetado.
- `index.php`: router serverless que sirve la UI, archivos estaticos y rutas
  `/api/...` como respuesta API Gateway proxy.

El flujo queda:

```text
Frontend
  -> API Gateway en LocalStack
  -> Lambda PHP
  -> DynamoDB en LocalStack
```
