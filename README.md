# pm-ariel

Sistema interno de gestión de proyectos y portafolio, grado PMI. Reemplaza el uso de
MS Project y Excel.

> **El nombre del producto no vive en el código.** `pm-ariel` es el nombre del repositorio.
> El nombre comercial se configura en `config/branding.php` y se consume desde ahí.
> La planeación completa vive fuera de este repositorio, en
> `asistente-ejecutivo-trabajo/projects/pm-ariel/`.

---

## Estado

**Etapa 1 — Fundación técnica.** En curso.

| Bloque | Entregable | Estado |
|---|---|---|
| 1.0 | Entorno PHP 8.4 + MySQL 8.4 | ✅ |
| 1.1 | Repositorio local | ✅ |
| 1.2 | Laravel 13 instalado | ✅ |
| 1.3 | Comando de respaldo con restauración probada | ✅ |
| 1.4 | `config/branding.php` + verificación de marca | ⬜ |
| 1.5 | Pint, PHPStan y CI local | ⬜ |
| 1.6+ | Accesos, roles, jerarquía, i18n, auditoría | ⬜ |

---

## Entorno

Verificado, no supuesto:

| Componente | Versión | Notas |
|---|---|---|
| PHP | 8.4.24 ZTS (VS17) | Laravel 13 exige 8.3+. XAMPP se detiene en 8.2.12 |
| Laravel | 13.9.0 (framework v13.25.0) | |
| MySQL | 8.4.3 | Puerto **3307**, `utf8mb4_unicode_ci`, zona horaria del servidor UTC |
| Node | 24.11.0 | |
| Composer | 2.8.12 | Debe correr sobre PHP 8.4, no sobre el de XAMPP |

**Por qué el puerto 3307.** La instalación de XAMPP conserva el 3306 y se deja intacta hasta
que este entorno esté probado. Cuando XAMPP se retire, cambiar `port` en
`C:\laragon\bin\mysql\mysql-8.4.3-winx64\my.ini` y `DB_PORT` en `.env`.

**Zona horaria.** Aplicación y base corren en UTC. La conversión a la zona del usuario es
responsabilidad de la vista, nunca de la base ni del dominio.

---

## Levantar el proyecto desde cero

1. **PHP 8.4.** Extraer `php-8.4.24-Win32-vs17-x64` en `C:\laragon\bin\php\`. Copiar
   `php.ini-development` a `php.ini` y habilitar: `curl fileinfo gd intl mbstring openssl
   pdo_mysql zip opcache sodium sqlite3 pdo_sqlite`.
   **`intl` no es opcional:** la interfaz es bilingüe.

2. **Arrancar MySQL 8.4**, desde Laragon o a mano:

   ```
   C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqld.exe --defaults-file=C:\laragon\bin\mysql\mysql-8.4.3-winx64\my.ini
   ```

3. **Crear base y usuario de la aplicación:**

   ```sql
   CREATE DATABASE pm_ariel      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE DATABASE pm_ariel_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'pm_ariel'@'127.0.0.1' IDENTIFIED BY '<contraseña>';
   GRANT ALL PRIVILEGES ON pm_ariel.*      TO 'pm_ariel'@'127.0.0.1';
   GRANT ALL PRIVILEGES ON pm_ariel_test.* TO 'pm_ariel'@'127.0.0.1';
   ```

4. **Dependencias y configuración:**

   ```
   composer install
   copy .env.example .env      (y completar DB_PASSWORD)
   php artisan key:generate
   php artisan migrate
   ```

5. **Levantar:** `php artisan serve`

La aplicación **nunca** se conecta como `root`. El usuario `pm_ariel` tiene permisos
únicamente sobre sus dos esquemas.

---

## Respaldo y restauración

Mientras no exista un repositorio remoto, este comando es la única copia del historial.

### Respaldar

```
php artisan backup:run
```

Produce un `.zip` fechado con tres componentes:

| Componente | Qué contiene |
|---|---|
| `database.sql` | Volcado con `--single-transaction`, rutinas y disparadores |
| `files/` | El contenido de `storage/app` |
| `repository.bundle` | El historial completo de git, empacado con `git bundle --all` |

Configuración en `config/backup.php` y `.env`:

| Variable | Para qué |
|---|---|
| `BACKUP_DESTINATION` | Ruta absoluta de destino. **Apuntar a un disco distinto al de la aplicación** |
| `BACKUP_KEEP` | Cuántos archivos conservar. La poda ocurre solo tras un respaldo exitoso |
| `BACKUP_MYSQLDUMP_PATH` | Ruta a `mysqldump` |

Programado a las 02:00 en `routes/console.php`, sin traslape.

### Restaurar — probado, no supuesto

1. **Extraer el archivo:**
   `Expand-Archive backup_AAAA-MM-DD_HHMMSS.zip -DestinationPath restore\`

2. **Base de datos:**

   ```
   mysql -u root -P 3307 -e "CREATE DATABASE pm_ariel_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -P 3307 pm_ariel_restore < restore\database.sql
   ```

3. **Archivos:** copiar `restore\files\storage_app\` sobre `storage\app\`

4. **Repositorio** — recupera todo el historial, no solo la última versión:

   ```
   git clone restore\repository.bundle pm-ariel-recuperado
   git bundle verify restore\repository.bundle
   ```

**Verificado el 2026-08-16:** restauración completa en un esquema limpio — 9 de 9 tablas,
migraciones íntegras, y `git bundle verify` confirmando historial completo.

---

## Pruebas

```
php artisan test
```

**La suite corre contra MySQL, no contra SQLite en memoria.** El motor de programación y las
consultas de reporte dependen del comportamiento real de la base; una suite sobre otro motor
demuestra que el código funciona en otro lado. La base de pruebas es `pm_ariel_test`.

---

## Convenciones

- **PSR-12**, sin prefijo `_` en propiedades ni métodos. Excepción autorizada y acotada a este
  repositorio frente a `php-dev-standards.md` — ver D-003 en la planeación.
- Nombres de archivo: clases PHP en PSR-4 · Blade en kebab-case · migraciones en formato
  Laravel · assets en kebab-case.
- Controladores delgados. La lógica de negocio vive en Services y Actions; los cálculos de
  programación, en clases de dominio puras sin dependencia de Laravel.
- Autorización solo por Policies. Ocultar un botón no es un permiso.
- **Ninguna función específica de un motor de base de datos.** Todo por el constructor de
  consultas y las migraciones. El motor de producción aún no está confirmado.
- Toda fecha en UTC en la base. Ninguna suma directa de días: la aritmética de fechas pasa por
  el calendario laboral.
- Cero cadenas de interfaz escritas duro: todo pasa por el sistema de traducción.
- Commits atómicos con mensaje convencional (`feat:`, `fix:`, `refactor:`, `test:`, `docs:`,
  `chore:`).
