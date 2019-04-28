<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WC Vendors form helper.
 *
 * Provides static methods for generating form elements for use in public or
 * admin facing forms.
 *
 * Based on the WCVendors_Pro_Form_Helper class by Jamie Madden.
 */
class MT_WC_Vendors_Form_Helper {

    /**
     * Create an input field with a label.
     *
     * @param array  $field Array defining all field attributes
     * @param string $context
     */
    public static function input( $field, $context = 'frontend' ) {
        if ( empty( $field ) ) {
            return;
        }

        $allow_markup = 'yes' === get_option( 'wcvendors_allow_form_markup' ) ? true : false;

        $post_id                = isset( $field['post_id'] ) ? $field['post_id'] : 0;
        $field['placeholder']   = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
        $field['class']         = isset( $field['class'] ) ? $field['class'] : '';
        $field['style']         = isset( $field['style'] ) ? $field['style'] : '';
        $field['title']         = isset( $field['title'] ) ? $field['title'] : '';
        $field['label']         = isset( $field['label'] ) ? $field['label'] : '';
        $field['wrapper_class'] = isset( $field['wrapper_class'] ) ? $field['wrapper_class'] : '';
        $field['wrapper_start'] = isset( $field['wrapper_start'] ) ? $field['wrapper_start'] : '';
        $field['wrapper_end']   = isset( $field['wrapper_end'] ) ? $field['wrapper_end'] : '';
        $field['value']         = isset( $field['value'] ) ? $field['value'] : get_post_meta(
            $post_id,
            $field['id'],
            true
        );
        $field['cbvalue']       = isset( $field['cbvalue'] ) ? $field['cbvalue'] : 'yes';
        $field['name']          = isset( $field['name'] ) ? $field['name'] : $field['id'];
        $field['type']          = isset( $field['type'] ) ? $field['type'] : 'text';
        $field['show_label']    = isset( $field['show_label'] ) ? $field['show_label'] : true;

        // Strip tags
        $field['value'] = ( $allow_markup ) ? $field['value'] : wp_strip_all_tags( $field['value'] );

        // disable label for hidden
        $field['show_label'] = ( 'hidden' == $field['type'] ) ? false : true;

        // Custom attribute handling
        $custom_attributes = array();

        if ( ! empty( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ) {
            foreach ( $field['custom_attributes'] as $attribute => $value ) {
                $custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $value ) . '"';
            }
        }

        do_action( 'wcv_form_input_before_' . $field['id'], $field );

        // Start wrapper - ignored in the admin context
        if ( 'frontend' === $context ) {
            if ( ! empty( $field['wrapper_start'] ) && ! empty( $field['wrapper_end'] ) ) {
                echo $field['wrapper_start'];
            }
        }

        if ( 'hidden' !== $field['type'] ) {
            self::start_control_group( $field, $context );
        }

        // Labels are displayed differently for checkboxes
        if ( 'checkbox' === $field['type'] ) {
            $field['show_label'] = $field['show_label'] && $field['title'];
        }

        if ( $field['show_label'] ) {
            self::field_label( $field, $context );
        }

        if ( 'frontend' === $context ) {
            if ( 'checkbox' === $field['type'] ) {
                echo '<ul class="control unstyled inline" style="padding:0; margin:0;"><li>';
            } else {
                echo '<div class="control">';
            }
        } else {
            if ( $field['show_label'] ) {
                echo '<td>';
            } else {
                echo '<th class="full-width-field" colspan="2">';
            }
        }

        // Change the output slightly for check boxes
        if ( 'checkbox' === $field['type'] ) {
            echo '<input type="checkbox" class="' . esc_attr( $field['class'] ) . '" style="' . esc_attr(
                    $field['style']
                ) . '" name="' . esc_attr(
                     $field['name']
                 ) . '" id="' . esc_attr( $field['id'] ) . '" value="' . esc_attr( $field['cbvalue'] ) . '" ' . checked(
                     $field['value'],
                     $field['cbvalue'],
                     false
                 ) . '  ' . implode( ' ', $custom_attributes ) . '/>';

            echo '<label for="' . esc_attr( $field['id'] ) . '">' . wp_kses_post( $field['label'] ) . '</label>';
        } else {
            echo '<input type="' . esc_attr( $field['type'] ) . '" class="' . esc_attr(
                    $field['class']
                ) . '" style="' . esc_attr( $field['style'] ) . '" name="' . esc_attr(
                     $field['name']
                 ) . '" id="' . esc_attr( $field['id'] ) . '" value="' . esc_attr(
                     $field['value']
                 ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" ' . implode(
                     ' ',
                     $custom_attributes
                 ) . ' /> ';
        }

        if ( ! empty( $field['description'] ) ) {
            if ( ! isset( $field['desc_tip'] ) || false == $field['desc_tip'] ) {
                self::field_description( $field['description'], $context );
            }
        }

        if ( 'frontend' === $context ) {
            if ( 'checkbox' === $field['type'] ) {
                echo '</li></ul>';
            } else {
                echo '</div>';
            }
        } else {
            if ( $field['show_label'] ) {
                echo '</td>';
            } else {
                echo '</th>';
            }
        }

        if ( 'hidden' !== $field['type'] ) {
            self::end_control_group( $context );
        }

        if ( 'frontend' === $context ) {
            // container wrapper end if defined
            if ( ! empty( $field['wrapper_start'] ) && ! empty( $field['wrapper_end'] ) ) {
                echo $field['wrapper_end'];
            }
        }

        do_action( 'wcv_form_input_after_' . $field['id'], $field );
    }

