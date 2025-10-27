<?php
// Asegúrate de incluir la clase WP_List_Table antes de instanciarla
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

// Suponiendo que la clase MDD_Miembros_List_Table está cargada en alguna parte del plugin.
if ( ! class_exists( 'MDD_Admin_Pages' ) ) {
    class MDD_Admin_Pages {
        
        public function __construct() {
            // Hook para agregar la página al menú de administración
            add_action( 'admin_menu', array( $this, 'mdd_registrar_pagina_membresias' ) );
        }

        /**
         * Registra el submenú en el menú principal de WooCommerce.
         */
        public function mdd_registrar_pagina_membresias() {
            add_submenu_page(
                'woocommerce',
                __( 'Membresías Digitales', 'membresia-descarga-digital' ), 
                __( 'Membresías', 'membresia-descarga-digital' ),
                'manage_woocommerce',
                'mdd-gestionar-membresias',
                array( $this, 'mdd_mostrar_pagina_membresias' ) 
            );
        }

        public function mdd_mostrar_pagina_membresias() {
            // Asegúrate de que la clase de la tabla esté definida antes de usarla
            if ( ! class_exists( 'MDD_Miembros_List_Table' ) ) {
                // Aquí deberías incluir el archivo si no está cargado automáticamente.
                // require_once plugin_dir_path( __FILE__ ) . 'mdd-miembros-list-table.php';
                // Si la clase aún no está disponible, salimos.
                return; 
            }

            echo '<div class="wrap">';
            echo '<h2>' . esc_html__( 'Gestión de Membresías Digitales', 'membresia-descarga-digital' ) . '</h2>';
            
            // -----------------------------------------------------
            // 1. Instanciar la clase de la tabla
            $Miembros_Table = new MDD_Miembros_List_Table();
            
            // 2. Preparar los datos (obtener, ordenar, paginar)
            $Miembros_Table->prepare_items();
            
            // 3. Mostrar la tabla
            // El formulario <form method="get"> es necesario para el buscador y los filtros.
            echo '<form method="get">';
            echo '<input type="hidden" name="page" value="' . esc_attr( $_REQUEST['page'] ) . '" />';
            
            $Miembros_Table->display();
            
            echo '</form>';
            // -----------------------------------------------------
            
            echo '</div>';
        }
    }
    // Inicializar la clase
    new MDD_Admin_Pages();
}
