<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MT_Field_API_Token
 *
 * Defines and provides validation for the 'TaxJar API token' field.
 */
class MT_Field_API_Token {

    /**
     * @var MT_Settings_API WC integration or vendor settings form instance
     */
    protected $integration;

    /**
     * Initializes a new field instance.
     *
     * @param MT_Settings_API $form Settings form instance.
     *
     * @return array Field definition
     */
    public static function init( $form ) {
        $instance = new self( $form );

        $field = [
            'type'              => 'text',
            'title'             => __( 'TaxJar API token', 'marketplace-taxes' ),
            'description'       => __(
                '<a href="https://thepluginpros.com/out/taxjar-api-token" target="_blank">Find your API token</a> | <a href="https://thepluginpros.com/out/taxjar" target="_blank">Register for TaxJar</a>',
                'marketplace-taxes'
            ),
            'sanitize_callback' => array( $instance, 'validate' ),
        ];

        return $field;
    }

    public function __construct( $integration ) {
        $this->integration = $integration;
    }

    /**
     * Validates the API token entered by the user.
     *
     * @param string $token
     *
     * @return string
     *
     * @throws Exception If validation fails
     */
    public function validate( $token ) {
        if ( $this->integration->is_token_required() ) {
            if ( empty( $token ) ) {
                throw new Exception( 'TaxJar API token is required.' );
            }

            $client = MT()->client( $token );

            try {
                $client->categories();
            } catch ( TaxJar\Exception $ex ) {
                if ( 401 === $ex->getStatusCode() ) {
                    throw new Exception( __( 'The provided API token is invalid.', 'marketplace-taxes' ) );
                } else {
                    throw new Exception( __( 'Error connecting to TaxJar.', 'marketplace-taxes' ) );
                }
            }
        }

        return $token;
    }

}
