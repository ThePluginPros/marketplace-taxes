<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dokan form helper.
 *
 * Provides static methods for generating form elements for use in public
 * facing forms.
 *
 * Based on the WCVendors_Pro_Form_Helper class by Jamie Madden.
 */
class MT_Dokan_Form_Helper {

    /**
     * Create an input field with a label.
     *
     * @param array $field Array defining all field attributes
     */
    public static function input( $field ) {
        if ( empty( $field ) ) {
            return;
        }

        $post_id                = isset( $field['post_id'] ) ? $field['post_id'] : 0;
        $field['placeholder']   = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
        $field['class']         = isset( $field['class'] ) ? $field['class'] : 'dokan-form-control';
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
        $field['value'] = wp_strip_all_tags( $field['value'] );

        // disable label for hidden
        $field['show_label'] = ( 'hidden' == $field['type'] ) ? false : true;

        // Custom attribute handling
        $custom_attributes = array();

        if ( ! empty( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ) {
            foreach ( $field['custom_attributes'] as $attribute => $value ) {
                $custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $value ) . '"';
            }
        }

        // Start wrapper - ignored in the admin context
        if ( ! empty( $field['wrapper_start'] ) && ! empty( $field['wrapper_end'] ) ) {
            echo $field['wrapper_start'];
        }

        if ( 'hidden' !== $field['type'] ) {
            self::start_control_group( $field );
        }

        // Labels are displayed differently for checkboxes
        if ( 'checkbox' === $field['type'] ) {
            $field['show_label'] = $field['show_label'] && $field['title'];
        }

        if ( $field['show_label'] ) {
            self::field_label( $field );
        }

        echo '<div class="dokan-w9 dokan-text-left">';

        // Change the output slightly for check boxes
        if ( 'checkbox' === $field['type'] ) {
            echo '<div class="checkbox">';

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

            echo '</div>';
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
                self::field_description( $field['description'] );
            }
        }

        echo '</div>';

        if ( 'hidden' !== $field['type'] ) {
            self::end_control_group();
        }

