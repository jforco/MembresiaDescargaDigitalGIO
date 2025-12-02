<?php
/**
 * Configuración completa para los campos de producto de tipo Membresía.
 * Incluye: Checkbox principal, campos condicionales, Javascript de UI y lógica de guardado.
 */

if ( ! defined('ABSPATH') ) {
    exit;
}

// 1. Agregar el Checkbox de "Membresía" en las opciones de tipo de producto
add_filter( 'product_type_options', function( $options ) {
    global $post;
    $es_membresia = get_post_meta($post->ID, '_es_membresia', true);
    
    $options['mdd_membresia'] = array(
        'id'            => '_es_membresia',
        'wrapper_class' => 'show_if_simple show_if_variable', // muéstralo en tipos comunes
        'label'         => __( 'Membresía', 'mdd' ),
        'description'   => __( 'Marcar si este producto es una membresía.', 'mdd' ),
        'default'       => ($es_membresia === 'yes') ? 'yes' : 'no',
    );
    return $options; 
}, 10 );

// 2. Mostrar los campos personalizados en la pestaña "General"
add_action( 'woocommerce_product_options_general_product_data', 'mdd_anadir_campos_datos_membresia' );

function mdd_anadir_campos_datos_membresia() {
    // --- GRUPO A: CAMPOS DE MEMBRESÍA ---
    echo '<div class="options_group mdd_membresia_fields">';

    // Campo: Duración de membresía
    woocommerce_wp_text_input( array(
        'id'    => '_mdd_duracion_membresia',
        'label' => __( 'Duración de la membresía (meses)', 'membresia-descarga-digital'),
        'desc_tip' => true,
        'type'  => 'number',
        'custom_attributes' => array(
            'min' => '1',
            'step' => '1'
        )
    ));

    // Campo: Número de descargas x día
    woocommerce_wp_text_input( array(
        'id'    => '_mdd_descargas_por_dia',
        'label' => __('Descargas por Día', 'membresia-descarga-digital'),
        'desc_tip'  => true,
        'description' => __( 'Cantidad máxima de archivos que el usuario puede descargar por día', 'membresia-descarga-digital' ),
        'type'  =>  'number',
        'custom_attributes' => array(
            'min' => '1',
            'step' => '1'
        )
    ));

    // Campo: Categorías permitidas (Checkboxes en scroll)
    global $thepostid;
    $categorias = get_terms( 'product_cat', array( 'hide_empty' => false ) );
    
    // Obtener las categorías permitidas guardadas (siempre debe ser un array)
    $categorias_permitidas = get_post_meta( $thepostid, '_mdd_categorias_permitidas', true );
    if ( ! is_array( $categorias_permitidas ) ) {
        $categorias_permitidas = array();
    }
    
    // ID del campo base
    $field_id = '_mdd_categorias_permitidas';
    
    // NOTA: La clase generada aquí es "_mdd_categorias_permitidas_field"
    ?>
    <fieldset class="form-field <?php echo esc_attr( $field_id ); ?>_field" style="margin-top: 20px;">
        
        <legend class="form-field-title">
            <?php esc_html_e( 'Categorías incluidas en la Membresía', 'membresia-descarga-digital' ); ?>
        </legend>
        
        <div id="<?php echo esc_attr( $field_id ); ?>_wrapper" class="mdd-categories-wrapper">
                        
            <div style="
                width: 40%; 
                height: 400px; 
                overflow-y: scroll; 
                border: 1px solid #8c8f94; 
                padding: 10px; 
                background: #fff;
                border-radius: 4px
            ">
            <?php
            
            if ( ! empty( $categorias ) && ! is_wp_error( $categorias ) ) {
                foreach ( $categorias as $cat ) {
                    $category_id = $cat->term_id;
                    $is_checked = in_array( $category_id, $categorias_permitidas );
                    
                    printf(
                        '<span style="display: block; margin-bottom: 5px;">
                            <input type="checkbox" 
                                name="%s[]" 
                                value="%s" 
                                %s />
                            %s
                        </span>',
                        esc_attr( $field_id ), 
                        esc_attr( $category_id ),
                        checked( $is_checked, true, false ),
                        esc_html( $cat->name )
                    );
                }
            } else {
                echo '<p>' . esc_html__( 'No hay categorías de producto disponibles.', 'membresia-descarga-digital' ) . '</p>';
            }

            ?>
            </div>
             <p class="description"><?php esc_html_e( 'Selecciona las categorías a las que tendrá acceso el usuario con este plan.', 'membresia-descarga-digital' ); ?></p>
        </div>
    </fieldset>
    <?php
    echo '</div>'; // Fin grupo membresía

    // --- GRUPO B: NUEVO CAMPO DE URL EXTERNA (Solo para descargables NO membresía) ---
    echo '<div class="options_group mdd_external_url_field">';
    
    woocommerce_wp_text_input( array(
        'id'          => '_mdd_url_descarga_externa',
        'label'       => __( 'URL de descarga externa', 'membresia-descarga-digital' ),
        'placeholder' => 'https://ejemplo.com/archivo.zip',
        'desc_tip'    => true,
        'description' => __( 'Si este producto es descargable pero NO es una membresía, ingresa aquí el enlace directo al archivo externo.', 'membresia-descarga-digital' ),
        'type'        => 'url'
    ));

    echo '</div>';
}