    /**
     * Creates a select box with a label.
     *
     * @param array  $field   Array defining all field attributes
     * @param string $context 'admin' or 'frontend'
     */
    public static function select( $field, $context = 'frontend' ) {
        $post_id                   = isset( $field['post_id'] ) ? $field['post_id'] : 0;
        $field['class']            = isset( $field['class'] ) ? $field['class'] : 'select2';
        $field['style']            = isset( $field['style'] ) ? $field['style'] : '';
        $field['wrapper_class']    = isset( $field['wrapper_class'] ) ? $field['wrapper_class'] : '';
        $field['wrapper_start']    = isset( $field['wrapper_start'] ) ? $field['wrapper_start'] : '';
        $field['wrapper_end']      = isset( $field['wrapper_end'] ) ? $field['wrapper_end'] : '';
        $field['value']            = isset( $field['value'] ) ? $field['value'] : get_post_meta(
            $post_id,
            $field['id'],
            true
        );
        $field['show_option_none'] = isset( $field['show_option_none'] ) ? $field['show_option_none'] : '';
        $field['options']          = isset( $field['options'] ) ? $field['options'] : array();
        $field['taxonomy_field']   = isset( $field['taxonomy_field'] ) ? $field['taxonomy_field'] : 'slug';
        $field['show_label']       = isset( $field['show_label'] ) ? $field['show_label'] : true;

        // Custom attribute handling
        $custom_attributes = array();

        if ( ! empty( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ) {
            foreach ( $field['custom_attributes'] as $attribute => $value ) {
                $custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $value ) . '"';
            }
        }

        // Taxonomy drop down
        if ( isset( $field['taxonomy'], $field['taxonomy_args'] ) && is_array( $field['taxonomy_args'] ) ) {
            // Default terms args
            $defaults = array(
                'orderby'           => 'name',
                'order'             => 'ASC',
                'hide_empty'        => true,
                'exclude'           => array(),
                'exclude_tree'      => array(),
                'include'           => array(),
                'number'            => '',
                'fields'            => 'all',
                'slug'              => '',
                'parent'            => '',
                'hierarchical'      => true,
                'child_of'          => 0,
                'get'               => '',
                'name__like'        => '',
                'description__like' => '',
                'pad_counts'        => false,
                'offset'            => '',
                'search'            => '',
                'cache_domain'      => 'core',
            );

            // Merge args
            $args = wp_parse_args( $field['taxonomy_args'], $defaults );

            if ( $args['orderby'] == 'order' ) {
                $args['menu_order'] = 'asc';
                $args['orderby']    = 'name';
            }

            // Get terms for taxonomy
            $terms = get_terms( $field['taxonomy'], $args );

            $options = array();

            foreach ( $terms as $term ) {
                $options[ $term->term_id ] = $term->name;
            }

            $field['options'] = $options;
        }

        do_action( 'wcv_form_select_before_' . $field['id'], $field );

        if ( 'frontend' === $context ) {
            // Container wrapper start if defined start & end required otherwise no output is shown
            if ( ! empty( $field['wrapper_start'] ) && ! empty( $field['wrapper_end'] ) ) {
                echo $field['wrapper_start'];
            }
        }

        self::start_control_group( $field, $context );

        if ( $field['show_label'] ) {
            self::field_label( $field, $context );
        }

        if ( 'frontend' === $context ) {
            echo '<div class="control select">';
        } else {
            if ( $field['show_label'] ) {
                echo '<td>';
            } else {
                echo '<td colspan="2">';
            }
        }

        echo '<select id="' . esc_attr( $field['id'] ) . '" name="' . esc_attr( $field['id'] ) . '" class="' . esc_attr(
                $field['class']
            ) . '" style="' . esc_attr( $field['style'] ) . '" ' . implode( ' ', $custom_attributes ) . '>';

        if ( ! empty( $field['show_option_none'] ) ) {
            echo '<option value>' . esc_html( $field['show_option_none'] ) . '</option>';
        }

        foreach ( $field['options'] as $key => $value ) {
            echo '<option value="' . esc_attr( $key ) . '" ' . selected(
                    esc_attr( $field['value'] ),
                    esc_attr( $key ),
                    false
                ) . '>' . esc_html( $value ) . '</option>';
        }

        echo '</select> ';

        if ( ! empty( $field['description'] ) ) {
            if ( ! isset( $field['desc_tip'] ) || false === $field['desc_tip'] ) {
                self::field_description( $field['description'], $context );
            }
        }

        if ( 'frontend' === $context ) {
            echo '</div>'; //control
        } else {
            echo '</td>';
        }

        self::end_control_group( $context );

        if ( 'frontend' === $context ) {
            // container wrapper end if defined
            if ( ! empty( $field['wrapper_start'] ) && ! empty( $field['wrapper_end'] ) ) {
                echo $field['wrapper_end'];
            }
        }

        do_action( 'wcv_form_select_after_' . $field['id'], $field );
    }

