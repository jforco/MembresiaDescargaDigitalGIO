<?php

//Control de descargas para usuarios con membresia

if ( !defined('ABSPATH') ) {
    exit;
}

function mdd_interceptar_descarga_producto() {

    $user_id = get_current_user_id();
    if (!$user_id)  wp_die( 'Debes iniciar sesion.'); 
        
    $producto_id = intval($_GET['mdd_descargar']);
    if (!$producto_id)  wp_die( 'Producto no válido.');

    if ( function_exists('mdd_es_producto_permitido_por_membresia') 
        && !mdd_es_producto_permitido_por_membresia($producto_id)) 
    {
        wp_die( 'Este producto no está disponible para descargar mediante membresia');
    }
    
    if ( !mdd_tiene_membresia_activa($user_id) )    wp_die( 'Tu membresia no está activa');
    
    $limite = mdd_get_limite_descargas_diaria($user_id); 
    $usados = mdd_get_cant_descargas_hechas_hoy($user_id);
    $ya_descargo = mdd_usuario_ya_descargo_el_producto($user_id, $producto_id);
    
    if (!$ya_descargo && $usados >= $limite) {
        wp_die( 'Has alcanzado tu limite de descargas por hoy.');
    }

    //Registrar descarga si nunca lo descargo y hay credito
    if (!$ya_descargo) {
        
        $fecha_hoy = current_time('Y-m-d');
        //_mdd_descargas_fecha guarda la fecha del último reinicio de contador de descargas
        $fecha_guardada = get_user_meta( $user_id, '_mdd_descargas_fecha', true );
        
        if ( $fecha_guardada !== $fecha_hoy ) {
            //es nuevo dia, se reinicia el contador de usados
            $usados = 0;
        }
        
        $usados++;
        //_mdd_descargas_fecha guarda la fecha del último reinicio de contador de descargas
        update_user_meta( $user_id, '_mdd_descargas_fecha', $fecha_hoy);
        update_user_meta( $user_id, '_mdd_descargas_hechas_hoy', $usados);
        
        mdd_registrar_producto_descargado($user_id, $producto_id);
    }

    //Redirigir la descarga real
    $producto = wc_get_product($producto_id);
    $files = $producto->get_downloads();

    if (!empty($files)) {
        $file = array_shift($files);
        $file_path = $file['file']; // URL del archivo

        // Convertir URL a ruta física en el servidor
        $relative_path = str_replace( site_url('/'), ABSPATH, $file_path );
        $real_path = realpath( $relative_path );

        if ( !$real_path || !file_exists($real_path) ) {
            wp_die('El archivo no existe en el servidor.');
        }

        // Forzar la descarga
        nocache_headers();
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($real_path) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . filesize($real_path));
        flush();
        readfile($real_path);

        exit;
    }

    wp_die('No hay archivos disponibles.');
}

add_action( 'init', function() {
    if (isset($_GET['mdd_descargar'])) {
        mdd_interceptar_descarga_producto();
    }
});
