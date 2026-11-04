<?php

namespace App\Services\WhatsApp\Soporte;

/**
 * Infieres Señor/Señora por el nombre de pila. Si no se sabe, no inventa.
 */
class TratoCliente
{
    /** @var list<string> */
    private const FEMENINOS = [
        'maria', 'maría', 'ana', 'laura', 'lucia', 'lucía', 'sofia', 'sofía', 'carmen',
        'rosa', 'patricia', 'sandra', 'monica', 'mónica', 'veronica', 'verónica', 'silvia',
        'gabriela', 'andrea', 'paola', 'daniela', 'valeria', 'camila', 'juliana', 'carolina',
        'fernanda', 'alejandra', 'natalia', 'beatriz', 'elena', 'isabel', 'claudia', 'adriana',
        'lorena', 'vanesa', 'vanessa', 'jessica', 'jesica', 'karina', 'carina', 'liliana',
        'graciela', 'mirtha', 'norma', 'gloria', 'martha', 'marta', 'susana', 'teresa',
        'elizabeth', 'elisa', 'ines', 'inés', 'ruth', 'noelia', 'milagros', 'fatima', 'fátima',
        'rocio', 'rocío', 'yamila', 'jimena', 'ximena', 'melina', 'agustina', 'florencia',
        'celeste', 'daiana', 'dayana', 'belen', 'belén', 'magali', 'magalí', 'yanina',
        'nancy', 'doris', 'irene', 'olga', 'pilar', 'rebeca', 'ramona', 'estela', 'estella',
        'lidia', 'lidia', 'nilsa', 'nina', 'petra', 'zunilda', 'carmelina', 'dominga',
        'encarnacion', 'encarnación', 'francisca', 'juana', 'josefa', 'catalina', 'antonia',
    ];

    /** @var list<string> */
    private const MASCULINOS = [
        'jose', 'josé', 'juan', 'carlos', 'luis', 'miguel', 'pedro', 'jorge', 'manuel',
        'francisco', 'antonio', 'ramon', 'ramón', 'roberto', 'ricardo', 'fernando', 'diego',
        'daniel', 'david', 'martin', 'martín', 'pablo', 'sergio', 'andres', 'andrés', 'raul',
        'raúl', 'oscar', 'óscar', 'hugo', 'julio', 'alberto', 'eduardo', 'enrique', 'victor',
        'víctor', 'gustavo', 'hector', 'héctor', 'cesar', 'césar', 'mario', 'ruben', 'rubén',
        'nelson', 'walter', 'wilfrido', 'wilberto', 'carmelo', 'domingo', 'rufino', 'dario',
        'darío', 'fabian', 'fabián', 'german', 'germán', 'ivan', 'iván', 'lucas', 'luca',
        'matias', 'matías', 'nicolas', 'nicolás', 'sebastian', 'sebastián', 'tomas', 'tomás',
        'gabriel', 'alejandro', 'alexander', 'joshua', 'jonathan', 'kevin', 'brian', 'bryan',
        'cristian', 'christian', 'esteban', 'federico', 'gonzalo', 'hernán', 'hernan',
        'ignacio', 'javier', 'leonardo', 'mauricio', 'maximiliano', 'oscar', 'rodrigo', 'derlis',
        'santiago', 'vicente', 'benjamin', 'benjamín', 'joaquin', 'joaquín',
    ];

    /** Nombres en -a que no son femeninos. */
    private const MASCULINOS_EN_A = [
        'joshua', 'luca', 'nicola', 'borja', 'guia', 'guía',
    ];

    /**
     * @return array{trato: ?string, vocativo: ?string, nombre_corto: ?string}
     */
    public static function resolver(?string $nombre, ?string $apellido = null): array
    {
        $nombreCorto = self::primerNombre($nombre);
        $apellidoVisible = self::primerApellido($apellido);
        $genero = self::generoDe($nombreCorto);

        $trato = match ($genero) {
            'f' => 'Sra.',
            'm' => 'Sr.',
            default => null,
        };

        $vocativo = null;
        if ($trato !== null) {
            $cola = $apellidoVisible ?: $nombreCorto;
            $vocativo = $cola ? "{$trato} {$cola}" : $trato;
        }

        return [
            'trato' => $trato,
            'vocativo' => $vocativo,
            'nombre_corto' => $nombreCorto,
        ];
    }

    public static function vocativo(?string $nombre, ?string $apellido = null): ?string
    {
        return self::resolver($nombre, $apellido)['vocativo'];
    }

    public static function generoDe(?string $nombrePila): ?string
    {
        $n = self::norm($nombrePila);
        if ($n === '') {
            return null;
        }
        if (in_array($n, self::FEMENINOS, true)) {
            return 'f';
        }
        if (in_array($n, self::MASCULINOS, true) || in_array($n, self::MASCULINOS_EN_A, true)) {
            return 'm';
        }
        if (str_ends_with($n, 'a') && ! in_array($n, self::MASCULINOS_EN_A, true)) {
            return 'f';
        }
        if (preg_match('/(?:o|os|an|on|ón|en|el|iel|uel|iro|ino|ardo|erto|usto)$/u', $n)) {
            return 'm';
        }

        return null;
    }

    public static function primerNombre(?string $nombre): ?string
    {
        $nombre = trim((string) $nombre);
        if ($nombre === '') {
            return null;
        }
        $primero = preg_split('/\s+/u', $nombre)[0] ?? '';
        $primero = trim((string) $primero, '.,');
        if ($primero === '') {
            return null;
        }

        return mb_convert_case(mb_strtolower($primero), MB_CASE_TITLE, 'UTF-8');
    }

    public static function primerApellido(?string $apellido): ?string
    {
        $apellido = trim((string) $apellido);
        if ($apellido === '') {
            return null;
        }
        $primero = preg_split('/\s+/u', $apellido)[0] ?? '';
        $primero = trim((string) $primero, '.,');
        if ($primero === '') {
            return null;
        }

        return mb_convert_case(mb_strtolower($primero), MB_CASE_TITLE, 'UTF-8');
    }

    private static function norm(?string $texto): string
    {
        return mb_strtolower(trim((string) $texto));
    }
}
