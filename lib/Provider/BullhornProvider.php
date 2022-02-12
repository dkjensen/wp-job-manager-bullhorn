<?php
/**
 * Bullhorn OAuth
 * 
 * @package WP Job Manager - Bullhorn Integration
 */


namespace SeattleWebCo\WPJobManager\Recruiter\Bullhorn\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use Psr\Http\Message\ResponseInterface;
use League\OAuth2\Client\Token\AccessToken;

class BullhornProvider extends AbstractProvider {

    
    /**
     * Authorization endpoint
     *
     * @return string
     */
    public function getBaseAuthorizationUrl() {
        return 'https://auth.bullhornstaffing.com/oauth/authorize';
    }


    /**
     * Token retrieval endpoint
     *
     * @param array $params
     * @return string
     */
    public function getBaseAccessTokenUrl( array $params ) {
        return 'https://auth.bullhornstaffing.com/oauth/token';
    }


    /**
     * Scopes array
     *
     * @return array
     */
    public function getDefaultScopes() {
        return array();
    }


    /**
     * OAuth scopes delimiter
     *
     * @return string
     */
    public function getScopeSeparator() {
        return ' ';
    }


    /**
     * Returns stored access token or retrieves new one
     *
     * @return string
     */
    public function get_access_token( $code = false ) {
        $tokens = get_option( 'job_manager_bullhorn_token' );

        if ( $code ) {
            $token = $this->getAccessToken( 'authorization_code', array( 'code' => $code ) );
        } elseif ( isset( $tokens['expires'] ) && time() > $tokens['expires'] ) {
            $token = $this->getAccessToken( 'refresh_token', array( 'refresh_token' => $tokens['refresh_token'] ) );
        }

        if ( isset( $token ) ) {
            $this->set_access_tokens( $token );
        }

        return isset( $tokens['token'] ) ? $tokens['token'] : '';
    }


    /**
     * Sets provider access tokens
     *
     * @param AccessToken $token
     * @return void
     */
    protected function set_access_tokens( AccessToken $token ) {
        update_option( 'job_manager_bullhorn_token', array( 
            'token'         => $token->getToken(),
            'expires'       => $token->getExpires(),
            'refresh_token' => $token->getRefreshToken()
        ) );
    }


    public function getResourceOwnerDetailsUrl( AccessToken $token ) {}


    protected function checkResponse( ResponseInterface $response, $data ) {}

        
    protected function createResourceOwner( array $response, AccessToken $token ) {}

}
