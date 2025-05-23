<?php

/**
 * @file
 * Contains \Drupal\summergame\Form\SummerGameHomeCodeForm.
 */

namespace Drupal\summergame\Form;

use Drupal\user\Entity\User;
use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SummerGameHomeCodeForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'summergame_home_code_form';
  }

  private function branches() {
    return [
      'downtown' => 'Downtown',
      'malletts' => 'Malletts Creek',
      'pittsfield' => 'Pittsfield',
      'traverwood' => 'Traverwood',
      'westgate' => 'Westgate',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $uid = 0) {
    // Check access to Account
    $user = User::load(\Drupal::currentUser()->id());
    if ($user->get('uid')->value == $uid ||
        $user->hasPermission('manage summergame')) {
      $account = User::load($uid);
      if (isset($account)) {
        $form = [
          '#attributes' => ['class' => 'form-width-exception'],
        ];

        // Check for existing home code for this user
        $homecode = summergame_get_homecode($uid);

        if (isset($homecode->code_id)) {
          $location_data = json_decode($homecode->clue);
          if (isset($location_data->branchcode)) {
            $branches = $this->branches();
            $branch = $branches[$location_data->branchcode];
            $location_message = "Spread the word that it's located at the $branch Library!";
          }
          else {
            $location_message = 'Make sure to display the code next to the street or sidewalk at:<br>' . $location_data->homecode;
          }
          $form['display'] = [
            '#markup' => '<p>Your Lawn or Library Code is:</p>' .
            '<h1>' . $homecode->text . '</h1>' .
            '<p>It has been redeemed ' . $homecode->num_redemptions . ' time' . ($homecode->num_redemptions == 1 ? '' : 's') . '!</p>' .
            "<p>$location_message</p>"
          ];
          $homecode_player = summergame_player_load(['uid' => $homecode->creator_uid]);
          $form['cancel'] = [
            '#type' => 'link',
            '#title' => 'Return to Player Page',
            '#url' => Url::fromRoute('summergame.player', ['pid' => $homecode_player['pid']]),
            '#suffix' => '</div>'
          ];
        }
        else if (\Drupal::config('summergame.settings')->get('summergame_homecode_form_enabled')) {
          $form['#attached']['library'][] = 'summergame/summergame-homecode-form-lib';

          $form['instructions'] = [
            '#markup' => \Drupal::config('summergame.settings')->get('summergame_homecode_message'),
          ];
          $form['uid'] = [
            '#type' => 'value',
            '#value' => $uid,
          ];
          $form['type'] = [
            '#type' => 'select',
            '#title' => 'Please select which type of sign you have to begin',
            '#options' => [
              '' => '- Select Type -',
              'lawn' => 'I have a Lawn Code sign',
              'library' => 'I have a Library Code card',
            ],
            '#required' => TRUE,
            '#attributes' => ["onChange" => "checkCodeType()"],
          ];
          $form['details'] = [
            '#prefix' => '<div id="homecode-form-details" class="visually-hidden">',
            '#suffix' => '</div>',
          ];
          $form['details']['code'] = [
            '#prefix' => '<div id="code-elements">',
            '#suffix' => '</div>',
          ];
          $form['details']['code']['text'] = [
            '#type' => 'textfield',
            '#title' => t('Lawn or Library Code Text for User') . ' ' . $account->get('name')->value,
            '#default_value' => '',
            '#size' => 20,
            '#maxlength' => 12,
            '#description' => t('Game Code Text for your sign (letters and numbers only, maximum 12 characters)'),
            '#required' => TRUE,
          ];
          $form['details']['code']['message'] = [
            '#type' => 'textfield',
            '#title' => t('Code Message'),
            '#default_value' => '',
            '#size' => 32,
            '#maxlength' => 64,
            '#description' => t('A short message to display to players who redeem your Game Code (optional)'),
          ];
          $form['details']['code']['message_guidelines'] = [
            '#markup' => '<strong><p>Please avoid messages that are commercial, religious, or political. Thank you!</p></strong>'
          ];
          $form['details']['library'] = [
            '#prefix' => '<div id="library-elements">',
            '#suffix' => '</div>',
          ];
          $form['details']['library']['branch'] = [
            '#type' => 'select',
            '#title' => t('Library Branch'),
            '#options' => array_merge(['' => '- Select Branch -'], $this->branches()),
            '#description' => t('The library branch where you are posting your library code sign'),
            '#attributes' => ["onChange" => "showActions()"],
          ];
          $form['details']['lawn'] = [
            '#prefix' => '<div id="lawn-elements">',
            '#suffix' => '</div>',
          ];
          $form['details']['lawn']['street'] = [
            '#type' => 'textfield',
            '#title' => t('Street Address'),
            '#default_value' => '',
            '#size' => 64,
            '#maxlength' => 128,
            '#description' => t('Approximate Street Address where the Game Code sign will be displayed (example "343 S. Fifth Ave")'),
          ];
          $form['details']['lawn']['zip'] = [
            '#type' => 'number',
            '#title' => t('Zip Code'),
            '#min' => 10000,
            '#max' => 99999,
            '#size' => 5,
            '#description' => t('5 digit Zip Code where the Game Code sign will be displayed (example "48103")'),
          ];
          $form['details']['lawn']['lookup_address'] = [
            '#type' => 'button',
            '#value' => $this->t('Map it!'),
            '#attributes' => [
              'onclick' => 'return false;'
            ],
          ];
          $form['details']['lawn']['map'] = [
            '#markup' => '<div id="map-error" class="visually-hidden"></div>' .
                         '<div id="map-wrapper" class="visually-hidden">' .
                         '<p>*Click on map to adjust sign position (optional)</p>' .
                         '<div id="mapid"></div>' .
                         '</div>',
          ];
          $form['details']['lawn']['formatted'] = [
            '#type' => 'hidden',
            '#default_value' => '',
          ];
          $form['details']['lawn']['route'] = [
            '#type' => 'hidden',
            '#default_value' => '',
          ];
          $form['details']['lawn']['lat'] = [
            '#type' => 'hidden',
            '#default_value' => '',
          ];
          $form['details']['lawn']['lon'] = [
            '#type' => 'hidden',
            '#default_value' => '',
          ];
          $form['details']['lawn']['display'] = [
            '#type' => 'checkbox',
            '#title' => 'Display my Address on Public Lawn Codes Map',
            '#description' => t("This means summer game players will come to your home looking for this code! Make sure it's in YOUR lawn or you have permission!"),
          ];
          $form['details']['lawn']['guidelines'] = [
            '#markup' => '<strong><p>Make sure your lawn sign is next to the sidewalk, street, or parking lot!</p></strong>'
          ];
          $form['details']['actions'] = [
            '#prefix' => '<div id="homecode-form-actions" class="visually-hidden">',
            '#suffix' => '</div>',
          ];
          $form['details']['actions']['permission'] = [
            '#type' => 'checkbox',
            '#title' => 'I am a grownup, or I have permission from one to make this code and put up a code sign. (REQUIRED)',
            '#required' => TRUE,
          ];
          $form['details']['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => t('Create Code'),
          ];
          $form['details']['actions']['cancel'] = [
            '#type' => 'link',
            '#title' => 'Return to Player Page',
            '#url' => Url::fromRoute('summergame.player'),
          ];
        }
        else {
          // No home code, but home code creation is not enabled
          $form['display'] = [
            '#markup' => '<p>Sorry, Lawn Code & Library Code creation is not currently available.</p>' .
                         '<p><a href="/summergame/homecodes">View the current Codes map</a> to see what codes are available.</p>',
          ];
          $form['cancel'] = [
            '#type' => 'link',
            '#title' => 'Return to Player Page',
            '#url' => Url::fromRoute('summergame.player'),
            '#suffix' => '</div>'
          ];
        }

        return $form;
      }
      else {
        \Drupal::messenger()->addWarning("Unable to load user from User ID");
        return new RedirectResponse('/summergame/player');
      }
    }
    else {
      \Drupal::messenger()->addWarning("You do not have access to User ID");
      return new RedirectResponse('/summergame/player');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $db = \Drupal::database();

    // Remove non-alphanumerics from Game Code text
    $text = preg_replace('/[^A-Za-z0-9]/', '', $form_state->getValue('text'));

    // Check whether new game code is unique
    $game_term = \Drupal::config('summergame.settings')->get('summergame_current_game_term');
    $code = $db->query("SELECT code_id FROM sg_game_codes WHERE text LIKE :text AND game_term LIKE :game_term",
                       [':text' => $text, ':game_term' => $game_term])->fetchObject();
    if (!empty($code->code_id)) {
      $form_state->setErrorByName('text', 'Code text is already in use. Please select another code.');
    }
    $form_state->setValue('text', $text);

    if ($form_state->getValue('type') == 'lawn') {
      // Check geocode of address
      if (empty($form_state->getValue('formatted'))) {
        $form_state->setErrorByName('street', 'Unable to locate street address. Please try again.');
      }
    }
    else if ($form_state->getValue('type') == 'library') {
      if ($form_state->getValue('branch') == '') {
        $form_state->setErrorByName('branch', 'Please select the branch where this code will be posted.');
      }
    }
    else {
      $form_state->setErrorByName('type', 'Invalid code type selected, please select type of code you wish to create');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $db = \Drupal::database();
    $messenger = \Drupal::messenger();
    $summergame_settings = \Drupal::config('summergame.settings');

    if ($form_state->getValue('type') == 'lawn') {
      // Format code description
      $description = "You found a Lawn Code on " . $form_state->getValue('route') . '.';
      if ($message = $form_state->getValue('message')) {
        $description .= ' ' . trim($message);
      }

      $clue = [
        'homecode' => $form_state->getValue('formatted'),
        'lat' => $form_state->getValue('lat'),
        'lon' => $form_state->getValue('lon'),
        'display' => $form_state->getValue('display'),
      ];
    }
    else {
      // branch code
      $branch = $form_state->getValue('branch');
      $branches = $this->branches();
      $description = "You found a Library Code at the " . $branches[$branch] . ' Library.';
      if ($message = $form_state->getValue('message')) {
        $description .= ' ' . trim($message);
      }
      $clue = [
        'branchcode' => $branch,
      ];
    }

    // Set up fields
    $fields = [
      'creator_uid' => $form_state->getValue('uid'),
      'created' => time(),
      'text' => strtoupper(str_replace(["\r", "\n", ' '], '', $form_state->getValue('text'))),
      'description' => $description,
      'hint' => '',
      'clue' => json_encode($clue),
      'clue_trigger' => '',
      'points' => 100,
      'diminishing' => 0,
      'max_redemptions' => 0,
      'valid_start' => time(),
      'valid_end' => strtotime($summergame_settings->get('summergame_gamecode_default_end')),
      'game_term' => $summergame_settings->get('summergame_current_game_term'),
      'everlasting' => 0,
      'link' => '',
      'search_phrase' => '',
    ];

    $code_id = $db->insert('sg_game_codes')->fields($fields)->execute();
    $messenger->addMessage('Game Code ' . $fields['text'] . ' Created');

    // Send notification email to moderate
    $notify_email = $summergame_settings->get('summergame_homecode_notify_email');
    mail($notify_email,
      'New Home Code: ' . $fields['text'],
      Url::fromRoute('summergame.admin.gamecode', ['code_id' => $code_id], ['absolute' => TRUE])->toString() . "\n\n" .
      $fields['text'] . ' created by User ID #' . $fields['creator_uid'] . "\n" .
      ($message ? 'User message: ' . $message . "\n" : '') .
      "\nAddress Info:\n" . str_replace('<br>', "\n", $clue['homecode']) . $clue['branchcode']
    );

    return;
  }

  private function geocode_lookup($street, $zip) {
    $address = FALSE;
    $guzzle = \Drupal::httpClient();
    $geocode_url =  \Drupal::config('summergame.settings')->get('summergame_homecode_geocode_url');

    $query = [
      'address' =>  $street . ' ' . $zip,
      'key' => \Drupal::config('summergame.settings')->get('summergame_homecode_geocode_api_key'),
    ];
    try {
      $response = $guzzle->request('GET', $geocode_url, ['query' => $query]);
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError('Unable to lookup address');
    }

    if ($response) {
      $response_body = json_decode($response->getBody()->getContents());

      if ($response_body->status == 'OK') {
        // parse address components
        $result = $response_body->results[0];

        // Convert formatted address
        $formatted_address = str_replace(', USA', '', $result->formatted_address);
        $comma_pos = strpos($formatted_address, ', ');
        if ($comma_pos) {
          $formatted_address = substr_replace($formatted_address, "<br>", $comma_pos, 2);
        }
        $last_space_pos = strrpos($formatted_address, ' ');
        if ($last_space_pos) {
          $formatted_address = substr_replace($formatted_address, "<br>", $last_space_pos, 1);
        }

        $address = [
          'formatted' => $formatted_address,
          'lat' => $result->geometry->location->lat,
          'lon' => $result->geometry->location->lng,
        ];
        foreach ($result->address_components as $address_component) {
          $type = $address_component->types[0];
          $address[$type] = $address_component->short_name;
        }

        if (empty($address['street_number'])) {
          // Require a street number
          $address = FALSE;
        }
      }
      else {
        \Drupal::messenger()->addError('Error returned from address lookup, try again');
      }
    }
    else {
      \Drupal::messenger()->addError('Empty response on address lookup');
    }

    return $address;
  }
}