    /**
     * Creates a select2 drop down with a label.
     *
     * @param array  $field   Array defining all field attributes
     * @param string $context 'admin' or 'frontend'
     */
    public static function select2( $field, $context = 'frontend' ) {
        $post_id                   = isset( $field['post_id'] ) ? $field['post_id'] : 0;
        $field['class']            = isset( $field['class'] ) ? $field['class'] : 'select2';
        $field['style']            = isset( $field['style'] ) ? $field['style'] : '';
        $field['wrapper_class']    = isset( $field['wrapper_class'] ) ? $field['wrapper_class'] : '';
        $field['wrapper_start']    = isset( $field['wrapper_start'] ) ? $field['wrapper_start'] : '';
        $field['wrapper_end']      = isset( $field['wrapper_end'] ) ? $field['wrapper_end'] : '';
        $field['value']            = isset( $field['value'] ) ? $field['value'] : get_post_meta(
            $post_id,
            $field['id'],
            true
        );
        $field['show_option_none'] = isset( $field['show_option_none'] ) ? $field['show_option_none'] : '';
        $field['options']          = isset( $field['options'] ) ? $field['options'] : array();
        $field['custom_tax']       = isset( $field['custom_tax'] ) ? $field['custom_tax'] : false;

        // Custom attribute handling
        $custom_attributes = array();

        if ( ! empty( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ) {
            foreach ( $field['custom_attributes'] as $attribute => $value ) {
                $custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $value ) . '"';
            }
        }

        // Taxonomy drop down
        if ( isset( $field['taxonomy'], $field['taxonomy_args'] ) && is_array( $field['taxonomy_args'] ) ) {
            $existing_terms = wp_get_post_terms( $post_id, $field['taxonomy'], array( 'fields' => 'all' ) );

            $selected = array();
            if ( ! empty( $existing_terms ) ) {
                foreach ( $existing_terms as $existing_term ) {
                    $selected[] = $existing_term->term_id;
                }
            }

            // Default terms args
            $defaults = apply_filters(
                'wcv_select2_args_' . $field['taxonomy'],
                array(
                    'pad_counts'         => 1,
                    'show_count'         => 0,
                    'hierarchical'       => 1,
                    'hide_empty'         => 1,
                    'fields'             => 'all',
                    'show_uncategorized' => 1,
                    'orderby'            => 'name',
                    'selected'           => $selected,
                    'menu_order'         => false,
                    'value'              => 'id',
                )
            );

            // Merge args
            $args = wp_parse_args( $field['taxonomy_args'], $defaults );

            if ( $args['orderby'] == 'order' ) {
                $args['menu_order'] = 'asc';
                $args['orderby']    = 'name';
            }

            // Get terms for taxonomy
            $terms = get_terms( $field['taxonomy'], $args );

            if ( ! $terms ) {
                return;
            }

            $field['options'] = wcv_walk_category_dropdown_tree( $terms, 0, $args );
        }

        do_action( 'wcv_form_select2_before_' . $field['id'], $field );

        if ( 'frontend' === $context ) {
            // Container wrapper start if defined start & end required otherwise no output is shown
            if ( ! empty( $field['wrapper_start'] ) && ! empty( $field['wrapper_end'] ) ) {
                echo $field['wrapper_start'];
            }
        }

        self::start_control_group( $field, $context );

        self::field_label( $field, $context );

        if ( 'frontend' === $context ) {
            echo '<div class="control select">';
        } else {
            echo '<td>';
        }

        if ( ! empty( $field['description'] ) ) {
            if ( ! isset( $field['desc_tip'] ) || false === $field['desc_tip'] ) {
                self::field_description( $field['description'], $context );
            }
        }

        echo '<select id="' . esc_attr( $field['id'] ) . '" name="' . esc_attr( $field['id'] ) . '" class="' . esc_attr(
                $field['class']
            ) . '" style="' . esc_attr( $field['style'] ) . '" ' . implode( ' ', $custom_attributes ) . '>';

        if ( ! empty( $field['show_option_none'] ) ) {
            echo '<option value>' . esc_html( $field['show_option_none'] ) . '</option>';
        }

        // If taxonomy provided then display the custom walked drop down, otherwise iterate over provided options
        if ( isset( $field['taxonomy'], $field['taxonomy_args'] ) && is_array( $field['taxonomy_args'] ) ) {
            echo $field['options'];
        } else {
            foreach ( $field['options'] as $key => $value ) {
                echo '<option value="' . esc_attr( $key ) . '" ' . selected(
                        esc_attr( $field['value'] ),
                        esc_attr( $key ),
                        false
                    ) . '>' . esc_html( $value ) . '</option>';
            }
        }

        echo '</select> ';

        if ( 'frontend' === $context ) {
            echo '</div>'; //control
        } else {
            echo '</td>';
        }

        self::end_control_group( $context );

        if ( 'frontend' === $context ) {
            // container wrapper end if defined
            if ( ! empty( $field['wrapper_start'] ) && ! empty( $field['wrapper_end'] ) ) {
                echo $field['wrapper_end'];
            }
        }

        if ( $field['custom_tax'] ) {
            $id = str_replace( '[]', '', $field['id'] );

            self::input(
                apply_filters(
                    'wcv_form_select2_custom_tax_' . $field['id'],
                    array(
                        'post_id' => $post_id,
                        'type'    => 'hidden',
                        'id'      => 'track_' . $id,
                        'value'   => '-1',
                    )
                )
            );
        }

        do_action( 'wcv_form_select2_after_' . $field['id'], $field );
    }

