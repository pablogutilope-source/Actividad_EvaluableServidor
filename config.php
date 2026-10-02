<?php
declare(strict_types=1); // Tipado estricto: no hay conversiones automáticas de tipos.

/**
 * config.php
 * Configuración del entorno, constantes de negocio y funciones globales.
 * Se incluye desde index.php con require_once.
 */

// --- Entorno -------------------------------------------------------------

date_default_timezone_set('Europe/Madrid'); // Zona horaria oficial del servidor.
mb_internal_encoding('UTF-8');              // Las funciones mb_* trabajan en UTF-8.

// Entorno de pruebas: se muestran todos los errores y avisos en pantalla.
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// --- Constantes de negocio -----------------------------------------------

const IVA = 0.21;                    // 21 % de IVA.
const RECARGO_ALMACENAMIENTO = 2.50; // € que se suman al precio base de cada pieza.
const PIEZAS_POR_PAGINA = 3;         // Tamaño de página del catálogo.
const CLIENTE_POR_DEFECTO = 'Cliente Anónimo';

// --- Funciones globales --------------------------------------------------

/**
 * Calcula el presupuesto de una reparación aplicando IVA.
 *
 * @param float $manoObra  Coste de la mano de obra (€).
 * @param float $recambios Coste de los recambios (€).
 * @param float $iva       Tipo de IVA en tanto por uno (por defecto, IVA).
 * @return array{base: float, iva: float, total: float}
 *         base = mano de obra + recambios; iva = importe del impuesto; total = base + iva.
 */
function calcularPresupuesto(float $manoObra, float $recambios, float $iva = IVA): array
{
    $base = $manoObra + $recambios;
    $importeIva = $base * $iva;

    return [
        'base'  => $base,
        'iva'   => $importeIva,
        'total' => $base + $importeIva,
    ];
}

/**
 * Devuelve una página de una colección.
 * La página pedida se acota a [1, total de páginas], así que nunca falla
 * con valores como 0, -5 o 999.
 *
 * @param list<mixed> $items           Colección completa (índices 0..n).
 * @param int         $paginaSolicitada Página pedida por el usuario.
 * @param int         $porPagina       Elementos por página.
 * @return array{items: list<mixed>, pagina: int, totalPaginas: int}
 */
function paginar(array $items, int $paginaSolicitada, int $porPagina): array
{
    // max(1, …) garantiza al menos una página aunque la colección esté vacía.
    $totalPaginas = max(1, (int) ceil(count($items) / $porPagina));
    $pagina = min(max($paginaSolicitada, 1), $totalPaginas);

    return [
        'items'        => array_slice($items, ($pagina - 1) * $porPagina, $porPagina),
        'pagina'       => $pagina,
        'totalPaginas' => $totalPaginas,
    ];
}

/**
 * Escapa un texto para imprimirlo en HTML y evitar inyección XSS.
 * Convierte < > & " ' en entidades; ENT_SUBSTITUTE evita cadenas vacías
 * si el texto tiene UTF-8 inválido.
 */
function e(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Formatea un importe al estilo español: 1.234,50 € */
function euros(float $importe): string
{
    return number_format($importe, 2, ',', '.') . ' €';
}

/**
 * Detiene la ejecución con un código HTTP y un mensaje en texto plano.
 * El tipo de retorno `never` indica que la función no vuelve nunca.
 */
function abortar(int $codigoHttp, string $mensaje): never
{
    http_response_code($codigoHttp);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $mensaje;
    exit;
}