// 3. Guardar el valor y asignar configuración (CONSOLIDADO)
// Usamos este hook moderno que nos da el objeto $product directamente.
add_action( 'woocommerce_admin_process_product_object', function( $product ) {
    
    // A. Guardar estado del Checkbox "Es Membresía"
    $es_membresia = isset( $_POST['_es_membresia'] ) ? 'yes' : 'no';
    $product->update_meta_data( '_es_membresia', $es_membresia );

    // B. Lógica condicional según si es membresía o no
    if ( $es_membresia === 'yes' ) {
        // Forzar a virtual
        $product->set_virtual( true );

        // Asignar categoría 'membresia'
        wp_set_object_terms( $product->get_id(), 'membresia', 'product_cat', true );
    } else {
        // Remover la categoría si se desmarca
        wp_remove_object_terms( $product->get_id(), 'membresia', 'product_cat' );
    }

    // C. Guardar Campos de Membresía
    // Duración
    if ( isset( $_POST['_mdd_duracion_membresia'] ) ) {
        $product->update_meta_data( '_mdd_duracion_membresia', sanitize_text_field( $_POST['_mdd_duracion_membresia'] ) );
    }
    
    // Descargas por día
    if ( isset( $_POST['_mdd_descargas_por_dia'] ) ) {
        $product->update_meta_data( '_mdd_descargas_por_dia', sanitize_text_field( $_POST['_mdd_descargas_por_dia'] ) );
    }

    // Categorías Permitidas (Array)
    if ( isset( $_POST['_mdd_categorias_permitidas'] ) && is_array( $_POST['_mdd_categorias_permitidas'] ) ) {
        // Sanear el array como enteros
        $categorias = array_map( 'intval', $_POST['_mdd_categorias_permitidas'] );
        $product->update_meta_data( '_mdd_categorias_permitidas', $categorias );
    } else {
        // Si no se selecciona nada o se desmarca membresía, guardamos un array vacío.
        $product->update_meta_data( '_mdd_categorias_permitidas', array() );
    }

    // D. Guardar Nuevo Campo: URL Externa
    if ( isset( $_POST['_mdd_url_descarga_externa'] ) ) {
        // Usamos esc_url_raw para sanear URLs
        $product->update_meta_data( '_mdd_url_descarga_externa', esc_url_raw( $_POST['_mdd_url_descarga_externa'] ) );
    }
});


// 4. Ocultar pestañas innecesarias en el servidor (PHP)
add_filter( 'woocommerce_product_data_tabs', function( $tabs ) {
    global $post;
    $es_membresia = get_post_meta( $post->ID, '_es_membresia', true );
    
    if ( $es_membresia === 'yes' ) {
        unset( $tabs['linked_product'] );   // Productos relacionados
        unset( $tabs['attribute'] );        // Atributos
        unset( $tabs['advanced'] );         // Avanzado
        unset( $tabs['cartflows'] );        // CartFlows (si existe)
        unset( $tabs['direct_checkout'] );  // Direct Checkout (si existe)
    }
    return $tabs;
}, 20 );