    /**
     * Outputs a select2 country select box.
     *
     * @param array  $field   Field definition.
     * @param string $context 'admin' or 'frontend'
     */
    public static function country_select2( $field, $context = 'frontend' ) {
        $field['id']                = isset( $field['id'] ) ? $field['id'] : '';
        $field['title']             = isset( $field['title'] ) ? $field['title'] : '';
        $field['value']             = isset( $field['value'] ) ? $field['value'] : '';
        $field['class']             = isset( $field['class'] ) ? $field['class'] : '';
        $field['wrapper_start']     = isset( $field['wrapper_start'] ) ? $field['wrapper_start'] : '';
        $field['wrapper_end']       = isset( $field['wrapper_end'] ) ? $field['wrapper_end'] : '';
        $field['wrapper_class']     = isset( $field['wrapper_class'] ) ? $field['wrapper_class'] : '';
        $field['show_option_none']  = isset( $field['show_option_none'] ) ? $field['show_option_none'] : '';
        $field['options']           = isset( $field['options'] ) ? $field['options'] : self::get_default_countries();
        $field['custom_attributes'] = isset( $field['custom_attributes'] ) ? $field['custom_attributes'] : [];

        if ( $field['value'] == '' ) {
            $field['value'] = WC()->countries->get_base_country();
        }

        do_action( 'wcv_form_country_select2_before_' . $field['id'], $field );

        self::select(
            [
                'id'                => $field['id'],
                'title'             => $field['title'],
                'value'             => $field['value'],
                'class'             => 'select2 mt_country_to_state country_select ' . $field['class'],
                'options'           => $field['options'],
                'wrapper_start'     => $field['wrapper_start'],
                'wrapper_end'       => $field['wrapper_end'],
                'wrapper_class'     => $field['wrapper_class'],
                'custom_attributes' => $field['custom_attributes'],
            ],
            $context
        );

        do_action( 'wcv_form_country_select2_after_' . $field['id'], $field );
    }

