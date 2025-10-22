<?php

if ( !is_user_logged_in() ) {
    echo '<p>debes iniciar sesion para ver tu informacion de membresia</p>';
    return;
}

$user_id  = get_current_user_id();

//Datos de la membresia
$activa = get_user_meta( $user_id, '_mdd_membresia_activa', true );
$inicio = get_user_meta( $user_id, '_mdd_membresia_inicio', true );
$expira = get_user_meta( $user_id, '_mdd_membresia_expiracion', true );
$duracion = get_user_meta( $user_id, '_mdd_duracion_membresia', true );
$producto_id = get_user_meta( $user_id, '_mdd_producto_id', true );

$limite = get_post_meta( '$producto_id', '_mdd_descargas_por_dia', true );
if ( $limite <= 0 ) $limite = 5;

//descargas del dia
$fecha_hoy = current_time( 'Y-m-d' );
//_mdd_descargas_fecha guarda la fecha del último reinicio de contador de descargas
$fecha_reg = get_user_meta( $user_id, '_mdd_descargas_fecha', true );
$usadas = ( $fecha_hoy === $fecha_reg ) ? (int) get_user_meta( $user_id, '_mdd_descargas_hechas_hoy', true ) : 0;
$restantes = max( 0, $limite - $usadas );

//verificar si está expirada
$expirada = ( $expira && current_time('timestamp') > $expira );

//Mostar resultados al usuario
if ( !$activa || $expirada) {
    echo '<p><strong>Tu membresia no eta activa o ha expirado</strong></p>';
    if ( $expira ) {
        echo '<p>Expiró el: <strong>' . date_i18n( 'd M Y', $expira ) . '</strong></p>';
    }
    return;
}

echo '<h3>Tu membresia está activa</h3>';

echo '<ul>';
echo '<li><strong>Duración (meses):</strong> ' . $duracion . '</li>';
echo '<li><strong>Inicio:</strong> ' . date_i18n( 'd M Y', $inicio ) . '</li>';
echo '<li><strong>Expira:</strong> ' . date_i18n( 'd M Y', $expira ) . '</li>';
echo '<li><strong>Descargas hoy:</strong> ' . $usadas . ' de ' . $limite . '</li>';
echo '<li><strong>Restantes hoy:</strong> ' . $restantes . '</li>';
echo '</ul>';

