<?php

// Traduce los códigos numéricos que Delvis escribe en sus bitácoras.
// Fuente: enums FaultCode y FaultGroupType de SPS2 (Delvis 1.14). El orden
// es el del enum: el número del log es la posición en la lista.
class CatalogoDelvis
{
    private const FALLAS = [
        0 => 'Sin falla', 1 => 'Bus CAN', 2 => 'Placa de E/S', 3 => 'Presión de aire',
        4 => 'Alimentación de cámaras', 5 => 'Alimentación de iluminación',
        6 => 'Alimentación de indicadores LED', 7 => 'Alimentación de control de eyectores',
        8 => 'Alimentación del motor de selección', 9 => 'Eyectores (banco derecho)',
        10 => 'Advertencia: temperatura de electrónica frontal', 11 => 'Advertencia: temperatura ambiente',
        12 => 'Visor abierto', 13 => 'Iluminación frontal del producto', 14 => 'Iluminación trasera del producto',
        15 => 'Iluminación frontal del fondo', 16 => 'Iluminación trasera del fondo',
        17 => 'Falla de LED del producto', 18 => 'Alimentador', 19 => 'Tasa de rechazo baja',
        20 => 'Tasa de rechazo alta', 21 => 'Limpiador (wiper)', 22 => 'Motor de selección',
        23 => 'Cámara frontal', 24 => 'Cámara trasera', 25 => 'Calentamiento de iluminación',
        26 => 'Alimentación de accionamiento de eyectores', 27 => 'Advertencia: LED del fondo',
        28 => 'Falla: LED del fondo', 29 => 'Advertencia: filtro de aire',
        30 => 'Advertencia: temperatura del visor frontal', 31 => 'Advertencia: temperatura del visor trasero',
        32 => 'Interbloqueo del cliente', 33 => 'Advertencia de inicialización',
        34 => 'Disco USB de respaldo no encontrado', 35 => 'Disco de usuario no encontrado',
        36 => 'Error de escritura en USB', 37 => 'Demasiados discos de respaldo',
        38 => 'Eyectores (banco izquierdo)', 39 => 'Error de sincronía de cámaras',
        40 => 'Advertencia: temperatura de electrónica trasera', 41 => 'Pérdida de energía principal',
        42 => 'Batería del UPS en mal estado', 43 => 'UPS no conectado',
        44 => 'Alineación de cámara 0', 45 => 'Alineación de cámara 1',
        46 => 'Alineación de cámara 2', 47 => 'Alineación de cámara 3',
        48 => 'Alimentador CAN', 49 => 'Sobredisparo de eyector', 50 => 'Contador de protección de eyectores',
        51 => 'Fuga de corriente alterna', 52 => 'Procesamiento de forma', 53 => 'Advertencia: procesamiento de forma',
        54 => 'Licencia de forma', 55 => 'Advertencia: temperatura de la PC (GUI)',
        56 => 'Enfriador de la PC poco confiable', 57 => 'Enfriador del visor frontal poco confiable',
        58 => 'Enfriador de electrónica frontal poco confiable', 59 => 'Enfriador del visor trasero poco confiable',
        60 => 'Enfriador de electrónica trasera poco confiable', 61 => 'Presión de enfriamiento',
        62 => 'Falló la prueba de memoria', 63 => 'Alimentación del enfriamiento',
    ];

    private const GRUPOS = [
        0 => 'Cámara', 1 => 'Motor de selección', 2 => 'Sistema de E/S', 3 => 'Suministro de aire',
        4 => 'Eyectores', 5 => 'Fuente del motor', 6 => 'Fuente de eyectores', 7 => 'Fuente de iluminación',
        8 => 'Iluminación del producto', 9 => 'Iluminación del fondo', 10 => 'Fuente de indicadores LED',
        11 => 'Fuente de cámaras', 12 => 'Temperatura', 13 => 'Alimentador', 14 => 'Tasa de rechazo',
        15 => 'LED individual', 16 => 'Visor abierto', 17 => 'Interbloqueo externo',
        18 => 'Advertencia de inicialización', 19 => 'Disco USB', 20 => 'Energía principal',
        21 => 'Sobredisparo de eyector', 22 => 'Fuga de corriente', 23 => 'Fuente de enfriamiento',
    ];

    public static function descripcion(string $tipo, ?string $codigo, ?string $detalle): string
    {
        $codigo  = (string)$codigo;
        $detalle = (string)$detalle;

        switch ($tipo) {
            case 'falla':
            case 'falla_ok':
                $p = explode(':', $codigo);
                $grupo = self::GRUPOS[(int)($p[0] ?? -1)] ?? ('Grupo ' . ($p[0] ?? '?'));
                $falla = self::FALLAS[(int)($p[1] ?? -1)] ?? ('Código ' . ($p[1] ?? '?'));
                return $falla . ' · ' . $grupo . ($detalle !== '' ? ' (' . $detalle . ')' : '');

            case 'ajuste':
                // Quita el nodo raíz del XML (SPS2.Globals.MachineSettings/...)
                $pos = strpos($codigo, '/');
                return $pos === false ? $codigo : substr($codigo, $pos + 1);

            case 'crash':
                return 'Cierre inesperado' . ($detalle !== '' ? ': ' . $detalle : '');

            default:
                return trim($codigo . ' ' . $detalle);
        }
    }

    // Etiqueta corta para mostrar en la línea de tiempo
    public static function etiqueta(string $tipo): string
    {
        $t = [
            'falla' => 'Falla', 'falla_ok' => 'Falla resuelta', 'ajuste' => 'Ajuste',
            'calibracion' => 'Calibración', 'crash' => 'Cierre inesperado',
            'mantenimiento' => 'Mantenimiento', 'aplicacion' => 'Aviso',
        ];
        return $t[$tipo] ?? $tipo;
    }
}