    /**
     * Returns the default options for country select boxes.
     *
     * @return array
     */
    private static function get_default_countries() {
        if ( WC()->countries->get_allowed_countries() ) {
            return WC()->countries->get_allowed_countries();
        } else {
            return WC()->countries->get_shipping_countries();
        }
    }

    /**
     * Creates a textarea with a label.
     *
     * @param array  $field   Array defining all field attributes
     * @param string $context 'admin' or 'frontend'
     */
    public static function textarea( $field, $context = 'frontend' ) {
        $allow_markup           = 'yes' === get_option( 'wcvendors_allow_form_markup' ) ? true : false;
        $post_id                = isset( $field['post_id'] ) ? $field['post_id'] : 0;
        $field['placeholder']   = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
        $field['class']         = isset( $field['class'] ) ? $field['class'] : 'select short';
        $field['rows']          = isset( $field['rows'] ) ? $field['rows'] : 5;
        $field['cols']          = isset( $field['cols'] ) ? $field['cols'] : 5;
        $field['style']         = isset( $field['style'] ) ? $field['style'] : '';
        $field['wrapper_start'] = isset( $field['wrapper_start'] ) ? $field['wrapper_start'] : '';
        $field['wrapper_end']   = isset( $field['wrapper_end'] ) ? $field['wrapper_end'] : '';
        $field['value']         = isset( $field['value'] ) ? $field['value'] : get_post_meta(
            $post_id,
            $field['id'],
            true
        );

        // Strip tags
        $field['value'] = ( $allow_markup ) ? $field['value'] : wp_strip_all_tags( $field['value'] );

        // Custom attribute handling
        $custom_attributes = array();

        if ( ! empty( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ) {
            foreach ( $field['custom_attributes'] as $attribute => $value ) {
                $custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $value ) . '"';
            }
        }

        do_action( 'wcv_form_textarea_before_' . $field['id'], $field );

        if ( 'frontend' === $context ) {
            // Container wrapper start if defined start & end required otherwise no output is shown
            if ( ! empty( $field['wrapper_start'] ) && ! empty( $field['wrapper_end'] ) ) {
                echo $field['wrapper_start'];
            }
        }

        self::start_control_group( $field, $context );

        self::field_label( $field, $context );

        if ( 'frontend' === $context ) {
            echo '<div class="control">';
        } else {
            echo '<td>';
        }

        echo '<textarea class="' . esc_attr( $field['class'] ) . '" style="' . esc_attr(
                $field['style']
            ) . '"  name="' . esc_attr( $field['id'] ) . '" id="' . esc_attr(
                 $field['id']
             ) . '" placeholder="' . esc_attr(
                 $field['placeholder']
             ) . '" rows="' . $field['rows'] . '" cols="' . $field['cols'] . '" ' . implode(
                 ' ',
                 $custom_attributes
             ) . '>' . esc_textarea( $field['value'] ) . '</textarea> ';

        self::field_description( $field['description'], $context );

        if ( 'frontend' === $context ) {
            echo '</div>';
        } else {
            echo '</td>';
        }

        if ( 'frontend' === $context ) {
            // container wrapper end if defined
            if ( ! empty( $field['wrapper_start'] ) && ! empty( $field['wrapper_end'] ) ) {
                echo $field['wrapper_end'];
            }
        }

        do_action( 'wcv_form_textarea_after_' . $field['id'], $field );
    }

