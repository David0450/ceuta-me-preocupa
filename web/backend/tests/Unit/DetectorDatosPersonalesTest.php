<?php

namespace Tests\Unit;

use App\Support\DetectorDatosPersonales;
use PHPUnit\Framework\TestCase;

class DetectorDatosPersonalesTest extends TestCase
{
    public function test_it_detects_and_replaces_direct_identifiers(): void
    {
        $texto = 'Mi DNI es 12345678Z, NIE X1234567L, móvil 600 123 456, correo ana@ejemplo.es, '
            .'IBAN ES91 2100 0418 4502 0005 1332 y vivo en la calle Real 25.';

        $tipos = array_column(DetectorDatosPersonales::detectar($texto), 'tipo');

        $this->assertEqualsCanonicalizing(['dni', 'nie', 'telefono', 'email', 'iban', 'direccion'], $tipos);
        $this->assertSame([], DetectorDatosPersonales::detectar(DetectorDatosPersonales::sustituir($texto)));
    }

    public function test_it_ignores_ordinary_numbers(): void
    {
        $this->assertSame([], DetectorDatosPersonales::detectar('Llevamos desde 2024 con 3 farolas rotas y 150 vecinos afectados.'));
    }

    public function test_highlighting_escapes_html(): void
    {
        $html = DetectorDatosPersonales::resaltar('<script>x</script> 600123456');

        $this->assertStringStartsWith('&lt;script&gt;', $html);
        $this->assertStringContainsString('<mark', $html);
    }
}
