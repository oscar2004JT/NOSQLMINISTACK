# Guia Rapida MiniStack

Esta guia resume los comandos principales para trabajar con este proyecto usando Docker + MiniStack.

## 1. Levantar el proyecto en Docker

Desde la raiz del proyecto:

```powershell
docker compose up -d --build
```

Para verificar que los contenedores estan corriendo:

```powershell
docker compose ps
```

Deberias ver:

- `mi-mercado-ministack`
- `mi-mercado-cdk`

## 2. Crear e insertar la tabla con datos demo

Este proyecto ya tiene un script para crear la tabla `MiMercadoLocal` y cargar los datos demo.

Ejecuta:

```powershell
docker compose exec cdk bash -lc "python /workspace/infra/scripts/seed_ministack.py"
```

Ese comando hace dos cosas:

- crea la tabla `MiMercadoLocal` si no existe
- inserta los datos del archivo compartido `app/data/mercado_seed.json`

## 3. Ver las tablas creadas

Desde PowerShell:

```powershell
aws dynamodb list-tables --endpoint-url http://localhost:4566 --region us-east-1
```

Debes ver algo como:

```json
{
  "TableNames": [
    "MiMercadoLocal"
  ]
}
```

## 4. Ver los datos de la tabla

Para ver todos los registros:

```powershell
aws dynamodb scan --table-name MiMercadoLocal --endpoint-url http://localhost:4566 --region us-east-1
```

Para ver solo cuántos registros hay:

```powershell
aws dynamodb scan --table-name MiMercadoLocal --endpoint-url http://localhost:4566 --region us-east-1 --select COUNT
```

Los datos demo cargados incluyen:

- `PROFILE`
- `ORDER#555`
- `ORDER#555#ITEM#1`
- `ORDER#555#ITEM#2`
- `ORDER#556`

## 5. Entrar al contenedor CDK

Si necesitas trabajar dentro del contenedor:

```powershell
docker compose exec cdk bash
```

Una vez dentro:

```bash
cd /workspace/infra
```

## 6. Ver el proyecto en el navegador

MiniStack corre sobre:

```text
http://localhost:4566
```

Pero eso no significa que ya tengas una pagina web lista ahi. En este repo hay dos cosas distintas:

- MiniStack para emular servicios AWS
- Laravel como frontend actual del proyecto

### Ver el frontend Laravel en el navegador

Debes correr Laravel por separado desde `laravel_app`.

Ejemplo:

```powershell
cd .\laravel_app
php artisan serve
```

Luego abre:

```text
http://127.0.0.1:8000
```

### Ver datos desde MiniStack

MiniStack no te muestra directamente la app web. Sirve como backend emulado para:

- DynamoDB
- Lambda
- API Gateway
- CloudFormation

Por eso para comprobar que los datos estan bien cargados normalmente usas:

- `aws dynamodb list-tables`
- `aws dynamodb scan`
- endpoints API cuando el deploy quede listo

## 7. Ver logs de los contenedores

Logs generales:

```powershell
docker compose logs -f
```

Logs solo de MiniStack:

```powershell
docker compose logs -f ministack
```

Logs solo del contenedor CDK:

```powershell
docker compose logs -f cdk
```

## 8. Apagar el entorno

```powershell
docker compose down
```

## 9. Resumen rapido

Levantar proyecto:

```powershell
docker compose up -d --build
```

Insertar tabla y datos:

```powershell
docker compose exec cdk bash -lc "python /workspace/infra/scripts/seed_ministack.py"
```

Ver tablas:

```powershell
aws dynamodb list-tables --endpoint-url http://localhost:4566 --region us-east-1
```

Ver datos:

```powershell
aws dynamodb scan --table-name MiMercadoLocal --endpoint-url http://localhost:4566 --region us-east-1
```

Ver Laravel en navegador:

```powershell
cd .\laravel_app
php artisan serve
```

Abrir:

```text
http://127.0.0.1:8000
```
