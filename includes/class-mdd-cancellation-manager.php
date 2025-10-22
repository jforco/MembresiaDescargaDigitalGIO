<?php


 //Para desactivar membresías cuando: un pedido es cancelado o es reembolsado

if ( !defined('ABSPATH') ) {
    exit; 
}

//se ejecuta cuando el estado del pedido cambia a cancelled
add_action( 'woocommerce_order_status_cancelled', 'mdd_desactivar_membresia_por_cancelacion' );
//se ejecuta cuando el estado del pedido cambia a refunded
add_action( 'woocommerce_order_status_refunded', 'mdd_desactivar_membresia_por_cancelacion' );


//Desactivar la membresía del usuario cuando se cancela un pedido 
function mdd_desactivar_membresia_por_cancelacion( $order_id ) { //id del pedido cancelado
    
    //Obtener el objeto del pedido
    $order = wc_get_order( $order_id );
    if ( !$order )  return;  //si no existe el pedido entoncs return 

    $user_id = $order->get_user_id();
    if ( !$user_id )    return; // Salir si no hay usuario

    //Ver si el hay productos de membresia en el pedido
    $contiene_membresia = false;
    $productos_membresia = array(); // Guardar los IDs de productos de membresía
    
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        
        // Verificar si este producto tiene duración de membresía configurada
        $duracion_meses = (int) get_post_meta( $product_id, '_mdd_duracion_membresia', true );
        
        if ( $duracion_meses > 0 ) {
            $contiene_membresia = true;
            $productos_membresia[] = $product_id;
        }
    }
  
    if ( !$contiene_membresia ) { //si no hay productos de membresia no hacer nada
        return;
    }

    // llamamos a desactivar membresía
    mdd_revertir_membresia_usuario( $user_id, $productos_membresia, $order_id );
}

/**
 * Revierte la membresía del usuario, desactivándola y limpiando su historial
 * $productos_membresia Array con IDs de productos de membresía del pedido
 */
function mdd_revertir_membresia_usuario( $user_id, $productos_membresia, $order_id ) {
    
    // 1. Obtener datos actuales de la membresía
    $membresia_activa = get_user_meta( $user_id, '_mdd_membresia_activa', true );
    $producto_membresia_actual = get_user_meta( $user_id, '_mdd_producto_id', true );

    if ( !$membresia_activa )   return; 
    
    //verificar si la membresía actual corresponde a alguno de los productos cancelados
    if ( !in_array( $producto_membresia_actual, $productos_membresia ) ) {
        return; 
    }

    //Desactivar la membresía (método: poner fecha de expiración en el pasado)
    $ayer = current_time( 'timestamp' ) - (24 * 60 * 60); // Fecha de ayer (24 horas en segundos)
    
    // Actualizar metadatos del usuario
    update_user_meta( $user_id, '_mdd_membresia_activa', false );
    update_user_meta( $user_id, '_mdd_membresia_expiracion', $ayer );
    
    // 5. Limpiar historial de descargas del usuario
    delete_user_meta( $user_id, '_mdd_productos_descargados' );
    delete_user_meta( $user_id, '_mdd_descargas_hechas_hoy' );
    delete_user_meta( $user_id, '_mdd_descargas_fecha' );

    // 6. Log de la acción (opcional, para debugging)
    // Esto es útil para hacer seguimiento de las cancelaciones
    $log_message = sprintf(
        'Membresía desactivada para usuario %d debido a cancelación del pedido %d. Productos afectados: %s',
        $user_id,
        $order_id,
        implode(', ', $productos_membresia)
    );
    
    // Escribir en log de WordPress (si está habilitado el debugging)
    error_log( '[MDD Cancelación] ' . $log_message );

    // 7. Hook personalizado para extender funcionalidad
    // Otros plugins o temas pueden usar este hook para hacer acciones adicionales
    do_action( 'mdd_membresia_cancelada', $user_id, $productos_membresia, $order_id );
}

