<?php

namespace Bexio;

use Bexio\Auth\OAuth2;
use Curl\Curl;

class Client
{
    const API_URL = 'https://api.bexio.com';

    const OAUTH2_AUTH_URL = 'https://office.bexio.com/oauth/authorize';
    const OAUTH2_TOKEN_URI = 'https://office.bexio.com/oauth/access_token';
    const OAUTH2_REFRESH_TOKEN_URI = 'https://office.bexio.com/oauth/refresh_token';

    /**
     * @var array $config
     */
    private $config;

    /**
     * @var
     */
    private $accessToken;

    /**
     * @var
     */
    private $auth;

    private $jsonDecodeAssoc = false;
    
    /**
     * Client constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = array())
    {
        $this->config = array_merge(
            [
                'clientId'     => '',
                'clientSecret' => '',
                'redirectUri'  => null,
            ],
            $config
        );
    }

    public function setClientId($clientId)
    {
        $this->config['clientId'] = $clientId;
    }

    public function getClientId()
    {
        return $this->config['clientId'];
    }

    public function setClientSecret($clientId)
    {
        $this->config['clientSecret'] = $clientId;
    }

    public function getClientSecret()
    {
        return $this->config['clientSecret'];
    }

    public function setRedirectUri($redirectUri)
    {
        $this->config['redirectUri'] = $redirectUri;
    }

    public function getRedirectUri()
    {
        return $this->config['redirectUri'];
    }

    /** @deprecated Is no longer used in API v2.0 */
    public function getOrg()
    {
        return $this->accessToken['org'];
    }

    /**
     * @param $accessToken
     * @throws \Exception
     */
    public function setAccessToken($accessToken)
    {
        if (is_string($accessToken)) {
            if ($json = json_decode($accessToken, true)) {
                $accessToken = $json;
            } else {
                $accessToken = [
                    'access_token' => $accessToken,
                ];
            }
        }

        if ($accessToken == null) {
            throw new \Exception('Invalid json token');
        }

        if (!isset($accessToken['access_token'])) {
            throw new \Exception("Invalid token format");
        }
        $this->accessToken = $accessToken;
    }

    public function getAccessToken()
    {
        return $this->accessToken;
    }

    public function isAccessTokenExpired()
    {
        if (!$this->accessToken) {
            return true;
        }

        $created = 0;
        $expiresIn = 0;

        if (isset($this->accessToken['created'])) {
            $created = $this->accessToken['created'];
        }

        if (isset($this->accessToken['expires_in'])) {
            $expiresIn = $this->accessToken['expires_in'];
        }

        return ($created + ($expiresIn - 30)) < time();
    }

    public function getRefreshToken()
    {
        if (isset($this->accessToken['refresh_token'])) {
            return $this->accessToken['refresh_token'];
        }
    }

    public function fetchAuthCode()
    {
        $auth = $this->getOAuth2Service();
        $auth->setRedirectUri($this->getRedirectUri());


    }

    public function fetchAccessTokenWithAuthCode($code)
    {
        if (strlen($code) === 0) {
            throw new \Exception("Invalid code");
        }

        $auth = $this->getOAuth2Service();
        $auth->setCode($code);
        $auth->setRedirectUri($this->getRedirectUri());

        $credentials = $auth->fetchAuthToken();

        if ($credentials && isset($credentials['access_token'])) {
            $credentials['created'] = time();
            $this->setAccessToken($credentials);
        }

        return $credentials;
    }

    public function refreshToken($refreshToken = null)
    {
        if ($refreshToken === null) {
            if (!isset($this->accessToken['refresh_token'])) {
                throw new \Exception('Refresh token must be passed or set as part of the accessToken');
            }

            $refreshToken = $this->accessToken['refresh_token'];
        }

        $auth = $this->getOAuth2Service();
        $auth->setRefreshToken($refreshToken);

        $credentials = $auth->fetchAuthToken();

        if ($credentials && isset($credentials['access_token'])) {
            $credentials['created'] = time();
            if (!isset($credentials['refresh_token'])) {
                $credentials['refresh_token'] = $refreshToken;
            }
            $this->setAccessToken($credentials);

            return $credentials;
        }

        throw new \Exception('Illegal access token received when token was refreshed');
    }

    /**
     * @return OAuth2
     */
    public function getOAuth2Service()
    {
        if (!isset($this->auth)) {
            $this->auth = new OAuth2(
                [
                    'clientId'                  => $this->getClientId(),
                    'clientSecret'              => $this->getClientSecret(),
                    'authorizationUri'          => self::OAUTH2_AUTH_URL,
                    'tokenCredentialUri'        => self::OAUTH2_TOKEN_URI,
                    'refreshTokenCredentialUri' => self::OAUTH2_REFRESH_TOKEN_URI,
                    'redirectUri'               => $this->getRedirectUri(),
                    'issuer'                    => $this->config['clientId'],
                ]
            );
        }

        return $this->auth;
    }

    protected function getRequest()
    {
        $accessToken = $this->getAccessToken();

        $curl = new Curl();
        $curl->setHeader('Accept', 'application/json');
        $curl->setHeader('Authorization', 'Bearer '.$accessToken['access_token']);

        return $curl;
    }

    public function get($path, array $parameters = [], string $version = '2.0')
    {
        $request = $this->getRequest();
        $request->get(self::API_URL.'/'. $version . '/' . $path, $parameters);

        return json_decode($request->response, $this->jsonDecodeAssoc);
    }

    public function post($path, array $parameters = [], string $version = '2.0')
    {
        $request = $this->getRequest();
        $request->post(self::API_URL.'/'. $version . '/' . $path, json_encode($parameters));

        return json_decode($request->response, $this->jsonDecodeAssoc);
    }

    public function postWithoutPayload($path, string $version = '2.0')
    {
        $request = $this->getRequest();
        $request->post(self::API_URL.'/'. $version . '/' . $path);

        return json_decode($request->response, $this->jsonDecodeAssoc);
    }

    public function put($path, array $parameters = [], string $version = '2.0')
    {
        $request = $this->getRequest();
        $request->put(self::API_URL.'/'. $version . '/' . $path, $parameters);

        return json_decode($request->response, $this->jsonDecodeAssoc);
    }

    public function delete($path, array $parameters = [], string $version = '2.0')
    {
        $request = $this->getRequest();
        $request->delete(self::API_URL.'/'. $version . '/' . $path, $parameters);

        return json_decode($request->response, $this->jsonDecodeAssoc);
    }

	public function setjsonDecodeAssoc(bool $jsonDecodeAssoc): self
	{
		$this->jsonDecodeAssoc = $jsonDecodeAssoc;
		return $this;
	}
}