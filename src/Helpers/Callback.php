<?php

namespace Drupal\newsletter2go\Helpers;

class Callback
{
    private static $instance = null;

    private function __construct()
    {

    }

    public static function getInstance()
    {
        return self::$instance ? : new Callback();
    }

    public function processCallback($postParams)
    {
        $config_factory = \Drupal::configFactory()->getEditable('newsletter2go.config');
        $key_value_store = \Drupal::keyValue('newsletter2go');
        if (isset($postParams['auth_key']) && !empty($postParams['auth_key'])) {
            $authKey = $postParams['auth_key'];
            $config_factory->set('authkey', $authKey);
        }        
        if (isset($postParams['access_token']) && !empty($postParams['access_token'])) {
            $accessToken = $postParams['access_token'];
            $key_value_store->set('accessToken', $accessToken);
        }       
        if (isset($postParams['refresh_token']) && !empty($postParams['refresh_token'])) {
            $refreshToken = $postParams['refresh_token'];
            $key_value_store->set('refreshToken', $refreshToken);
        }
        $config_factory->save();

        return ['success' => 1];
    }
}
