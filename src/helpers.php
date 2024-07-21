<?php

// src/helpers.php

if (!function_exists('config_path')) {
    /**
     * Obtener la ruta del archivo de configuración.
     *
     * @param  string  $path
     * @return string
     */
    function config_path($path = '')
    {
        return __DIR__ . "/../config/" . $path;
    }
}

if (!function_exists('config')) {
    /**
     * Obtener el valor de configuración de la aplicación.
     *
     * @param  string  $key
     *
     * @return mixed
     */
    function config($key)
    {
        $config = require config_path('config.php');

        return $config[$key];
    }
}

if (!function_exists('base_path')) {
    /**
     * Obtener la ruta base del proyecto.
     *
     * @param  string  $path
     * @return string
     */
    function base_path($path = '')
    {
        return __DIR__ . DIRECTORY_SEPARATOR . $path;
    }
}
