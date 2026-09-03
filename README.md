# Tesseract Flow — Gestión de proyectos · PMI

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

El nombre, lema y rutas de los logotipos de Tesseract Flow viven en `config/branding.php`. El
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

`.env.production` vive solo en el servidor y nunca entra al repositorio. Un
cambio a `.env.production.example` **no llega solo a produccion**: la plantilla
documenta que variables existen, y el archivo de verdad se edita en el servidor.

El servidor jala de `main`. Una rama empujada a GitHub no le llega hasta que
este en main.

### Si el script no corre

`deploy-pm-ariel.sh` es lo mismo que estos cuatro pasos, y sirven cuando hay que
ver donde falla o cuando un intermediario bloquea la llamada al script completo:

```
cd /opt/pm-ariel && git pull --ff-only origin main
docker build --target app -t pm-ariel-app:latest .
docker build --target web -t pm-ariel-web:latest .
docker compose -f compose.production.yml up -d --no-build
```

Entrar por SSH sin sesion interactiva necesita `SSH_ASKPASS`: OpenSSH lee la
contraseña de la terminal y no de la entrada estandar, asi que una tuberia no
alcanza. `plink` de PuTTY tampoco, porque pide confirmar la llave del host en la
consola. **La contraseña va en una variable de entorno, nunca en un archivo.**

Para `git push` no hace falta ningun token en un `.env`: Git Credential Manager
ya guarda la credencial en el almacen cifrado de Windows. La contraseña de la
cuenta de GitHub **no** sirve para `git push` desde 2021 — si algun dia se
necesita automatizar, se crea un token en GitHub y se guarda en el gestor de
credenciales, no en texto plano.

## Correr las pruebas en Windows

Dos cosas que no salen de la configuracion del repositorio:

```
PHP=/c/laragon/bin/php/php-8.4.24-Win32-vs17-x64/php.exe
DB_PORT=3307 DB_USERNAME=pm_ariel DB_PASSWORD='...' "$PHP" artisan test
```

- **El `php` del PATH es 8.2** (XAMPP) y `composer.json` exige >= 8.4.1, asi que
  `php artisan` muere en `platform_check.php`. El 8.4 esta en Laragon. `ci.ps1`
  ya lo resuelve solo y respeta la variable `PM_ARIEL_PHP`.
- **MySQL local escucha en 3307.** `phpunit.xml` fuerza `mysql` y la base
  `pm_ariel_test` pero **no define `DB_PORT`**, asi que sin pasarlo cae al 3306 y
  todo falla con «connection refused». Hay un `.env.testing` que dice `sqlite`,
  pero `phpunit.xml` le gana.

Conviene medir la linea base antes de atribuirse una falla: `git stash` y correr
lo mismo en HEAD limpio.
