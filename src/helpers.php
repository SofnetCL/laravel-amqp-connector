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
        return __DIR__ . "/../vendor/" . $path;
    }
}
