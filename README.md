# Tu Cromi Admin

Panel administrativo para la gestión de líneas de micros del transporte público urbano de Santa Cruz de la Sierra.

Georreferenciación de rutas sobre mapa interactivo, CRUD de líneas, gestión de incidencias, cómputo de transferencias peatonales y administración de reseñas/usuarios.

## Stack

- **Backend**: Laravel 13 (PHP 8.3)
- **Frontend**: Vue 3, Inertia.js v3 (SPA), TypeScript
- **Estilos**: Tailwind CSS v4, shadcn-vue (new-york-v4)
- **Base de datos**: PostgreSQL + PostGIS
- **SSR**: Inertia SSR habilitado

## Setup

```bash
composer install
npm install

# Configurar .env con conexión PostgreSQL + PostGIS
cp .env.example .env
php artisan key:generate

# Migraciones + seed
php artisan migrate
php artisan db:seed

# Iniciar dev (PHP + Vite)
composer dev
```

## Comandos principales

| Comando | Descripción |
|---|---|
| `composer dev` | Servidor de desarrollo (Chisel) |
| `npm run dev` | Solo Vite |
| `php artisan test --compact` | Tests |
| `composer test` | Lint → PHPStan → Tests |
| `composer lint` | Pint (formatear PHP) |
| `npm run lint` | ESLint (formatear frontend) |
| `npm run types:check` | vue-tsc (type check) |
| `npm run build` | Build producción |
| `php artisan wayfinder:generate` | Regenerar codegen (`@/routes/`, `@/actions/`) |
| `php artisan lines:import --force` | Importar líneas desde GeoJSON |
| `php artisan transfers:compute` | Computar transferencias peatonales (300m, lento ~17min). Usar `--limit=N` para pruebas |

## Importación de líneas desde GeoJSON

Colocar el archivo `rutas_scz.geojson` en `database/data/` y ejecutar:

```bash
# Importar (aborta si ya hay datos)
php artisan lines:import

# Forzar reimportación (truncate + insert)
php artisan lines:import --force

# Ruta personalizada
php artisan lines:import --path=/ruta/completa/archivo.geojson
```

El comando:
1. Lee features del GeoJSON.
2. Crea líneas con sentido (OUTBOUND/RETURN) según `sentido`.
3. Revierte coordenadas en líneas de vuelta (RETURN).
4. Vincula líneas opuestas (ida/vuelta) por código.
5. Puebla la columna PostGIS `geom` desde `geo_json`.

## Convenciones

- **PHP**: Pint (Laravel preset). Ejecutar `vendor/bin/pint --dirty --format agent` tras cada cambio.
- **Frontend**: Prettier (4 espacios, single quotes, punto y coma). ESLint: `1tbs`, padding en control statements, imports ordenados.
- **shadcn-vue**: `cn()` desde `@/lib/utils` (clsx + tailwind-merge), iconos lucide.
- **Wayfinder**: Importar rutas desde `@/routes/`, controladores desde `@/actions/`. Archivos generados en `.gitignore`.
- **Tests**: PHPUnit (no Pest), trait `RefreshDatabase`, `skipUnlessFortifyHas()` para features condicionales.
- **Migrations**: `$table->timestamps()`, `$table->softDeletes()`, `string()` sin length (default 255), `foreignId()->constrained()` por convención.

## Base de datos

Requiere PostgreSQL con extensión **PostGIS** habilitada.

```sql
CREATE EXTENSION IF NOT EXISTS postgis;
```

Las migraciones la habilitan automáticamente. La columna `lines.geom` es `geometry(MultiLineString, 4326)` con índice GIST para consultas espaciales.

## Estructura del proyecto

```
app/
├── Console/Commands/     # Artisan commands (lines:import, transfers:compute)
├── Enums/                # PHP enums (LineSense)
├── Models/               # Eloquent models (Line, User, Favorite, Review)
├── Http/Controllers/     # Inertia controllers
└── Http/Middleware/      # HandleAppearance, HandleInertiaRequests
database/
├── data/                 # Archivos GeoJSON para importación
├── factories/            # Model factories
├── migrations/           # Migraciones
└── seeders/              # Seeders
resources/js/
├── pages/                # Inertia pages (auto-descubiertas)
├── layouts/              # Layouts (AppLayout, AuthLayout, SettingsLayout)
├── components/           # Componentes Vue (ui/, app/)
└── lib/                  # Utilidades (utils.ts, flashToast)
routes/
├── web.php               # Rutas principales
├── settings.php          # Rutas de settings
└── console.php           # Rutas de consola
```
