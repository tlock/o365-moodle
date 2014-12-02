<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package auth_oidc
 * @author James McQuillan <james.mcquillan@remote-learner.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2014 onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 */

namespace auth_oidc;

/**
 * OpenID Connect Client
 */
class oidcclient {
    /** @var \auth_oidc\httpclientinterface An HTTP client to use. */
    protected $httpclient;

    /** @var string The client ID. */
    protected $clientid;

    /** @var string The client secret. */
    protected $clientsecret;

    /** @var string The client redirect URI. */
    protected $redirecturi;

    /** @var array Array of endpoints. */
    protected $endpoints = [];

    /**
     * Constructor.
     *
     * @param \auth_oidc\httpclientinterface $httpclient An HTTP client to use for background communication.
     */
    public function __construct(\auth_oidc\httpclientinterface $httpclient) {
        $this->httpclient = $httpclient;
    }

    /**
     * Set client details/credentials.
     *
     * @param string $id The registered client ID.
     * @param string $secret The registered client secret.
     * @param string $redirecturi The registered client redirect URI.
     */
    public function setcreds($id, $secret, $redirecturi) {
        $this->clientid = $id;
        $this->clientsecret = $secret;
        $this->redirecturi = $redirecturi;
    }

    /**
     * Get the set client ID.
     *
     * @return string The set client ID.
     */
    public function get_clientid() {
        return (isset($this->clientid)) ? $this->clientid : null;
    }

    /**
     * Get the set client secret.
     *
     * @return string The set client secret.
     */
    public function get_clientsecret() {
        return (isset($this->clientsecret)) ? $this->clientsecret : null;
    }

    /**
     * Get the set redirect URI.
     *
     * @return string The set redirect URI.
     */
    public function get_redirecturi() {
        return (isset($this->redirecturi)) ? $this->redirecturi : null;
    }

    /**
     * Set OIDC endpoints.
     *
     * @param array $endpoints Array of endpoints. Can have keys 'auth', and 'token'.
     */
    public function setendpoints($endpoints) {
        foreach ($endpoints as $type => $uri) {
            if (clean_param($uri, PARAM_URL) !== $uri) {
                throw new \Exception('Invalid Endpoint URI received.');
            }
            $this->endpoints[$type] = $uri;
        }
    }

    public function get_endpoint($endpoint) {
        return (isset($this->endpoints[$endpoint])) ? $this->endpoints[$endpoint] : null;
    }

    /**
     * Get an array of authorization request parameters.
     *
     * @return array Array of request parameters.
     */
    protected function getauthrequestparams() {
        $nonce = 'N'.uniqid();
        return [
            'response_type' => 'code',
            'client_id' => $this->clientid,
            'scope' => 'openid profile email',
            'nonce' => $nonce,
            'response_mode' => 'form_post',
            'resource' => 'https://graph.windows.net',
            'state' => $this->getnewstate($nonce),
            'prompt' => 'login',
        ];
    }

    /**
     * Generate a new state parameter.
     *
     * @return string The new state value.
     */
    protected function getnewstate($nonce) {
        global $DB;
        $staterec = new \stdClass;
        $staterec->sesskey = sesskey();
        $staterec->state = random_string(15);
        $staterec->nonce = $nonce;
        $staterec->timecreated = time();
        $DB->insert_record('auth_oidc_state', $staterec);
        return $staterec->state;
    }

    /**
     * Perform an authorization request by redirecting resource owner's user agent to auth endpoint.
     */
    public function authrequest() {
        global $DB;
        if (empty($this->clientid)) {
            throw new \Exception('Please set client credentials with setcreds');
        }

        if (empty($this->endpoints['auth'])) {
            throw new \Exception('No auth endpoint set. Please set with $this->setendpoints');
        }

        $params = $this->getauthrequestparams();
        $redirecturl = new \moodle_url($this->endpoints['auth'], $params);
        redirect($redirecturl);
    }

    /**
     * Exchange an authorization code for an access token.
     *
     * @param string $tokenendpoint The token endpoint URI.
     * @param string $code An authorization code.
     * @return array Received parameters.
     */
    public function tokenrequest($code) {
        if (empty($this->endpoints['token'])) {
            throw new \Exception('No token endpoint set. Please set with $this->setendpoints');
        }

        $params = [
            'client_id' => $this->clientid,
            'client_secret' => $this->clientsecret,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirecturi,
        ];

        try {
            $returned = $this->httpclient->post($this->endpoints['token'], $params);
            return @json_decode($returned, true);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
}