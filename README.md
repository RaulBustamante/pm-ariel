# Gestión de Proyectos — Ariel Premium Supply

Sistema de administración de proyectos con ruta crítica: acta constitutiva
guiada, plan de trabajo, Gantt, tablero, calendario y avisos proactivos.

Este archivo existe para que **alguien que nunca ha visto el proyecto lo levante
desde cero**. Si algo aquí no funciona tal cual está escrito, es un error del
archivo, no del lector.

---

## Lo que necesitas

| Pieza | Versión | Por qué esa |
|---|---|---|
| PHP | **8.4** | `8.2` deja de tener soporte el 31-dic-2026 (riesgo R-12) |
| MySQL | 8.4 o MariaDB 10.6+ | El código no usa nada exclusivo de un motor (regla R-11) |
| Node | 20+ | Solo para compilar los estilos |
| Composer | 2.x | |

Extensiones de PHP: `intl`, `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`,
`gd`, `zip`.

> **En Windows con Laragon**, PHP y MySQL no quedan en el `PATH`. Se llaman por
> ruta completa. Los ejemplos de abajo usan la ruta de Laragon; ajústala si tu
> instalación está en otro lado.

---

## Levantarlo desde cero

```bash
git clone <repositorio> pm-ariel
cd pm-ariel

composer install
cp .env.example .env
php artisan key:generate
```

Edita `.env` con los datos de tu base:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pm_ariel
DB_USERNAME=pm_ariel
DB_PASSWORD=…

APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_TIMEZONE=America/Tijuana
```

Crea la base y el usuario, y luego:

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan db:seed --class=ProjectTemplatesSeeder

npm install
npm run build

php artisan serve
```

### Para entrar

**En desarrollo** — crea un administrador con contraseña conocida:

```bash
php artisan db:seed --class=DevAdminSeeder
```

Deja `admin@localhost` / `Ariel2026!Raul`, ajustables con `DEV_ADMIN_EMAIL` y
`DEV_ADMIN_PASSWORD`. Se puede correr las veces que haga falta, y **limpia el
bloqueo por intentos fallidos** — cinco intentos malos bloquean un minuto y eso
se confunde con una contraseña incorrecta. **Se niega a correr en producción.**

**En producción:**

```bash
php artisan db:seed
```

Imprime el correo y la contraseña temporal del primer administrador **una sola
vez**. Anótala: no se vuelve a mostrar. Si se corre sin consola, falla con un
mensaje explícito en vez de crear una cuenta cuya contraseña nadie vio.

### Para ver el producto funcionando sin capturar nada

```bash
php artisan db:seed --class=DemoProjectSeeder
```

Carga un proyecto realista de 14 tareas con dependencias, tres personas y avance
parcial. Trae **dos problemas plantados a propósito** —alguien al 200 % y tareas
críticas sin responsable— porque un demo donde todo está bien no demuestra nada.

---

## Revisión antes de dar algo por bueno

```powershell
.\ci.ps1
```

Corre cinco etapas en una pasada y **no se detiene en la primera falla**:

| Etapa | Qué revisa |
|---|---|
| Formato | Pint |
| Análisis estático | PHPStan/Larastan **nivel 6**, sin errores permitidos |
| Marca | Que el nombre del producto no se filtre al código |
| Estilos | Que estén compilados después del último cambio de plantilla |
| Pruebas | La suite completa |

Variantes: `.\ci.ps1 -Fix` deja que Pint corrija · `.\ci.ps1 -Only stan` corre
una sola etapa.

En Linux o macOS, las mismas cuatro órdenes:

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress
php artisan branding:verify
php artisan test
```

---

## Respaldo

```bash
php artisan backup:run
```

Copia **base de datos + archivos + bundle del repositorio** a la ruta de
`BACKUP_DESTINATION`.

> **Si esa variable está vacía, el respaldo cae en `storage/backups`, dentro del
> mismo disco.** Eso no es un respaldo: un disco que falla se lleva el original
> y la copia. Apúntala a otro disco o a un recurso de red antes de usar el
> sistema de verdad.

---

## Cómo está organizado

```
app/
  Support/Scheduling/     El motor de ruta crítica. Sin Eloquent, a propósito:
                          recibe datos puros y devuelve datos puros, y por eso
                          se puede probar contra casos resueltos en papel.
  Support/Initiation/     El recorrido de inicio y su semáforo de completitud.
  Services/Scheduling/    El puente entre el motor y la base.
  Services/Advisor/       Las reglas que detectan lo que amenaza la entrega.
  Services/Import/        El importador de hojas de cálculo.
  Contracts/              Las costuras: identidad (SSO), sugerencias (IA) y
                          calendarios externos (Google/Outlook).
```

Tres cosas que conviene saber antes de tocar el código:

1. **`WorkingCalendar` es el único lugar** que sabe qué es un fin de semana, un
   feriado o una jornada partida. Toda la aritmética va en **minutos de
   trabajo**, nunca en días.
2. **En `tasks`, las columnas se dividen en dos grupos que no se mezclan:** lo
   que capturó el usuario y lo que produjo el cálculo (`early_*`, `late_*`,
   holguras, `is_critical`). El motor solo escribe el segundo.
3. **Las líneas base no se editan ni se borran** — el modelo lanza excepción.
   Toda su utilidad viene de eso.

---

## Idiomas

Español e inglés, en `lang/es` y `lang/en`. **Ningún texto visible vive en el
código.** Agregar un idioma es copiar la carpeta y listarlo en
`config/app.php → supported_locales`.

---

## Marca

El nombre del producto vive en `config/branding.php` y en ningún otro lado. El
comando `branding:verify` falla si se filtra al código, y corre dentro del CI.
Cambiar de nombre es editar un archivo.

## Despliegue

El VPS tiene una copia de trabajo del repositorio en `/opt/pm-ariel`, con una
llave de despliegue de solo lectura. El ciclo es: cambias aquí, empujas a
GitHub, y el servidor jala.

```
git push origin main                              # desde tu maquina
ssh root@el-servidor /root/deploy-pm-ariel.sh     # en el VPS
```

El script hace `git pull`, reconstruye las dos imagenes y las levanta.

**`docker compose build` no funciona en este servidor:** buildx es 0.14.1 y
compose exige 0.17 o mas. Por eso las imagenes se construyen con `docker build
--target app|web` y compose solo las levanta con `--no-build`. Si algun dia se
actualiza buildx, el script se puede simplificar.

`.env.production` vive solo en el servidor y nunca entra al repositorio.
