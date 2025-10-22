<?php
if ( ! defined('WP_UNINSTALL_PLUGIN') ) exit;

//borrar los metadatos de usuarios
$meta_keys = array(
  '_mdd_membresia_activa',
  '_mdd_membresia_inicio',
  '_mdd_membresia_expiracion',
  '_mdd_producto_id',
  '_mdd_duracion_membresia',
  '_mdd_descargas_por_dia',
  '_mdd_descargas_hechas_hoy',
  '_mdd_descargas_fecha',
  '_mdd_productos_descargados', 
);

global $wpdb;
foreach ( $meta_keys as $k ) {
  $wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
    $k
  ) );
}

// Borra term meta de categorías
delete_metadata( 'term', 0, 'mdd_excluir_membresia', '', true );