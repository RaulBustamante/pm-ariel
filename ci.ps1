# Revision local completa. Corre lo mismo que correria un servidor de
# integracion, en el mismo orden, y no se detiene en la primera falla: la idea
# es ver todo lo que esta roto de una sola pasada, no de cuatro.
#
#   .\ci.ps1              todo
#   .\ci.ps1 -Fix         deja que Pint corrija el formato en vez de solo reclamar
#   .\ci.ps1 -Only stan   una sola etapa: pint | stan | branding | test
#
# PHP no vive en el PATH de esta maquina. Se resuelve en este orden:
#   1. la variable de entorno PM_ARIEL_PHP
#   2. el `php` del PATH, si es 8.3 o mayor
#   3. el PHP de Laragon
# Si un dia cambia la ruta de Laragon, se ajusta $FallbackPhp y ya.

[CmdletBinding()]
param(
    [switch] $Fix,
    [ValidateSet('pint', 'stan', 'branding', 'assets', 'test')]
    [string] $Only
)

$ErrorActionPreference = 'Stop'
Set-Location -Path $PSScriptRoot

$FallbackPhp = 'C:\laragon\bin\php\php-8.4.24-Win32-vs17-x64\php.exe'

function Resolve-Php {
    if ($env:PM_ARIEL_PHP) {
        if (-not (Test-Path $env:PM_ARIEL_PHP)) {
            throw "PM_ARIEL_PHP apunta a $($env:PM_ARIEL_PHP), que no existe."
        }
        return $env:PM_ARIEL_PHP
    }

    $onPath = Get-Command php -ErrorAction SilentlyContinue
    if ($onPath) {
        $version = & $onPath.Source -r 'echo PHP_VERSION;'
        if ([version]($version -split '-')[0] -ge [version]'8.3.0') {
            return $onPath.Source
        }
        Write-Host "  (el php del PATH es $version, muy viejo; se usa el de Laragon)" -ForegroundColor DarkGray
    }

    if (-not (Test-Path $FallbackPhp)) {
        throw "No se encontro un PHP 8.3+. Define PM_ARIEL_PHP con la ruta al ejecutable."
    }

    return $FallbackPhp
}

$php = Resolve-Php
$results = [ordered] @{}

function Invoke-Stage {
    param([string] $Key, [string] $Title, [string[]] $Arguments)

    if ($Only -and $Only -ne $Key) { return }

    Write-Host ""
    Write-Host "=== $Title ===" -ForegroundColor Cyan

    & $php @Arguments
    $code = $LASTEXITCODE

    $results[$Title] = $code
    if ($code -eq 0) {
        Write-Host "--- $Title : bien" -ForegroundColor Green
    } else {
        Write-Host "--- $Title : FALLA (codigo $code)" -ForegroundColor Red
    }
}

Write-Host "PHP: $php" -ForegroundColor DarkGray

$pintArgs = if ($Fix) { @('vendor/bin/pint') } else { @('vendor/bin/pint', '--test') }

Invoke-Stage -Key 'pint'     -Title 'Formato (Pint)'          -Arguments $pintArgs
Invoke-Stage -Key 'stan'     -Title 'Analisis estatico (PHPStan)' -Arguments @('-d', 'memory_limit=1G', 'vendor/bin/phpstan', 'analyse', '--no-progress')
Invoke-Stage -Key 'branding' -Title 'Marca fuera del codigo'  -Arguments @('artisan', 'branding:verify')

# Tailwind compila las clases que encuentra en las plantillas. Si se agrega una
# pantalla y no se recompila, la pantalla existe y se ve cruda — que es igual de
# inutil que no tenerla, y mas confuso, porque parece que no se construyo nada.
if (-not $Only -or $Only -eq 'assets') {
    Write-Host ""
    Write-Host "=== Estilos compilados al dia ===" -ForegroundColor Cyan

    $built = Get-ChildItem -Path "public\build\assets" -Filter "app-*.css" -ErrorAction SilentlyContinue |
        Sort-Object LastWriteTime -Descending | Select-Object -First 1

    $sources = Get-ChildItem -Path "resources" -Recurse -Include "*.blade.php", "*.css", "*.js" -ErrorAction SilentlyContinue |
        Sort-Object LastWriteTime -Descending | Select-Object -First 1

    if (-not $built) {
        Write-Host "  No hay CSS compilado. Corre: npm run build" -ForegroundColor Red
        $results['Estilos compilados al dia'] = 1
    } elseif ($sources -and $sources.LastWriteTime -gt $built.LastWriteTime) {
        Write-Host ("  {0} cambio despues de compilar. Corre: npm run build" -f $sources.Name) -ForegroundColor Red
        $results['Estilos compilados al dia'] = 1
    } else {
        Write-Host "  Al dia." -ForegroundColor DarkGray
        $results['Estilos compilados al dia'] = 0
    }

    if ($results['Estilos compilados al dia'] -eq 0) {
        Write-Host "--- Estilos compilados al dia : bien" -ForegroundColor Green
    } else {
        Write-Host "--- Estilos compilados al dia : FALLA" -ForegroundColor Red
    }
}

Invoke-Stage -Key 'test'     -Title 'Pruebas'                 -Arguments @('artisan', 'test')

Write-Host ""
Write-Host "=== Resumen ===" -ForegroundColor Cyan

$failed = 0
foreach ($entry in $results.GetEnumerator()) {
    if ($entry.Value -eq 0) {
        Write-Host ("  bien   {0}" -f $entry.Key) -ForegroundColor Green
    } else {
        Write-Host ("  FALLA  {0}" -f $entry.Key) -ForegroundColor Red
        $failed++
    }
}

if ($failed -gt 0) {
    Write-Host ""
    Write-Host "$failed de $($results.Count) etapas fallaron." -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "Las $($results.Count) etapas pasaron." -ForegroundColor Green
exit 0
