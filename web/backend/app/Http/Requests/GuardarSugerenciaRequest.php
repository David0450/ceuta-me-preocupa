<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarSugerenciaRequest extends FormRequest
{
    protected $redirect = '/#formulario';

    public function authorize(): bool
    {
        // Formulario público: cualquiera puede enviar.
        return true;
    }

    public function rules(): array
    {
        return [
            'categoria_id' => ['required', 'integer', 'exists:categorias,id'],

            // Entre 1 y 2 subcategorías, sin repetir, y TODAS deben pertenecer
            // a la categoría elegida (así se hace cumplir el invariante
            // "una categoría, sus propias subcategorías").
            'subcategorias'   => ['required', 'array', 'min:1', 'max:2'],
            'subcategorias.*' => [
                'integer',
                'distinct',
                //TODO ¿Permitir subcategorias de distintas categorias?
                Rule::exists('subcategorias', 'id')
                    ->where('categoria_id', $this->input('categoria_id')),
            ],

            'zona'                         => ['nullable', 'string', 'max:255'],
            'descripcion'                  => ['required', 'string', 'max:1000'],
            'resultado_esperado'           => ['nullable', 'string', 'max:1000'],
            'organismo_sugerido_ciudadano' => ['nullable', 'string', 'max:255'],

            // Debe estar marcada para poder enviar.
            'acepta_privacidad' => ['accepted'],

            // Opcional. Si viene, tiene que ser un email o un teléfono español.
            //TODO Permitir otros prefijos.
            'contacto' => [
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $esEmail = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;

                    // Teléfono ES: 9 dígitos empezando en 6-9, con +34 y espacios opcionales.
                    $normalizado = preg_replace('/[\s-]/', '', (string) $value);
                    $esTelefono  = preg_match('/^(\+34)?[6-9]\d{8}$/', $normalizado) === 1;

                    if (! $esEmail && ! $esTelefono) {
                        $fail('El contacto debe ser un correo electrónico o un teléfono válido.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'categoria_id.required'      => 'Debes seleccionar una categoría.',
            'subcategorias.required'     => 'Selecciona al menos una subcategoría.',
            'subcategorias.min'          => 'Selecciona al menos una subcategoría.',
            'subcategorias.max'          => 'Puedes elegir como máximo dos subcategorías.',
            'descripcion.required'       => 'Describe tu preocupación o propuesta.',
            'descripcion.max'            => 'La descripción no puede superar los 1.000 caracteres.',
            'acepta_privacidad.accepted' => 'Debes aceptar la información de privacidad para continuar.',
        ];
    }
}