// 5. JavaScript para la UI (Ocultar/Mostrar al vuelo)
add_action( 'admin_footer', function() {
    $screen = get_current_screen();
    if ( $screen && $screen->id === 'product' ) : ?>
        <script>
        jQuery(function($){
            
            // Sincronizar checkbox de virtual
            $('#_es_membresia').on('change', function(){
                if( $(this).is(':checked') ){
                    $('#_virtual').prop('checked', true).trigger('change');
                }
            });

            // Función central para gestionar visibilidad de TODOS los campos personalizados
            function toggleCustomFields() {
                var isMembership = $('#_es_membresia').is(':checked');
                var isDownloadable = $('#_downloadable').is(':checked');

                // 1. Lógica de Membresía (Prioritaria)
                if ( isMembership ) {
                    // MOSTRAR campos de Membresía
                    $('#_mdd_duracion_membresia').prop('disabled', false).closest('.form-field').show();
                    $('#_mdd_descargas_por_dia').prop('disabled', false).closest('.form-field').show();
                    $('._mdd_categorias_permitidas_field').show().find('input').prop('disabled', false);
                    
                    // OCULTAR campo de URL Externa (No tiene sentido en membresía)
                    $('#_mdd_url_descarga_externa').prop('disabled', true).closest('.form-field').hide();
                } 
                else {
                    // OCULTAR campos de Membresía
                    $('#_mdd_duracion_membresia').prop('disabled', true).closest('.form-field').hide();
                    $('#_mdd_descargas_por_dia').prop('disabled', true).closest('.form-field').hide();
                    $('._mdd_categorias_permitidas_field').hide().find('input').prop('disabled', true);

                    // 2. Lógica de URL Externa (Solo si es Descargable y NO es membresía)
                    if ( isDownloadable ) {
                        // Mostrar URL Externa
                        $('#_mdd_url_descarga_externa').prop('disabled', false).closest('.form-field').show();
                        
                        // HACK VISUAL: Mover este campo justo debajo de la caja de "Archivos descargables" de WooCommerce
                        // La clase del contenedor de archivos descargables suele ser '.downloadable_files'
                        var externalField = $('#_mdd_url_descarga_externa').closest('.form-field');
                        var downloadableFilesSection = $('.downloadable_files').closest('.form-field');
                        
                        // Si encontramos la sección de archivos descargables, movemos nuestro campo debajo
                        if ( downloadableFilesSection.length ) {
                            externalField.insertAfter( downloadableFilesSection );
                        }
                    } else {
                        // Ocultar URL Externa si no es descargable
                        $('#_mdd_url_descarga_externa').prop('disabled', true).closest('.form-field').hide();
                    }
                }
            }

            // Ejecutar al cargar la página
            toggleCustomFields();

            // Ejecutar cuando cambie el check de Membresía
            $('#_es_membresia').on('change', function(){
                toggleCustomFields();
            });

            // Ejecutar cuando cambie el check de Descargable (Nativo de WC)
            $('#_downloadable').on('change', function(){
                toggleCustomFields();
            });

            // Lógica para pestañas (Tabs)
            function toggleMembresiaTabs() {
                if ( $('#_es_membresia').is(':checked') ) {
                    $('#woocommerce-product-data .wc-tabs li.attribute_tab').hide();
                    $('#woocommerce-product-data .wc-tabs li.linked_product_tab').hide();
                    $('#woocommerce-product-data .wc-tabs li.advanced_tab').hide();
                    $('#woocommerce-product-data .wc-tabs li.cartflows_tab').hide();
                    $('#woocommerce-product-data .wc-tabs li.direct_checkout_tab').hide();
                } else {
                    $('#woocommerce-product-data .wc-tabs li.attribute_tab').show();
                    $('#woocommerce-product-data .wc-tabs li.linked_product_tab').show();
                    $('#woocommerce-product-data .wc-tabs li.advanced_tab').show();
                    $('#woocommerce-product-data .wc-tabs li.cartflows_tab').show();
                    $('#woocommerce-product-data .wc-tabs li.direct_checkout_tab').show();
                }
            }
            
            toggleMembresiaTabs();
            $(document).on('change', '#_es_membresia', toggleMembresiaTabs);
        });
        </script>
    <?php endif;
});