<?php
/**
 * Bullhorn OAuth
 * 
 * @package WP Job Manager - Bullhorn Integration
 */


namespace SeattleWebCo\WPJobManager\Recruiter\Bullhorn\Provider;

use SeattleWebCo\WPJobManager\Recruiter\Bullhorn\Log;
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
     * Returns authorization parameters based on provided options.
     *
     * @param  array $options
     * @return array Authorization parameters
     */
    protected function getAuthorizationParameters( array $options ) {
        if (empty($options['state'])) {
            $options['state'] = $this->getRandomState();
        }

        if (empty($options['scope'])) {
            $options['scope'] = $this->getDefaultScopes();
        }

        $options += [
            'response_type'   => 'code',
            'approval_prompt' => 'auto',
            'action'          => 'Login',
        ];

        $username = get_option( 'bullhorn_api_username' );
        $password = get_option( 'bullhorn_api_password' );

        if ( ! empty( $username ) ) {
            $options['username'] = $username;
        }

        if ( ! empty( $password ) ) {
            $options['password'] = $password;
        }

        if (is_array($options['scope'])) {
            $separator = $this->getScopeSeparator();
            $options['scope'] = implode($separator, $options['scope']);
        }

        // Store the state as it may need to be accessed later on.
        $this->state = $options['state'];

        // Business code layer might set a different redirect_uri parameter
        // depending on the context, leave it as-is
        if (!isset($options['redirect_uri'])) {
            $options['redirect_uri'] = $this->redirectUri;
        }

        $options['client_id'] = $this->clientId;

        return $options;
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
     * @return array|null
     */
    public function getDefaultScopes() {
        return null;
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
    public function get_access_token( $code = false, $force = false ) {
        $tokens = get_option( 'job_manager_bullhorn_token' );

        try {
            if ( $code ) {
                $token = $this->getAccessToken( 'authorization_code', array( 'code' => $code ) );
            } elseif ( isset( $tokens['expires'] ) && time() > $tokens['expires'] ) {
                $token = $this->getAccessToken( 'refresh_token', $tokens );
            }

            if ( isset( $token ) ) {
                $this->set_access_tokens( $token );
            }
        } catch ( \Exception $e ) {
           
        }

        return isset( $tokens['access_token'] ) ? $tokens['access_token'] : '';
    }


    /**
     * Sets provider access tokens
     *
     * @param AccessToken $token
     * @return void
     */
    protected function set_access_tokens( AccessToken $token ) {
        update_option( 'job_manager_bullhorn_token', array( 
            'access_token'  => $token->getToken(),
            'expires'       => $token->getExpires(),
            'refresh_token' => $token->getRefreshToken()
        ) );
    }


    public function getResourceOwnerDetailsUrl( AccessToken $token ) {}


    protected function checkResponse( ResponseInterface $response, $data ) {}

        
    protected function createResourceOwner( array $response, AccessToken $token ) {}

}
