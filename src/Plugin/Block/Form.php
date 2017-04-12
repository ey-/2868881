<?php

namespace Drupal\newsletter2go\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\newsletter2go\Helpers\Api;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Newsletter2Go subscription form block.
 *
 * @Block(
 *   id = "newsletter2go",
 *   admin_label = @Translation("Newsletter2Go"),
 * )
 */
class Form extends BlockBase implements ContainerFactoryPluginInterface {

  /** @var  ContainerInterface */
  protected $container;

  public function __construct(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $this->container = $container;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'form_type' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access newsletter2go content');
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = \Drupal::config('newsletter2go.config');
    $formUniqueCode = $config->get('formUniqueCode');

    $forms = Api::getInstance()->getForms($config->get('authkey'));
    if (empty($forms)) {
      return parent::blockForm($form, $form_state);
    }
    $options = [];
    $subscribe = $unsubscribe = FALSE;
    foreach ($forms as $form) {
      if ($formUniqueCode == $form['hash']) {
        $subscribe = $form['type_subscribe'];
        $unsubscribe = $form['type_unsubscribe'];
      }
    }

    $subscribe == TRUE ? $options['subscribe'] = t('Subscribe-Form') : '';
    $unsubscribe == TRUE ? $options['unsubscribe'] = t('Unsubscribe-Form') : '';

    $form['form_type'] = array(
      '#type' => 'select',
      '#title' => t('Form type'),
      '#default_value' => $config->get('formType'),
      '#options' => $options,
    );

    return parent::blockForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    \Drupal::configFactory()->getEditable('newsletter2go.config')
      ->set('formType', $form_state->getValue('form_type'))
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = \Drupal::config('newsletter2go.config');
    $formUniqueCode = $config->get('formUniqueCode');
    $n2gConfig = $config->get('widgetStyleConfig');

    $formType = $config->get('formType');
    if (empty($type)) {
      !empty($formType) ? $type = $formType : $type = 'subscribe';
    }

    empty($func) ? $func = 'createForm' : '';
    $uniqueId = uniqid();

    $params = "'$type:$func', " . $n2gConfig;
    if ($func == 'createPopup') {
      $params .= ", 5"; // '5' seconds delay
    }

    $block['subject']['#markup'] = t('<h2>Newsletter2Go</h2>');

    $block['newsletter2go_script'] = [
      [
        '#type' => 'html_tag',
        '#tag' => 'script',
        '#value' => '!function(e,t,n,c,r,a,i){e.Newsletter2GoTrackingObject=r,e[r]=e[r]||function(){(e[r].q=e[r].q||[]).push(arguments)},e[r].l=1*new Date,a=t.createElement(n),i=t.getElementsByTagName(n)[0],a.async=1,a.src=c,i.parentNode.insertBefore(a,i)}(window,document,"script","//static.newsletter2go.com/utils.js","n2g");
     n2g(\'create\',\'' . $formUniqueCode . '\');
     n2g(' . $params . '' . ($uniqueId ? ',"' . $uniqueId . '"' : "") . ');',
        '#attributes' => array('id' => ($uniqueId ? $uniqueId : "n2g_script")),
      ],
      'newsletter2go_script'
    ];
    return $block;
  }

  /**
   * Cache the form per user role.
   *
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $cache_tags = parent::getCacheTags();
    $user_roles = \Drupal::currentUser()->getRoles();
    $role_tag = [];
    foreach (array_keys($user_roles) as $key) {
      $role_tag[] = 'role:' . $key;
    }
    return Cache::mergeTags($cache_tags, $role_tag);
  }

}
