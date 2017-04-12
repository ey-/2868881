<?php

namespace Drupal\newsletter2go\Plugin\Mail;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Mail\Plugin\Mail\PhpMail;
use Drupal\Core\Site\Settings;
use Drupal\newsletter2go\Helpers\Api;

/**
 * Mail backend to send messages using newsletter2go API.
 *
 * @Mail(
 *   id = "newsletter2go_mail",
 *   label = @Translation("Newsletter2go mailer"),
 *   description = @Translation("Sends the message using newsletter2go API")
 * )
 */
class N2goMail extends PhpMail {
  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * @var \Drupal\newsletter2go\Helpers\Api
   */
  protected $api;

  public function __construct() {
    $this->config = \Drupal::config('newsletter2go.config');
    $this->api = Api::getInstance();
  }

  /**
   * Sends an email message.
   *
   * @param array $message
   *   A message array, as described in hook_mail_alter().
   *
   * @return bool
   *   TRUE if the mail was successfully accepted, otherwise FALSE.
   *
   * @see https://www.newsletter2go.com/pr/api/Newsletter2Go_API_Doku_latest_en.pdf
   * @see \Drupal\Core\Mail\MailManagerInterface::mail()
   */
  public function mail(array $message) {
    $line_endings = Settings::get('mail_line_endings', PHP_EOL);
    $mail_body = preg_replace('@\r?\n@', $line_endings, $message['body']);
    // @todo: move all config logic to Api class.
    $result = $this->api->executeN2Go('send/email', [
      'key' => $this->config->get('authkey'),
      'to' => $message['to'],
      'from' => 'mike.lange@ffwagency.com',
      'subject' => Unicode::mimeHeaderEncode($message['subject']),
      'body' => $mail_body,
      'text' => strip_tags($mail_body),
    ]);
    return (bool)$result['success'];
  }

}