        // container wrapper end if defined
        if ( ! empty( $field['wrapper_start'] ) && ! empty( $field['wrapper_end'] ) ) {
            echo $field['wrapper_end'];
        }
    }

    /**
     * Creates a select box with a label.
     *
     * @param array $field Array defining all field attributes
     */
    public static function select( $field ) {
        $post_id                   = isset( $field['post_id'] ) ? $field['post_id'] : 0;
        $field['class']            = isset( $field['class'] ) ? $field['class'] : 'select2 dokan-form-control';
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

        // Container wrapper start if defined start & end required otherwise no output is shown
        if ( ! empty( $field['wrapper_start'] ) && ! empty( $field['wrapper_end'] ) ) {
            echo $field['wrapper_start'];
        }

        self::start_control_group( $field );

        if ( $field['show_label'] ) {
            self::field_label( $field );
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

        echo '</select>';

        if ( ! empty( $field['description'] ) ) {
            if ( ! isset( $field['desc_tip'] ) || false === $field['desc_tip'] ) {
                self::field_description( $field['description'] );
            }
        }

        self::end_control_group();

        // container wrapper end if defined
        if ( ! empty( $field['wrapper_start'] ) && ! empty( $field['wrapper_end'] ) ) {
            echo $field['wrapper_end'];
        }
    }

    /**
     * Outputs a select2 country select box.
     *
     * @param array $field Field definition.
     */
    public static function country_select2( $field ) {
        $field['id']                = isset( $field['id'] ) ? $field['id'] : '';
        $field['title']             = isset( $field['title'] ) ? $field['title'] : '';
        $field['value']             = isset( $field['value'] ) ? $field['value'] : '';
        $field['class']             = isset( $field['class'] ) ? $field['class'] : 'dokan-form-control';
        $field['wrapper_start']     = isset( $field['wrapper_start'] ) ? $field['wrapper_start'] : '';
        $field['wrapper_end']       = isset( $field['wrapper_end'] ) ? $field['wrapper_end'] : '';
        $field['wrapper_class']     = isset( $field['wrapper_class'] ) ? $field['wrapper_class'] : '';
        $field['show_everywhere']   = isset( $field['show_everywhere'] ) ? $field['show_everywhere'] : false;
        $field['options']           = isset( $field['options'] ) ? $field['options'] : self::get_default_countries();
        $field['custom_attributes'] = isset( $field['custom_attributes'] ) ? $field['custom_attributes'] : [];

        if ( $field['value'] == '' ) {
            $field['value'] = WC()->countries->get_base_country();
        }

        // Custom attribute handling
        $custom_attributes = array();

        if ( ! empty( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ) {
            foreach ( $field['custom_attributes'] as $attribute => $value ) {
                $custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $value ) . '"';
            }
        }

        // Container wrapper start if defined start & end required otherwise no output is shown
        if ( ! empty( $field['wrapper_start'] ) && ! empty( $field['wrapper_end'] ) ) {
            echo $field['wrapper_start'];
        }

        self::start_control_group( $field );

        self::field_label( $field );

        if ( ! empty( $field['description'] ) ) {
            if ( ! isset( $field['desc_tip'] ) || false === $field['desc_tip'] ) {
                self::field_description( $field['description'] );
            }
        }

        echo '<select id="' . esc_attr( $field['id'] ) . '" name="' . esc_attr( $field['id'] ) . '" class="' . esc_attr(
                $field['class']
            ) . '" style="' . esc_attr( $field['style'] ) . '" ' . implode( ' ', $custom_attributes ) . '>';

        dokan_country_dropdown( $field['options'], $field['value'], $field['show_everywhere'] );

        echo '</select>';

        self::end_control_group();

        // container wrapper end if defined
        if ( ! empty( $field['wrapper_start'] ) && ! empty( $field['wrapper_end'] ) ) {
            echo $field['wrapper_end'];
        }
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
     * @param array $field Array defining all field attributes
     */
    public static function textarea( $field ) {
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
        $field['value'] = wp_strip_all_tags( $field['value'] );

        // Custom attribute handling
        $custom_attributes = array();

        if ( ! empty( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ) {
            foreach ( $field['custom_attributes'] as $attribute => $value ) {
                $custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $value ) . '"';
            }
        }

        // Container wrapper start if defined start & end required otherwise no output is shown
        if ( ! empty( $field['wrapper_start'] ) && ! empty( $field['wrapper_end'] ) ) {
            echo $field['wrapper_start'];
        }

        self::start_control_group( $field );

        self::field_label( $field );

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

        self::field_description( $field['description'] );

        // container wrapper end if defined
        if ( ! empty( $field['wrapper_start'] ) && ! empty( $field['wrapper_end'] ) ) {
            echo $field['wrapper_end'];
        }
    }

    /**
     * Outputs a custom form input based on an included file.
     *
     * @param array $field Field attributes
     */
    public static function custom_field( $field ) {
        self::start_control_group( $field );

        self::field_label( $field, true );

        echo '<div class="dokan-w12 dokan-text-left">';

        // Make all field attributes available in the included file
        extract( $field );

        include_once( $field['path'] );

        if ( ! isset( $field['desc_tip'] ) || false === $field['desc_tip'] ) {
            self::field_description( $field['description'] );
        }

        echo '</div>';

        self::end_control_group();
    }

    /**
     * Opens a new control group.
     *
     * @param array $field
     */
    private static function start_control_group( $field ) {
        $wrapper_class = '';

        if ( ! empty( $field['wrapper_class'] ) ) {
            $wrapper_class = esc_attr( $field['wrapper_class'] );
        }

        echo '<div class="dokan-form-group ' . $wrapper_class . '">';
    }

    /**
     * Closes a control group.
     */
    private static function end_control_group() {
        echo '</div>';
    }

    /**
     * Outputs a field label.
     *
     * @param array $field      Form field.
     * @param bool  $full_width Should the label occupy the full form width?
     */
    private static function field_label( $field, $full_width = false ) {
        if ( $full_width ) {
            $class = 'dokan-w12';
        } else {
            $class = 'dokan-w3';
        }

        echo '<label for="' . esc_attr( $field['id'] ) . '" class="' . $class . ' dokan-control-label">';

        echo wp_kses_post( $field['title'] );

        if ( isset( $field['desc_tip'] ) && true === $field['desc_tip'] ) {
            echo '<i class="fa fa-question-circle tips" data-title="' . $field['description'] . '" aria-hidden="true"></i>';
        }

        echo '</label>';
    }

    /**
     * Outputs a field description.
     *
     * @param string $description
     */
    private static function field_description( $description ) {
        echo '<p class="description">' . $description . '</p>';
    }

}
