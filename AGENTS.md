# tu-cromi-admin

Laravel 13 + Vue 3 + Inertia.js v3 SPA (TypeScript, Tailwind CSS v4, shadcn-vue new-york-v4).

## Commands

| What | Command | Notes |
|---|---|---|
| Dev server | `composer dev` | Laravel Chisel (PHP + Vite concurrently) |
| Vite only | `npm run dev` | |
| PHP tests | `php artisan test` | Use `--compact --filter=testName` for single test |
| All checks | `composer test` | lint:check → types:check → test |
| PHP lint | `composer lint` | Pint (laravel preset) |
| PHP lint check | `composer lint:check` | `pint --parallel --test` |
| PHPStan | `composer types:check` | Level 7, covers `app/`, `config/`, `database/`, `routes/` |
| Frontend lint | `npm run lint` | ESLint + fix |
| Frontend format | `npm run format` | Prettier on `resources/` |
| TypeScript check | `npm run types:check` | `vue-tsc --noEmit` |
| Full CI | `composer ci:check` | Frontend lint → format → types → test |
| Build | `npm run build` | |
| Codegen | `php artisan wayfinder:generate` | Regenerates `resources/js/{actions,routes,wayfinder}/` |

## Architecture

- **Frontend entry**: `resources/js/app.ts` — layout dispatching, theme init, flash toasts.
- **Pages**: `resources/js/pages/` auto-discovered by Inertia. Auth pages under `auth/`, settings under `settings/`.
- **Layouts**: Assigned by page name in `app.ts`: `auth/*` → AuthLayout, `settings/*` → AppLayout+SettingsLayout, `Welcome` → none, else → AppLayout.
- **Routes**: `routes/web.php`, `routes/settings.php`, `routes/console.php`. Use named routes.
- **`@` alias**: `resources/js/` (tsconfig + Inertia config).
- **DB**: PostgreSQL (dev/prod), SQLite `:memory:` (tests).
- **SSR**: Enabled. Dev URL at `127.0.0.1:13714` (config/inertia.php).
- **Auth**: Laravel Fortify — features: registration, password reset, email verification, 2FA, passkeys. Config at `config/fortify.php`.
- **Gated pages**: `dashboard` requires `auth` + `verified` middleware.

## Conventions

- **Pint** after every PHP change: `vendor/bin/pint --dirty --format agent` (not `--test`).
- **Prettier**: 4-space indent, single quotes, semicolons. YAML files use 2-space indent. Ignores `resources/js/components/ui/*`, `resources/views/mail/*`.
- **ESLint**: block brace `1tbs` (no single line), padding around control statements, sorted imports (builtin→external→internal→parent→sibling→index). Ignores codegen outputs.
- **shadcn-vue**: `cn()` from `@/lib/utils` (clsx + tailwind-merge), lucide icons.
- **Wayfinder**: Import controllers from `@/actions/`, named routes from `@/routes/`. Generated files are gitignored.
- **Cookies excluded from encryption**: `appearance`, `sidebar_state` (see `bootstrap/app.php`).
- **Tests**: PHPUnit classes (not Pest). `RefreshDatabase` trait. Use `skipUnlessFortifyHas()` for conditional Fortify feature tests.
- **Passkeys**: `PASSKEYS_USER_HANDLE_SECRET` env var (falls back to `APP_KEY`).
- **App boots** `initializeTheme()` + `initializeFlashToast()` on every page load (see `app.ts`).
- **Migrations**: `$table->timestamps()` for `created_at`/`updated_at` pairs; `$table->softDeletes()` for soft deletes; `string()` defaults to 255; `foreignId('foo_id')->constrained()` resolves table by convention.
