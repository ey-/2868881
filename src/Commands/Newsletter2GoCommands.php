<?php

namespace Drupal\newsletter2go\Commands;

use Drupal\newsletter2go\Helpers\Api;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
class Newsletter2GoCommands extends DrushCommands {
  /**
   * updates refresh and access Token for newsletter2go.
   *
   * @command newsletter2go:retrieve-access-token
   * @aliases nl2go-token
   * @usage newsletter2go:retrieve-access-token
   */
  public function retrieveAccessToken() {

    $helper = Api::getInstance();
    $helper->retrieveAccessToken();

    $this->output()->writeln('Done');
  }

}
