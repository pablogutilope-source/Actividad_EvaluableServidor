<?php
declare(strict_types=1);

/**
 * index.php
 * Punto de entrada. Flujo:
 *   1. Valida la petición (id, cliente, servicio, página).
 *   2. Prepara el catálogo de recambios (recargo, stock, valoración, paginación).
 *   3. Crea la orden de trabajo y calcula el presupuesto.
 *   4. Genera el HTML en un búfer de salida y lo envía.
 *
 * Parámetros GET: id (obligatorio), cliente, servicio, pagina.
 * Ejemplo: index.php?id=7&cliente=José&servicio=bateria&pagina=2
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/OrdenTrabajo.php';

// ===========================================================================
// Bloque 1: validación HTTP e identificación del usuario
// ===========================================================================

// `id` debe ser un entero >= 1. filter_input devuelve null si falta
// y false si no es válido; en ambos casos respondemos 400.
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === null || $id === false) {
    abortar(400, 'Error 400: el parámetro "id" es obligatorio y debe ser un entero positivo mayor que cero.');
}

// Nombre del cliente: si no llega o está vacío, se usa el valor genérico.
$clienteRecibido = trim((string) filter_input(INPUT_GET, 'cliente'));
// mb_* respetan tildes y caracteres especiales (strtoupper/strlen no).
$cliente = mb_strtoupper($clienteRecibido !== '' ? $clienteRecibido : CLIENTE_POR_DEFECTO);
$longitudCliente = mb_strlen($cliente);

// Servicio: tryFrom devuelve null si el valor no existe; entonces, Pantalla.
$servicio = TipoServicio::tryFrom((string) filter_input(INPUT_GET, 'servicio')) ?? TipoServicio::Pantalla;

// ===========================================================================
// Bloque 3: catálogo de recambios, inventario y paginación
// ===========================================================================

// Catálogo de ejemplo: nombre, tipo de servicio al que pertenece, precio base y stock.
$catalogo = [
    ['nombre' => 'Pantalla OLED 6.1"',      'tipo' => TipoServicio::Pantalla,  'precio' => 89.90,  'stock' => 4],
    ['nombre' => 'Pantalla LCD 5.8"',       'tipo' => TipoServicio::Pantalla,  'precio' => 59.90,  'stock' => 0],
    ['nombre' => 'Batería 3000 mAh',        'tipo' => TipoServicio::Bateria,   'precio' => 24.50,  'stock' => 10],
    ['nombre' => 'Batería 4500 mAh',        'tipo' => TipoServicio::Bateria,   'precio' => 34.00,  'stock' => 6],
    ['nombre' => 'Placa base modelo A',     'tipo' => TipoServicio::PlacaBase, 'precio' => 210.00, 'stock' => 1],
    ['nombre' => 'Placa base modelo B',     'tipo' => TipoServicio::PlacaBase, 'precio' => 250.00, 'stock' => 0],
    ['nombre' => 'Conector de carga USB-C', 'tipo' => TipoServicio::PlacaBase, 'precio' => 8.75,   'stock' => 25],
    ['nombre' => 'Cámara trasera 12 MP',    'tipo' => TipoServicio::Pantalla,  'precio' => 39.00,  'stock' => 3],
];

// Recargo de almacenamiento: se modifica el array original mediante referencia (&).
// unset() es obligatorio: sin él, $pieza seguiría apuntando al último elemento
// y un foreach posterior con la misma variable lo sobrescribiría.
foreach ($catalogo as &$pieza) {
    $pieza['precio'] += RECARGO_ALMACENAMIENTO;
}
unset($pieza);

// Solo piezas con stock; array_values reindexa (0..n) para poder paginar.
$disponibles = array_values(array_filter($catalogo, static fn (array $p): bool => $p['stock'] > 0));

// Valor del almacén = suma de (precio × unidades) de las piezas disponibles.
$valorAlmacen = array_sum(array_map(static fn (array $p): float => $p['precio'] * $p['stock'], $disponibles));

// Página pedida (si falta o es inválida, 1); paginar() la acota al rango real.
$paginaSolicitada = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1;
$paginacion = paginar($disponibles, $paginaSolicitada, PIEZAS_POR_PAGINA);

// ===========================================================================
// Bloque 2: orden de trabajo y cálculo del presupuesto
// ===========================================================================

// Coste de recambios = precio (con recargo) de la primera pieza en stock
// del mismo tipo que el servicio. Si no hay ninguna, 0.
$costeRecambios = 0.0;
foreach ($disponibles as $p) {
    if ($p['tipo'] === $servicio) {
        $costeRecambios = $p['precio'];
        break;
    }
}

// Orden inmutable; argumentos nombrados para mayor claridad.
$orden = new OrdenTrabajo(
    id: $id,
    cliente: $cliente,
    servicio: $servicio,
    manoObra: $servicio->manoObra(),
    recambios: $costeRecambios,
);

$presupuesto = calcularPresupuesto(manoObra: $orden->manoObra, recambios: $orden->recambios);

// Filas del desglose (concepto => importe); la vista las recorre con foreach.
$desglose = [
    'Mano de obra'                      => $orden->manoObra,
    'Recambios'                         => $orden->recambios,
    'Base imponible'                    => $presupuesto['base'],
    'IVA (' . (int) (IVA * 100) . ' %)' => $presupuesto['iva'],
];

// Genera la URL de una página del catálogo conservando el resto de parámetros.
$enlacePagina = static fn (int $n): string => '?' . http_build_query([
    'id'       => $orden->id,
    'cliente'  => $clienteRecibido,
    'servicio' => $orden->servicio->value,
    'pagina'   => $n,
]);

// ===========================================================================
// Bloque 4: vista con búfer de salida, sintaxis alternativa y protección XSS
// ===========================================================================

// Todo lo que se imprima hasta ob_get_clean() se guarda en memoria en vez de enviarse.
// Notas de la vista:
//  - La etiqueta corta de salida (signo igual tras la apertura de PHP) equivale a echo.
//  - e() escapa todo dato de texto (XSS). Los enteros se imprimen sin escapar.
//  - Se usa la sintaxis alternativa (if: / foreach: / endif / endforeach).
ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TechFix · Presupuesto #<?= $orden->id ?></title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 760px; margin: 2rem auto; padding: 0 1rem; color: #1f2933; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { border: 1px solid #cbd2d9; padding: .5rem .75rem; text-align: left; }
        th { background: #f0f4f8; }
        td.num, th.num { text-align: right; }
        nav a { margin-right: .5rem; }
        .actual { font-weight: bold; }
    </style>
</head>
<body>
    <h1>Presupuesto #<?= $orden->id ?></h1>
    <p>
        Cliente: <strong><?= e($orden->cliente) ?></strong>
        (<?= $longitudCliente ?> caracteres)<br>
        Servicio: <?= e($orden->servicio->descripcion()) ?><br>
        Fecha: <?= date('d/m/Y H:i') ?>
    </p>

    <!-- Tabla de desglose del presupuesto -->
    <h2>Desglose</h2>
    <table>
        <tr><th>Concepto</th><th class="num">Importe</th></tr>
        <?php foreach ($desglose as $concepto => $importe): ?>
            <tr><td><?= e($concepto) ?></td><td class="num"><?= e(euros($importe)) ?></td></tr>
        <?php endforeach; ?>
        <tr><th>Total</th><th class="num"><?= e(euros($presupuesto['total'])) ?></th></tr>
    </table>

    <!-- Catálogo paginado de piezas con stock -->
    <h2>Catálogo de recambios disponibles</h2>
    <p>Valor total del almacén: <strong><?= e(euros($valorAlmacen)) ?></strong></p>

    <?php if ($paginacion['items'] === []): ?>
        <p>No hay piezas disponibles.</p>
    <?php else: ?>
        <table>
            <tr><th>Pieza</th><th>Tipo</th><th class="num">Precio</th><th class="num">Stock</th></tr>
            <?php foreach ($paginacion['items'] as $p): ?>
                <tr>
                    <td><?= e($p['nombre']) ?></td>
                    <td><?= e($p['tipo']->descripcion()) ?></td>
                    <td class="num"><?= e(euros($p['precio'])) ?></td>
                    <td class="num"><?= $p['stock'] ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <!-- Enlaces de paginación; la página actual va en negrita -->
    <nav aria-label="Paginación">
        Página <?= $paginacion['pagina'] ?> de <?= $paginacion['totalPaginas'] ?>:
        <?php for ($n = 1; $n <= $paginacion['totalPaginas']; $n++): ?>
            <a href="<?= e($enlacePagina($n)) ?>"<?= $n === $paginacion['pagina'] ? ' class="actual"' : '' ?>><?= $n ?></a>
        <?php endfor; ?>
    </nav>
</body>
</html>
<?php
// Recoge el HTML acumulado, cierra el búfer y lo envía al cliente.
$html = ob_get_clean();
echo $html;