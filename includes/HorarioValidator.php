<?php
/**
 * validaciones centralizadas para importacion de horarios
 * solo logica de validacion, sin acceso a base de datos
 */

class HorarioValidator
{
    private const DIAS_VALIDOS = [
        'lunes' => 'Lunes',
        'martes' => 'Martes',
        'miercoles' => 'Miércoles',
        'miércoles' => 'Miércoles',
        'jueves' => 'Jueves',
        'viernes' => 'Viernes',
        'sabado' => 'Sábado',
        'sábado' => 'Sábado',
        'domingo' => 'Domingo'
    ];

    /**
     * normaliza el nombre del dia
     * @return string|null dia normalizado o null si invalido
     */
    public static function normalizarDia(string $dia): ?string
    {
        $key = strtolower(trim($dia));
        return self::DIAS_VALIDOS[$key] ?? null;
    }

    /**
     * normaliza hora desde texto, time o datetime de excel
     * @return string|null formato HH:MM o null si invalido
     */
    public static function normalizarHora($valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        // si ya es objeto DateTime (excel)
        if ($valor instanceof \DateTime) {
            return $valor->format('H:i');
        }

        // si es string, limpiar y validar
        $str = trim((string)$valor);

        // formato HH:MM o HH:MM:SS
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $str, $matches)) {
            $h = (int)$matches[1];
            $m = (int)$matches[2];
            if ($h >= 0 && $h <= 23 && $m >= 0 && $m <= 59) {
                return sprintf('%02d:%02d', $h, $m);
            }
            return null;
        }

        // numero serial de excel (dias desde 1900-01-01)
        if (is_numeric($str)) {
            $fecha = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$str);
            if ($fecha) {
                return $fecha->format('H:i');
            }
        }

        return null;
    }

    /**
     * valida que hora_fin > hora_inicio
     */
    public static function validarOrdenHoras(string $inicio, string $fin): bool
    {
        $ini = strtotime($inicio);
        $fn = strtotime($fin);
        return $fn > $ini;
    }

    /**
     * detecta solapamiento entre dos intervalos [iniA, finA) y [iniB, finB)
     * @param string $iniA HH:MM
     * @param string $finA HH:MM
     * @param string $iniB HH:MM
     * @param string $finB HH:MM
     * @return bool true si se solapan
     */
    public static function haySolapamiento(string $iniA, string $finA, string $iniB, string $finB): bool
    {
        $a1 = strtotime($iniA);
        $a2 = strtotime($finA);
        $b1 = strtotime($iniB);
        $b2 = strtotime($finB);
        return $a1 < $b2 && $b1 < $a2;
    }

    /**
     * detecta fila completamente vacia
     */
    public static function esFilaVacia(array $fila, array $headers): bool
    {
        foreach ($headers as $h) {
            $val = $fila[$h] ?? '';
            if ($val !== null && $val !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * valida encabezados requeridos (case-insensitive, trim)
     * @param array $headersEncontrados
     * @return array ['ok' => bool, 'faltantes' => array, 'extras' => array]
     */
    public static function validarEncabezados(array $headersEncontrados): array
    {
        $requeridos = [
            'dia',
            'hora_inicio',
            'hora_fin',
            'identificacion_instructor',
            'ambiente'
        ];

        $normalizados = [];
        foreach ($headersEncontrados as $h) {
            $normalizados[strtolower(trim($h))] = true;
        }

        $faltantes = [];
        foreach ($requeridos as $req) {
            if (!isset($normalizados[$req])) {
                $faltantes[] = $req;
            }
        }

        // al menos uno de id_ficha o numero_ficha
        $tieneIdFicha = isset($normalizados['id_ficha']);
        $tieneNumFicha = isset($normalizados['numero_ficha']);

        if (!$tieneIdFicha && !$tieneNumFicha) {
            $faltantes[] = 'id_ficha o numero_ficha';
        }

        return [
            'ok' => empty($faltantes),
            'faltantes' => $faltantes,
            'tiene_id_ficha' => $tieneIdFicha,
            'tiene_numero_ficha' => $tieneNumFicha
        ];
    }

    /**
     * normaliza clave de encabezado para busqueda en fila
     */
    public static function normalizarClave(string $header): string
    {
        return strtolower(trim($header));
    }

    /**
     * normaliza ambiente para comparacion (trim, lowercase)
     */
    public static function normalizarAmbiente(string $ambiente): string
    {
        return strtolower(trim($ambiente));
    }

    /**
     * normaliza hora a HH:MM:SS para comparacion lexicografica segura
     * @return string|null HH:MM:SS o null si invalido
     */
    public static function normalizarHoraSegura($valor): ?string
    {
        $hm = self::normalizarHora($valor);
        if (!$hm) {
            return null;
        }
        return $hm . ':00';
    }

    /**
     * detecta solapamiento usando comparacion lexicografica de HH:MM:SS
     * evita strtotime
     */
    public static function haySolapamientoSeguro(string $iniA, string $finA, string $iniB, string $finB): bool
    {
        return $iniA < $finB && $iniB < $finA;
    }

    /**
     * verifica si dos ambientes coinciden (case-insensitive, trim)
     */
    public static function ambientesCoinciden(string $a, string $b): bool
    {
        return self::normalizarAmbiente($a) === self::normalizarAmbiente($b);
    }
}