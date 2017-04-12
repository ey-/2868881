<?php

namespace Drupal\newsletter2go\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\newsletter2go\Helpers\Api;
use Drupal\user\PermissionHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure maintenance settings for this site.
 */
class ConfigForm extends ConfigFormBase {
 use StringTranslationTrait;
  /**
   * The state keyvalue collection.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The permission handler.
   *
   * @var \Drupal\user\PermissionHandlerInterface
   */
  protected $permissionHandler;

  /**
   * Helper object.
   * 
   * @var Api
   */
  protected $helper;
  
  /**
   * Constructs N2Go configuration form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state keyvalue collection to use.
   * @param \Drupal\user\PermissionHandlerInterface $permission_handler
   *   The permission handler.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StateInterface $state, PermissionHandlerInterface $permission_handler) {
    parent::__construct($config_factory);
    $this->state = $state;
    $this->permissionHandler = $permission_handler;
    $this->helper = Api::getInstance();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('user.permissions')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'newsletter2go_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['newsletter2go.config'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('newsletter2go.config');
    $apiKey = $config->get('apikey');
    $formUniqueCode = $config->get('formUniqueCode');
    $nl2gStylesConfigObject = $config->get('widgetStyleConfig');

    $form['#attached']['library'][] = 'newsletter2go/admin';

    $queryParams['version'] = N2GO_PLUGIN_VERSION;
    $queryParams['apiKey'] = $apiKey;

    if ($queryParams['apiKey'] == '') {
      \Drupal::configFactory()->getEditable('newsletter2go.config')
        ->set('apikey', generateRandomString())
        ->save();
      $queryParams['apiKey'] = $config->get('apikey');
    }

    $queryParams['language'] = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $queryParams['url'] = rtrim($GLOBALS['base_url'], '/');
    $queryParams['callback'] = $queryParams['url'] . '/n2go/callback';

    $apiConnectUrl = N2GO_INTEGRATION_URL . '?' . http_build_query($queryParams);

    $authKey = $config->get('authkey');
    $forms = $this->helper->getForms($authKey);

    $hasForms = true;
    if ($forms === false) {
      $hasForms = false;
      $forms = array();
    }

    $selectForms = array();
    foreach ($forms as $f) {
      $selectForms[$f['hash']] = $f['name'];
    }

    $response = $this->helper->executeN2Go('get/attributes', array('key' => $apiKey));
    $color = $response['success'] ? 'greenyellow' : 'yellow';


    if (!strlen($formUniqueCode) > 0) {
      $errorMessage = "Please, enter the form unique code!";
    }

    if (isset($forms[$formUniqueCode]['type_subscribe'])) {
      $forms[$formUniqueCode]['type_subscribe'] ? $active = true : $active = false;
    } else {
      $active = false;
    }

    $base_path = \Drupal::url('<front>');

    // Because of broken HTML, use Markup::create() instead of strings to avoid sanitation.
    $form['api'] = array(
      '#tree' => true,
      'n2goSection' => array(
        '#markup' => Markup::create('    <div class="n2go-section">
        <img src="'. $base_path . drupal_get_path('module', 'newsletter2go') . '/images/banner_drupal_newsletter2go.png"'.'
			  
            width="92%; margin-left: 18px;" class="n2go_logo">
        <div class="n2go-block main-block" style="width:92%; margin-bottom: 30px; margin-left: 18px;">
            <div class="panel">
                <div class="panel-heading text-center">
                    <h3>So benutzen Sie die Anmeldeformulare</h3>
                </div>
                <div class="n2go-row">
                    <div class="n2go-block50">
                        <h4>als Block</h4>
                        <p>Unter Struktur -> Blocks können Sie ihr konfiguriertes Formular bequem in ihre
                            Seitenleisten und Menüs einfügen</p>
                    </div>

                    <div class="n2go-block50">
                        <h4>in Seiten</h4>
                        <p>Über den Shortcode <code>[newsletter2go:plugin]</code> können Sie ihr
                            konfiguriertes Anmeldeformular in allen Seiten über den Editor einbinden.<br/>
                            <br/>
                            Durch den Parameter <code>[newsletter2go:plugin:subscribe]</code> bzw. <code>[newsletter2go:plugin:unsubscribe]</code>
                            erzeugen Sie ein An- bzw. Abmeldeformular, soweit dieser Formular-Typ im Newsletter2Go-System ebenfalls aktiviert wurde.
                            Standardmäßig wird ein Anmeldeformular erzeugt.<br/><br/>
                            Mit der zusätzlichen Option <code>[newsletter2go:popup]</code> wird aus dem
                            eingebetten Formular ein Popup welches auf der spezifischen Seite eingeblendet wird.</p>
                    </div>
                </div>
                <div style="clear: both"></div>
            </div>
        </div>

    </div>
		<div class="n2go-section">
                            <div class="n2go-block50 main-block">
                                <div class="panel">
                                    <div class="panel-heading text-center">
                                        <h3>' . $this->t('Newsletter2Go Drupal Plugin') . '</h3>
                                    </div>
                                        <div class="panel-body">'),
      ),

      'connectButton' => ($hasForms === FALSE) ? [
        '#type' => 'markup',
        '#markup' => '  <div class="n2go-row">
                        <div class="n2go-block50"><span>' . $this->t('Login or Create Account') . '</span></div>
                        <div class="n2go-block25">
                          <div class="n2go-btn">
                                    <input type="hidden" name="apiKey" placeholder="" value=' . $apiKey . ' style="width:300px" readonly>
                                    <a href=' . $apiConnectUrl . ' target="_blank" style="padding:5px"><span class="fa fa-plug"></span> <span>' . $this->t('Login or Create Account') . '</span></a>
                                </div>'
        ] : [
          '#type' => 'submit',
          '#value' => $this->t('Disconnect'),
          '#prefix' => '<div class="n2go-row"><div class="n2go-block25">
        <span class="n2go-label-success"> <span class="fa fa-check margin-right-5"></span>
         <span>' .$this->t('Successfully connected') . '</span></span><br/><br/>',
          '#suffix' => '</div></div>',
          '#attributes' => ['class' => ['n2go-disconnect-btn']],
        ],

      'selectBody' => array(
        '#markup' => Markup::create('  <div class="n2go-row">
                      <div class="n2go-block50"><span>' .$this->t('Choose the connected subscribe form') . '</span></div>
                        <div class="n2go-block25">'),
      ),

      'formUniqueCode' => array(
        '#id' => 'formUniqueCode',
        '#type' => 'select',
        '#default_value' => $formUniqueCode,
        '#options' => $selectForms,
      ),

      'endSelectBody' => array(
        '#markup' => Markup::create('</div></div>'),
      ),

      'endPanelBody' => array(
        '#markup' => Markup::create('</div>'),
      ),

      'colorPanel' => array(
        '#markup' => Markup::create('    <div class="n2go-row">
                    <div class="n2go-block50"><span>' .$this->t('Configure your Drupal widget') . '</span></div>
                    <div class="n2go-block50">'),
      ),

      'formUniqueCode' => array(
        '#id' => 'formUniqueCode',
        '#name' => 'formUniqueCode',
        '#type' => 'select',
        '#default_value' => $formUniqueCode,
        '#options' => $selectForms,
        '#attributes' => array(
          'class' => array('n2go-select'),
        ),
      ),

      'form.background-color' => array(
        '#id' => 'valueInputFBC',
        '#name' => 'form.background-color',
        '#type' => 'textfield',
        '#default_value' => 'FFFFFF',
        '#theme_wrappers' => array(),
        '#prefix' => '<label for="formBackgroundColor">' .$this->t('Form background color') . '</label>
                    <div class="n2go-cp input-group">
                        <span class="n2go-input-group-addon">#</span>',
        '#suffix' => '<button id="styleInputFBC" class="input-group-btn jscolor{valueElement:\'valueInputFBC\', styleElement:\'styleInputFBC\'}">
                        </button>
                    </div>',
        '#attributes' => array(
          'class' => array('n2go-colorField', 'form-control', 'n2go-text-right'),
        ),
      ),
      'label.color' => array(
        '#id' => 'valueInputLC',
        '#type' => 'textfield',
        '#name' => 'label.color',
        '#default_value' => '222222',
        '#theme_wrappers' => array(),
        '#prefix' => '<label for="formBackgroundColor">' .$this->t('Label color') . '</label>
                    <div class="n2go-cp input-group">
                        <span class="n2go-input-group-addon">#</span>',
        '#suffix' => '<button id="styleInputLC" class="input-group-btn jscolor{valueElement:\'valueInputLC\', styleElement:\'styleInputLC\'}">
                        </button>
                    </div>',
        '#attributes' => array(
          'class' => array('n2go-colorField', 'form-control', 'n2go-text-right'),
        ),
      ),
      'input.color' => array(
        '#id' => 'valueInputIC',
        '#type' => 'textfield',
        '#name' => 'input.color',
        '#default_value' => '222222',
        '#theme_wrappers' => array(),
        '#prefix' => '<label for="formBackgroundColor">' .$this->t('Input color') . '</label>
                        <div class="n2go-cp input-group">
                            <span class="n2go-input-group-addon">#</span>',
        '#suffix' => '<button id="styleInputIC" class="input-group-btn jscolor{valueElement:\'valueInputIC\', styleElement:\'styleInputIC\'}">
                            </button>
                        </div>',
        '#attributes' => array(
          'class' => array('n2go-colorField', 'form-control', 'n2go-text-right'),
        ),
      ),
      'input.border-color' => array(
        '#id' => 'valueInputIBrC',
        '#type' => 'textfield',
        '#name' => 'input.border-color',
        '#default_value' => 'CCCCCC',
        '#theme_wrappers' => array(),
        '#prefix' => '<label for="formBackgroundColor">' .$this->t('Input border color') . '</label>
                            <div class="n2go-cp input-group">
                                <span class="n2go-input-group-addon">#</span>',
        '#suffix' => '<button id="styleInputIBrC" class="input-group-btn jscolor{valueElement:\'valueInputIBrC\', styleElement:\'styleInputIBrC\'}">
                                </button>
                            </div>',
        '#attributes' => array(
          'class' => array('n2go-colorField', 'form-control', 'n2go-text-right'),
        ),
      ),
      'input.background-color' => array(
        '#id' => 'valueInputIBC',
        '#type' => 'textfield',
        '#name' => 'input.background-color',
        '#default_value' => 'FFFFFF',
        '#theme_wrappers' => array(),
        '#prefix' => '<label for="formBackgroundColor">' .$this->t('Input background color') . '</label>
                                <div class="n2go-cp input-group">
                                    <span class="n2go-input-group-addon">#</span>',
        '#suffix' => '<button id="styleInputIBC" class="input-group-btn jscolor{valueElement:\'valueInputIBC\', styleElement:\'styleInputIBC\'}">
                                    </button>
                                </div>',
        '#attributes' => array(
          'class' => array('n2go-colorField', 'form-control', 'n2go-text-right'),
        ),
      ),
      'button.color' => array(
        '#id' => 'valueInputBC',
        '#type' => 'textfield',
        '#name' => 'button.color',
        '#default_value' => 'FFFFFF',
        '#theme_wrappers' => array(),
        '#prefix' => '<label for="formBackgroundColor">' .$this->t('Button text color') . '</label>
                                    <div class="n2go-cp input-group">
                                        <span class="n2go-input-group-addon">#</span>',
        '#suffix' => '<button id="styleInputBC" class="input-group-btn jscolor{valueElement:\'valueInputBC\', styleElement:\'styleInputBC\'}">
                                        </button>
                                    </div>',
        '#attributes' => array(
          'class' => array('n2go-colorField', 'form-control', 'n2go-text-right'),
        ),
      ),
      'button.background-color' => array(
        '#id' => 'valueInputBBC',
        '#type' => 'textfield',
        '#name' => 'button.background-color',
        '#default_value' => '00BAFF',
        '#theme_wrappers' => array(),
        '#prefix' => '<label for="formBackgroundColor">' .$this->t('Button background color') . '</label>
                                        <div class="n2go-cp input-group">
                                            <span class="n2go-input-group-addon">#</span>',
        '#suffix' => '<button id="styleInputBBC" class="input-group-btn jscolor{valueElement:\'valueInputBBC\', styleElement:\'styleInputBBC\'}">
                                            </button>
                                        </div>',
        '#attributes' => array(
          'class' => array('n2go-colorField', 'form-control', 'n2go-text-right'),
        ),
      ),

      'endColorPanel' => array(
        '#markup' => Markup::create('</div>
             </div>'),
      ),

      'endN2GoPanel' => array(
        '#markup' => Markup::create('</div>'),
      ),

      'endN2GoMainBlock' => array(
        '#markup' => Markup::create('</div>'),
      ),

      'preview' => array(
        '#markup' => Markup::create('<div class="n2go-block50 main-block">
            <div class="panel">
                <div class="panel-heading text-center">
                    <h3>' .$this->t('This is how your form will look like') . '</h3>
                </div>
                <div class="panel-body">
                    <ul id="n2gButtons" class="nav nav-tabs">
                        ' . ((isset($forms[$formUniqueCode]['type_subscribe']) && $forms[$formUniqueCode]['type_subscribe']) ? '<li id="btnShowPreviewSubscribe" class="active">' .$this->t('Subscription-Form') . '</li>' : '') .
          '' . ((isset($forms[$formUniqueCode]['type_unsubscribe']) && $forms[$formUniqueCode]['type_unsubscribe']) ? '<li id="btnShowPreviewUnsubscribe" ' . (!$active ? 'class="active"' : '') . '>' .$this->t('Unsubscription-Form') . '</li>' : '') .
          '<li id="btnShowConfig" class="">' .$this->t('Source') . '</li>
                    </ul>
                    <div id="preview-form-panel" class="preview-pane">
                        <div id="widgetPreview" ' . (!$active ? 'style="display:none"' : '') . '>' .

          (!isset($errorMessage) ? ' <script id="n2g_script_subscribe">
                                </script>'

            : '<h3 class="n2go-error-general">' . $errorMessage . '</h3>') .
          '</div>
                        <div id="widgetPreviewUnsubscribe" ' . ($active ? 'style="display:none"' : '') . '><script id="n2g_script_unsubscribe"></script></div>
                        <div id="nl2gStylesConfig" class="preview-pane">
                            <textarea id="widgetStyleConfig" name="widgetStyleConfig">' . json_encode($nl2gStylesConfigObject) . '</textarea>
                        </div>
                    </div>
                    <div>
                    <a id ="resetStyles" value="resetStyles" class="save-btn n2go-reset-styles-btn" name="resetStyles">' .$this->t('Reset styles') . '</a>
                    </div>
                </div>
            </div>
        </div>'),
      ),

    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    return;
    // Do not validate disconnect requests.
    if ('Disconnect' === ($form_state->getTriggeringElement()['#value'])->getUntranslatedString()) {
      return;
    }
    if (!$form_state->hasValue('apikey')) {
      $form_state->setErrorByName('apikey', t('You must enter API key'));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ('Disconnect' === ($form_state->getTriggeringElement()['#value'])->getUntranslatedString()) {
      // Reset default values to NULL.
      $this->resetValues();
      parent::submitForm($form, $form_state);
      return;
    }
    $config_factory = \Drupal::configFactory()
      ->getEditable('newsletter2go.config');
    $userInput = $form_state->getUserInput();
    foreach ([
               'formUniqueCode',
               'colors',
               'widgetStyleConfig',
               'apikey',
             ] as $name) {
      // We also have to submit null values.
      $config_factory->set($name, $userInput[$name]);
    }
    $config_factory->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Helper function to reset values.
   */
  protected function resetValues() {
    $config_factory = \Drupal::configFactory()->getEditable('newsletter2go.config');
    $config_factory->set('authkey', null);
    $config_factory->set('accessToken', null);
    $config_factory->set('refreshToken', null);
    $config_factory->set('formUniqueCode', null);
    $config_factory->set('widgetStyleConfig', null);
    $config_factory->save();
  }
}
