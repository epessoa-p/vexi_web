<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Valores por defecto de una empresa nueva
    |--------------------------------------------------------------------------
    |
    | Vexi es multipaís: cada empresa guarda su propio país, moneda, zona
    | horaria y etiqueta de identificador fiscal. Estos son los valores que se
    | proponen al dar de alta una empresa desde el panel del superadmin.
    |
    */

    'default_country'  => env('VEXI_COUNTRY', 'BO'),
    'default_currency' => env('VEXI_CURRENCY', 'BOB'),
    'default_timezone' => env('VEXI_TIMEZONE', 'America/La_Paz'),

    /*
    |--------------------------------------------------------------------------
    | Catálogo de países
    |--------------------------------------------------------------------------
    |
    | Por cada país: cómo se llama su identificador fiscal, qué moneda usa por
    | defecto y en qué zona horaria opera. Al elegir país en el formulario de
    | empresa, los otros tres campos se autocompletan con estos valores.
    |
    */

    'countries' => [
        'BO' => ['name' => 'Bolivia',     'tax_label' => 'NIT',  'currency' => 'BOB', 'timezone' => 'America/La_Paz'],
        'PE' => ['name' => 'Perú',        'tax_label' => 'RUC',  'currency' => 'PEN', 'timezone' => 'America/Lima'],
        'CL' => ['name' => 'Chile',       'tax_label' => 'RUT',  'currency' => 'CLP', 'timezone' => 'America/Santiago'],
        'CO' => ['name' => 'Colombia',    'tax_label' => 'NIT',  'currency' => 'COP', 'timezone' => 'America/Bogota'],
        'MX' => ['name' => 'México',      'tax_label' => 'RFC',  'currency' => 'MXN', 'timezone' => 'America/Mexico_City'],
        'AR' => ['name' => 'Argentina',   'tax_label' => 'CUIT', 'currency' => 'ARS', 'timezone' => 'America/Argentina/Buenos_Aires'],
        'EC' => ['name' => 'Ecuador',     'tax_label' => 'RUC',  'currency' => 'USD', 'timezone' => 'America/Guayaquil'],
        'SV' => ['name' => 'El Salvador', 'tax_label' => 'NIT',  'currency' => 'USD', 'timezone' => 'America/El_Salvador'],
        'ES' => ['name' => 'España',      'tax_label' => 'NIF',  'currency' => 'EUR', 'timezone' => 'Europe/Madrid'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monedas
    |--------------------------------------------------------------------------
    |
    | Símbolo y número de decimales de cada moneda soportada. Lo consume
    | App\Support\Money para no escribir símbolos a mano en las vistas.
    |
    */

    'currencies' => [
        'BOB' => ['symbol' => 'Bs',  'decimals' => 2],
        'PEN' => ['symbol' => 'S/',  'decimals' => 2],
        'CLP' => ['symbol' => '$',   'decimals' => 0],
        'COP' => ['symbol' => '$',   'decimals' => 0],
        'MXN' => ['symbol' => '$',   'decimals' => 2],
        'ARS' => ['symbol' => '$',   'decimals' => 2],
        'USD' => ['symbol' => '$',   'decimals' => 2],
        'EUR' => ['symbol' => '€',   'decimals' => 2],
    ],

    /*
    |--------------------------------------------------------------------------
    | Control de suscripción
    |--------------------------------------------------------------------------
    |
    | Con false, el middleware EnsureSubscriptionActive deja pasar todo. Útil
    | en desarrollo o en una instalación on-premise de un solo cliente.
    |
    */

    'require_subscription' => env('VEXI_REQUIRE_SUBSCRIPTION', true),

    /*
    |--------------------------------------------------------------------------
    | Agenda
    |--------------------------------------------------------------------------
    |
    | Franja visible del calendario y duración de una cita cuando el servicio
    | no define la suya. Pasarán a ser configurables por veterinaria (§15.4).
    |
    */

    'agenda' => [
        'slot_min_time' => '07:00:00',
        'slot_max_time' => '21:00:00',
        'default_duration' => 30,   // minutos
    ],

    /*
    |--------------------------------------------------------------------------
    | Desparasitación y alertas sanitarias
    |--------------------------------------------------------------------------
    |
    | Días sugeridos hasta la próxima desparasitación (se pueden cambiar en
    | cada registro) y ventana de "próximas" del calendario sanitario.
    |
    */

    'deworming' => [
        'internal' => 90,
        'external' => 30,
    ],

    'health_alert_days' => 15,

    /*
    |--------------------------------------------------------------------------
    | Inventario
    |--------------------------------------------------------------------------
    |
    | Días de anticipación con que un lote aparece como "próximo a vencer".
    |
    */

    'expiry_alert_days' => 60,

];
