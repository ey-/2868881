<?php

namespace Drupal\newsletter2go\Helpers;

class Api {
    private $version = 4000;
    private static $instance = null;
    private $getParams;
    private $postParams;
    private $apikey;

    private $logger;
    protected $debugMode = FALSE;

    private function __construct() {
      $this->logger = \Drupal::logger('newsletter2go');
    }

    public static function getInstance() {
      return self::$instance ? : new Api();
    }

  /**
   * Turn debugging mode on and off
   *
   * @param bool $debugMode
   */
  public function setDebug($debugMode) {
    $this->debugMode = (bool)$debugMode;
  }

  public function processRequest($apikey, $getParams, $postParams) {
    $this->apikey = $apikey;
    if ($apikey == NULL && isset($postParams['apikey'])) {
      $this->apikey = $postParams['apikey'];
    }
    $this->getParams = $getParams;
    $this->postParams = $postParams;
    $result = array('success' => 1);

    if (!$this->checkApiKey()) {
      $result = ResponseHelper::generateErrorResponse('Invalid or missing API key!', ResponseHelper::ERRNO_PLUGIN_CREDENTIALS_WRONG);
    }
    else {
      switch ($this->postParams['action']) {
        case 'test':
          $result['message'] = $this->test();
          break;
        case 'getPost':
          $post = $this->getPost();
          if (!$post) {
            $result = ResponseHelper::generateErrorResponse('Post with given id not found!', ResponseHelper::ERRNO_PLUGIN_OTHER);
          }
          else {
            $result = ResponseHelper::generateSuccessResponse(array('post' => $post));
          }
          break;
        case 'getPluginVersion':
          $version = $this->getPluginVersion();
          $result = ResponseHelper::generateSuccessResponse(array('version' => $version));
          break;
        default:
          $result = ResponseHelper::generateErrorResponse('Invalid action!', ResponseHelper::ERRNO_PLUGIN_OTHER);
          break;
      }
    }

    return $result;
  }

    protected function test()
    {
        return t('Connected');
    }

    protected function getPost()
    {
        if (empty($this->postParams['id']) && is_int($this->postParams['id'])) {
            return null;
        }
        // @todo: refactor database calls.
        $id = $this->postParams['id'];
        $query = db_select('node', 'n');
        $query->leftJoin('field_data_body', 'd', 'd.entity_id = n.nid');
        $query->leftJoin('users', 'u', 'u.uid = n.uid');
        $query->condition('n.nid', $id)
                ->condition('n.type', 'article');
        $query->addField('n', 'nid', 'itemId');
        $query->addField('n', 'title', 'title');
        $query->addField('n', 'created', 'date');
        $query->addField('u', 'name', 'author');
        $query->addField('d', 'body_value', 'description');
        $query->addField('d', 'body_summary', 'shortDescription');
        $result = $query->execute()->fetchAssoc();
        if (!$result) {
            return null;
        }
        
        $result['url'] = url('', array('absolute' => true));
        $result['link'] = 'node/' . $id;
        $result['date'] = date('Y-m-d H:i:s', $result['date']);
        $result['category'] = array();
        
        //tags
        $query = db_select('field_data_field_tags', 't');
        $query->innerJoin('taxonomy_term_data', 'dt', 't.field_tags_tid = dt.tid');
        $query->condition('t.entity_id', $id);
        $query->addField('dt', 'name', 'name');
        $result['tags'] = $query->execute()->fetchAll();
        foreach ($result['tags'] as &$tag) {
            $tag = $tag->name;
        }
        
        //images
        $query = db_select('field_data_field_image', 'fi');
        $query->innerJoin('file_managed', 'f', 'fi.field_image_fid = f.fid');
        $query->condition('fi.entity_id', $id);
        $query->addField('f', 'uri', 'uri');
        $result['images'] = $query->execute()->fetchAll();
        foreach ($result['images'] as &$image) {
            $image = file_create_url($image->uri);
        }
        
        return $result;
    }

    protected function checkApiKey()
    {
      return $this->apikey === \Drupal::config('newsletter2go.config')->get('apikey');
    }

    protected function getPluginVersion(){
        return $this->version;
    }

    /**
     * Creates request and returns response. New API and access token.
     *
     * @param string $action
     * @param array $post
     * 
     * @return string
     */
    public function execute($action, $post) {
        $config = \Drupal::config('newsletter2go.config');
        $access_token = $config->get('accessToken');
        $responseJson = $this->executeRequest($action, $access_token, $post);

        if ($responseJson['status_code'] == 403 || $responseJson['status_code'] == 401) {
            $this->refreshTokens();
            $access_token = $config->get('accessToken');
            $responseJson = $this->executeRequest($action, $access_token, $post);
        }

        return $responseJson;
    }

