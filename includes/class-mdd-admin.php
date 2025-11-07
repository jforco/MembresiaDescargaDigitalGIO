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
            // Asegúrate de que la clase de la tabla esté definida
            if ( ! class_exists( 'MDD_Miembros_List_Table' ) ) {
                $table_class_file = plugin_dir_path( __FILE__ ) . 'mdd-miembros-list-table.php';
                if ( file_exists( $table_class_file ) ) {
                    require_once $table_class_file;
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html__( 'Error: El archivo de la tabla no se encontró.', 'membresia-descarga-digital' ) . '</p></div>';
                    return; 
                }
            }

            // --- LÓGICA PRINCIPAL DE CONTROL DE VISTAS (Similar a tu mdd_gestionar_membresias_page) ---
            
            // Comprobar la acción solicitada en la URL.
            $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';

            if ( $action === 'mdd_editar_membresia' ) {
                // --- VISTA DE EDICIÓN DE EXPIRACIÓN ---
                $user_id = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0;
                
                if ( $user_id > 0 ) {
                    // LLAMA A LA FUNCIÓN EXTERNA DE RENDERIZADO
                    $this->mdd_render_edit_membership_form( $user_id );
                } else {
                    // Usuario no válido, redirigir a la tabla.
                    $base_url = admin_url('admin.php?page=mdd-gestionar-membresias');
                    // Usamos la redirección de WordPress
                    wp_safe_redirect( esc_url_raw( $base_url ) );
                    exit;
                }

            } else {
                // --- VISTA DE TABLA PRINCIPAL ---
                $this->mdd_render_main_table();
            }
        }

        function mdd_render_edit_membership_form( $user_id ) {
            $user = get_userdata( $user_id );

            // Comprobación de usuario básico
            if ( ! $user ) {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'Error: Usuario no encontrado.', 'membresia-descarga-digital' ) . '</p></div>';
                return;
            }

            // Obtener la fecha de expiración actual como timestamp
            $current_expiration_ts = get_user_meta( $user_id, '_mdd_membresia_expiracion', true );
            // Convertir el timestamp a formato YYYY-MM-DD para el campo input[type=date]
            $current_expiration_date = $current_expiration_ts ? date( 'Y-m-d', $current_expiration_ts ) : '';
            
            // Obtener el nombre del plan para contexto
            $product_id = get_user_meta( $user_id, '_mdd_producto_id', true );
            $plan_name = $product_id ? get_the_title( $product_id ) : __( 'N/A', 'membresia-descarga-digital' );
            
            // URL de retorno
            $back_url = admin_url('admin.php?page=mdd-gestionar-membresias');

            ?>
            <div class="wrap">
                <!-- Título genérico para la página de modificación -->
                <h1><?php esc_html_e( 'Modificar Membresía', 'membresia-descarga-digital' ); ?>: <?php echo esc_html( $user->display_name ); ?></h1>
                
                <a href="<?php echo esc_url( $back_url ); ?>" class="button button-secondary">
                    &larr; <?php esc_html_e( 'Volver a la Gestión de Miembros', 'membresia-descarga-digital' ); ?>
                </a>

                <hr class="wp-header-end">

                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                        <div id="post-body-content">
                            
                            <!-- 1. CAJA: Modificar Fecha de Expiración -->
                            <div class="postbox">
                                <h2 class="hndle"><span><?php esc_html_e( 'Modificar Fecha de Expiración', 'membresia-descarga-digital' ); ?></span></h2>
                                <div class="inside">
                                    <form method="post">
                                        <p>
                                            <strong><?php esc_html_e( 'Usuario:', 'membresia-descarga-digital' ); ?></strong>
                                            <?php echo esc_html( $user->display_name ); ?> (<?php echo esc_html( $user->user_email ); ?>)
                                        </p>
                                        <p>
                                            <strong><?php esc_html_e( 'Plan de Membresía:', 'membresia-descarga-digital' ); ?></strong>
                                            <?php echo esc_html( $plan_name ); ?>
                                        </p>
                                        
                                        <table class="form-table">
                                            <tbody>
                                                <tr>
                                                    <th scope="row"><label for="mdd_new_exp_date"><?php esc_html_e( 'Nueva Fecha de Expiración', 'membresia-descarga-digital' ); ?></label></th>
                                                    <td>
                                                        <!-- ¡IMPORTANTE! El nombre del campo debe coincidir con el handler de POST -->
                                                        <input 
                                                            type="date" 
                                                            id="mdd_new_exp_date" 
                                                            name="mdd_expiracion_date" 
                                                            value="<?php echo esc_attr( $current_expiration_date ); ?>" 
                                                            required
                                                        />
                                                        <p class="description">
                                                            <?php esc_html_e( 'Selecciona la nueva fecha de expiración. La fecha actual es:', 'membresia-descarga-digital' ); ?> 
                                                            <?php echo $current_expiration_date ? date_i18n( get_option( 'date_format' ), $current_expiration_ts ) : __( 'Sin fecha', 'membresia-descarga-digital' ); ?>
                                                        </p>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        
                                        <input type="hidden" name="user_id" value="<?php echo absint( $user_id ); ?>" />
                                        <!-- ¡IMPORTANTE! La acción oculta debe coincidir con el handler de POST -->
                                        <input type="hidden" name="action" value="mdd_modificar_expiracion" />
                                        <?php wp_nonce_field( 'mdd_guardar_expiracion_' . $user_id, '_wpnonce' ); ?>
                                        
                                        <?php submit_button( __( 'Guardar Nueva Fecha de Expiración', 'membresia-descarga-digital' ), 'primary' ); ?>
                                    </form>
                                </div>
                            </div>

                            <!-- 2. CAJA: Opciones de Cancelación (Reubicado de la tabla principal) -->
                            <div class="postbox">
                                <h2 class="hndle"><span><?php esc_html_e( 'Opciones de Cancelación', 'membresia-descarga-digital' ); ?></span></h2>
                                <div class="inside">
                                    <p>
                                        <?php esc_html_e( 'Utiliza esta opción para cancelar la membresía del usuario de forma inmediata. Esto desactiva el acceso y establece la fecha de expiración en el pasado.', 'membresia-descarga-digital' ); ?>
                                    </p>
                                    <?php
                                    // Enlace de CANCELAR (Acción GET)
                                    $base_url = admin_url('admin.php?page=mdd-gestionar-membresias');
                                    $cancel_url = wp_nonce_url( 
                                        add_query_arg( 
                                            array( 'action' => 'mdd_cancelar', 'user' => $user_id ), 
                                            $base_url 
                                        ), 
                                        'mdd_cancelar_membresia_' . $user_id // Nonce específico por usuario
                                    );
                                    ?>
                                    <p>
                                        <a href="<?php echo esc_url( $cancel_url ); ?>" class="button button-danger" 
                                        onclick="return confirm('<?php esc_attr_e( '¿Estás seguro de que deseas CANCELAR la membresía de este usuario de forma inmediata? Esta acción no se puede deshacer.', 'membresia-descarga-digital' ); ?>');">
                                            <?php esc_html_e( 'Cancelar Membresía Ahora', 'membresia-descarga-digital' ); ?>
                                        </a>
                                    </p>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

        private function mdd_render_main_table() {
            // 1. Instanciar la List Table
            $miembros_list_table = new MDD_Miembros_List_Table();
            
            // 2. Preparar los datos (obtener, ordenar, paginar)
            $miembros_list_table->prepare_items();

            // 3. Renderizar la tabla (incluye el formulario de búsqueda y filtros)
            echo '<div class="wrap">';
            echo '<h2>' . esc_html__( 'Gestión de Membresías Digitales', 'membresia-descarga-digital' ) . '</h2>';
            
            echo '<form method="get">';
            echo '<input type="hidden" name="page" value="' . esc_attr( $_REQUEST['page'] ) . '" />';
            //$miembros_list_table->search_box( __( 'Buscar Miembros', 'membresia-descarga-digital' ), 'member' );
            $miembros_list_table->display();
            echo '</form>';
            
            echo '</div>'; // wrap
        }
    }
    // Inicializar la clase
    new MDD_Admin_Pages();
}