    /**
     * Outputs a custom form input based on an included file.
     *
     * @param array  $field   Field attributes
     * @param string $context 'admin' or 'frontend'
     */
    public static function custom_field( $field, $context = 'frontend' ) {
        do_action( 'wcv_form_custom_field_before_' . $field['id'], $field );

        self::start_control_group( $field, $context );

        self::field_label( $field, $context );

        if ( 'frontend' === $context ) {
            echo '<div class="control">';
        } else {
            echo '<td>';
        }

        // Make all field attributes available in the included file
        extract( $field );

        include_once( $field['path'] );

        if ( ! isset( $field['desc_tip'] ) || false === $field['desc_tip'] ) {
            self::field_description( $field['description'], $context );
        }

        if ( 'frontend' === $context ) {
            echo '</div>';
        } else {
            echo '</td>';
        }

        self::end_control_group( $context );

        do_action( 'wcv_form_custom_field_after_' . $field['id'], $field );
    }

    /**
     * Opens a new control group.
     *
     * @param array  $field
     * @param string $context 'admin' or 'frontend'
     */
    private static function start_control_group( $field, $context ) {
        $wrapper_class = '';

        if ( ! empty( $field['wrapper_class'] ) ) {
            $wrapper_class = esc_attr( $field['wrapper_class'] );
        }

        if ( 'frontend' === $context ) {
            echo '<div class="control-group ' . $wrapper_class . '">';
        } else {
            echo '<tr class="' . $wrapper_class . '">';
        }
    }

    /**
     * Closes a control group.
     *
     * @param string $context 'admin' or 'frontend'
     */
    private static function end_control_group( $context ) {
        if ( 'frontend' === $context ) {
            echo '</div>';
        } else {
            echo '</tr>';
        }
    }

    /**
     * Outputs a field label.
     *
     * @param array  $field
     * @param string $context 'admin' or 'frontend'
     */
    private static function field_label( $field, $context ) {
        $is_admin = 'admin' === $context;

        if ( $is_admin ) {
            echo '<th>';
        }

        echo '<label for="' . esc_attr( $field['id'] ) . '">' . wp_kses_post( $field['title'] );

        if ( isset( $field['desc_tip'] ) && true === $field['desc_tip'] ) {
            if ( $is_admin ) {
                echo wc_help_tip( $field['description'], true );
            } else {
                echo '<i class="dashicons dashicons-editor-help has-tip right" data-tooltip data-tip="' . esc_attr(
                        $field['description']
                    ) . '" aria-haspopup="true" title="' . esc_attr(
                         $field['description']
                     ) . '"></i>';
            }
        }

        echo '</label>';

        if ( $is_admin ) {
            echo '</th>';
        }
    }

    /**
     * Outputs a field description.
     *
     * @param string $description
     * @param string $context 'admin' or 'frontend'
     */
    private static function field_description( $description, $context ) {
        $desc_class = 'frontend' === $context ? 'tip' : 'description';

        echo '<p class="' . $desc_class . '">' . $description . '</p>';
    }

}