    /**
     * Creates request and returns response. New API and access token.
     *
     * @param string $action
     * @param string $access_token
     * @param array $post
     * 
     * @return string
     * 
     * @internal param mixed $params
     */
    private function executeRequest($action, $access_token, $post) {
        $apiUrl = N2GO_API_URL;
        
        $cURL = curl_init();
        curl_setopt($cURL, CURLOPT_URL, $apiUrl . $action);
        curl_setopt($cURL, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($cURL, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $access_token));

        if (!empty($post)) {
            $postData = '';
            foreach ($post as $k => $v) {
                $postData .= urlencode($k) . '=' . urlencode($v) . '&';
            }
            $postData = substr($postData, 0, -1);

            curl_setopt($cURL, CURLOPT_POST, 1);
            curl_setopt($cURL, CURLOPT_POSTFIELDS, $postData);
        }

        curl_setopt($cURL, CURLOPT_SSL_VERIFYPEER, FALSE);
        $response = curl_exec($cURL);
        if ($this->debugMode) {
          $this->logger->debug(__LINE__ . ':' . $action . ':' . $postData . "\r\n" . $response);
        }
        $response = json_decode($response, TRUE);
        $status = curl_getinfo($cURL);
        $response['status_code'] = $status['http_code'];

        curl_close($cURL);

        return $response;
    }

    /**
     * Creates request and returns response, refresh access token.
     *
     * @return bool
     * 
     * @internal param mixed $params
     */
    private function refreshTokens() {
        $config = \Drupal::config('newsletter2go.config');
        $config_factory = \Drupal::configFactory()
          ->getEditable('newsletter2go.config');
        $authKey = $config->get('authkey');
        $auth = base64_encode($authKey);
        $refreshToken = $config->get('refreshToken');
        $refreshPost = array(
          'refresh_token' => $refreshToken,
          'grant_type' => N2GO_REFRESH_GRANT_TYPE,
        );
        $post = http_build_query($refreshPost);

        $url = N2GO_API_URL . 'oauth/v2/token';

        $header = array(
          'Authorization: Basic ' . $auth,
          'Content-Type: application/x-www-form-urlencoded'
        );

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

        curl_setopt($curl, CURLOPT_POST, TRUE);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $json_response = curl_exec($curl);
        if ($this->debugMode) {
          $this->logger->debug(__LINE__ . ':' . $post . "\r\n" . $json_response);
        }
        curl_close($curl);

        $response = json_decode($json_response);


        if (isset($response->access_token) && !empty($response->access_token)) {
            $config_factory->set('accessToken', $response->access_token);
        }
        if (isset($response->refresh_token) && !empty($response->refresh_token)) {
            $config_factory->set('refreshToken', $response->refresh_token);
        }
        $config_factory->save();

        return TRUE;
    }

    /**
     * Creates request and returns response.
     *
     * @param string $action
     * @param mixed $post
     * 
     * @return array
     */
    public function executeN2Go($action, $post) {
      $cURL = curl_init();
      curl_setopt($cURL, CURLOPT_URL, "https://www.newsletter2go.com/en/api/$action/");
      curl_setopt($cURL, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($cURL, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . \Drupal::config('newsletter2go.config')
          ->get('accessToken'),
      ]);

      $postData = '';
      foreach ($post as $k => $v) {
        $postData .= urlencode($k) . '=' . urlencode($v) . '&';
      }
      $postData = substr($postData, 0, -1);

      curl_setopt($cURL, CURLOPT_POST, 1);
      curl_setopt($cURL, CURLOPT_POSTFIELDS, $postData);
      curl_setopt($cURL, CURLOPT_SSL_VERIFYPEER, FALSE);

      $response = curl_exec($cURL);
      if ($this->debugMode) {
        $this->logger->debug(__LINE__ . ':' . $postData . "\r\n" . $response);
      }
      curl_close($cURL);

      return json_decode($response, TRUE);
    }

    /**
     * Get forms from N2GO API.
     * 
     * @param string $authKey
     * @return array|false
     */
    public function getForms($authKey = '') {
        $result = FALSE;

        if (strlen($authKey) > 0) {
            $form = $this->execute('forms/all?_expand=1', array());
            if (isset($form['status']) && $form['status'] >= 200 && $form['status'] < 300) {
                $result = array();
                foreach ($form['value'] as $value) {
                    $key = $value['hash'];
                    $result[$key]['name'] = $value['name'];
                    $result[$key]['hash'] = $value['hash'];
                    $result[$key]['type_subscribe'] = $value['type_subscribe'];
                    $result[$key]['type_unsubscribe'] = $value['type_unsubscribe'];
                }
            }
        }

        return $result;
    }

}
