# Guia Rapida PHP

El proyecto ya no necesita MiniStack, CDK, Lambda ni DynamoDB para ejecutarse en
local. La migracion deja una app PHP con almacenamiento JSON local.

## Estructura por capas

- `public/`: gateway HTTP.
- `src/Lambdas/`: handlers de los endpoints.
- `src/Business/`: reglas del negocio y contratos.
- `src/Gateways/`: acceso a datos.
- `src/Http/`: utilidades HTTP y render web.

## Levantar sin Docker

```powershell
php -S 127.0.0.1:8000 -t public public/index.php
```

Abre:

```text
http://127.0.0.1:8000
```

## Levantar con Docker

```powershell
docker compose up --build
```

Abre:

```text
http://127.0.0.1:8000
```

## Probar API

```powershell
curl "http://127.0.0.1:8000/api/usuarios/123"
curl "http://127.0.0.1:8000/api/usuarios/123/pedidos"
curl "http://127.0.0.1:8000/api/usuarios/123/pedidos/555"
```

## Reiniciar datos

La app crea `storage/mercado_data.json` a partir de
`app/data/mercado_seed.json`. Para volver al estado inicial:

```powershell
Remove-Item .\storage\mercado_data.json
```
