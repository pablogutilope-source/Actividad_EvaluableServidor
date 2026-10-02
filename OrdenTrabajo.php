<?php
declare(strict_types=1);

/**
 * OrdenTrabajo.php
 * Modelo del dominio: tipos de servicio (enum) y orden de trabajo inmutable.
 */

/**
 * Tipos de reparación. Es un enum "respaldado": cada caso tiene un valor
 * de texto (el que se recibe por URL, p. ej. ?servicio=bateria).
 */
enum TipoServicio: string
{
    case Pantalla  = 'pantalla';
    case Bateria   = 'bateria';
    case PlacaBase = 'placabase';

    /** Texto legible del servicio, elegido con una expresión `match`. */
    public function descripcion(): string
    {
        return match ($this) {
            self::Pantalla  => 'Sustitución de pantalla',
            self::Bateria   => 'Cambio de batería',
            self::PlacaBase => 'Reparación de placa base',
        };
    }

    /** Tarifa de mano de obra (€) asociada a cada tipo de servicio. */
    public function manoObra(): float
    {
        return match ($this) {
            self::Pantalla  => 45.00,
            self::Bateria   => 30.00,
            self::PlacaBase => 120.00,
        };
    }
}

/**
 * Orden de reparación inmutable: una vez creada no se puede modificar.
 * Las propiedades `readonly` se declaran y asignan directamente en la
 * firma del constructor (promoción de propiedades).
 */
final class OrdenTrabajo
{
    /**
     * @param int          $id        Número de solicitud o presupuesto.
     * @param string       $cliente   Nombre del cliente.
     * @param TipoServicio $servicio  Tipo de reparación.
     * @param float        $manoObra  Coste de mano de obra (€).
     * @param float        $recambios Coste de recambios (€).
     */
    public function __construct(
        public readonly int $id,
        public readonly string $cliente,
        public readonly TipoServicio $servicio,
        public readonly float $manoObra,
        public readonly float $recambios,
    ) {
    }
}