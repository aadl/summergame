<?php

/**
 * @file
 * Contains \Drupal\summergame\Form\SummerGameHomeCodeForm.
 */

namespace Drupal\summergame\Form;

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
    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    if ($user->get('uid')->value == $uid ||
        $user->hasPermission('manage summergame')) {
      $account = \Drupal\user\Entity\User::load($uid);
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
          $form['cancel'] = [
            '#type' => 'link',
            '#title' => 'Return to Player Page',
            '#url' => \Drupal\Core\Url::fromRoute('summergame.player'),
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
              'lawn' => 'I have a Lawn sign',
              'library' => 'I have a Library card',
            ],
            '#required' => TRUE,
            '#attributes' => array("onChange" => "checkCodeType()", "style" => "border: 1px solid")
          ];
          $form['details'] = [
            '#prefix' => '<div id="homecode-form-details" class="visually-hidden">',
            '#suffix' => '</div>',
          ];
          $form['details']['text'] = [
            '#type' => 'textfield',
            '#title' => t('Lawn or Library Code Text for User') . ' ' . $account->get('name')->value,
            '#default_value' => '',
            '#size' => 20,
            '#maxlength' => 12,
            '#description' => t('Game Code Text for your sign (letters and numbers only, maximum 12 characters)'),
            '#required' => TRUE,
          ];
          $form['details']['message'] = [
            '#type' => 'textfield',
            '#title' => t('Code Message'),
            '#default_value' => '',
            '#size' => 32,
            '#maxlength' => 64,
            '#description' => t('A short message to display to players who redeem your Game Code (optional)'),
          ];
          $form['details']['message_guidelines'] = [
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
          $form['details']['lawn']['guidelines'] = [
            '#markup' => '<strong><p>Make sure your lawn sign is next to the sidewalk, street, or parking lot!</p></strong>'
          ];
          $form['details']['permission'] = [
            '#type' => 'checkbox',
            '#title' => 'I am a grownup, or I have permission from one to make this code and put up a code sign. (REQUIRED)',
            '#required' => TRUE,
          ];
          $form['details']['actions'] = [
            '#prefix' => '<div class="sg-form-actions">',
            '#suffix' => '</div>',
          ];
          $form['details']['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => t('Submit Code'),
          ];
          $form['details']['actions']['cancel'] = [
            '#type' => 'link',
            '#title' => 'Return to Player Page',
            '#url' => \Drupal\Core\Url::fromRoute('summergame.player'),
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
            '#url' => \Drupal\Core\Url::fromRoute('summergame.player'),
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
    $summergame_settings = \Drupal::config('summergame.settings');

    // Remove non-alphanumerics from Game Code text
    $text = preg_replace('/[^A-Za-z0-9]/', '', $form_state->getValue('text'));

    // Check whether new game code is unique
    $code = $db->query("SELECT code_id FROM sg_game_codes WHERE text LIKE :text", [':text' => $text])->fetchObject();
    if ($code->code_id) {
      $form_state->setErrorByName('text', 'Code text is already in use. Please select another code.');
    }
    $form_state->setValue('text', $text);

    if ($form_state->getValue('type') == 'lawn') {
      // Check geocode of address
      $street = trim($form_state->getValue('street'));
      $guzzle = \Drupal::httpClient();
      $geocode_search_url = $summergame_settings->get('summergame_homecode_geocode_url');

      $query = [
        'street' => $street,
        'postalcode' => $form_state->getValue('zip'),
        'country' => 'United States of America',
        'addressdetails' => 1,
        'format' => 'json',
      ];
      try {
        $response = $guzzle->request('GET', $geocode_search_url, ['query' => $query]);
      }
      catch (\Exception $e) {
        \Drupal::messenger()->addError('Unable to lookup address position');
      }
      if ($response) {
        $response_body = json_decode($response->getBody()->getContents());
        if (!isset($response_body[0]->address->road)) {
          $form_state->setErrorByName('street', 'Unable to locate street address. Please try again.');
        }
        $form_state->setValue('geocode_data', $response_body[0]);
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
      $geocode_data = $form_state->getValue('geocode_data');
      $description = "You found a Lawn Code on " . $geocode_data->address->road . '.';
      if ($message = $form_state->getValue('message')) {
        $description .= ' ' . trim($message);
      }

      $city = $geocode_data->address->municipality ?? $geocode_data->address->city ?? $geocode_data->address->town ?? $geocode_data->address->village;
      $clue = [
        'homecode' => $geocode_data->address->house_number . ' ' . $geocode_data->address->road . '<br>' .
                      $city . ', ' . $geocode_data->address->state . '<br>' .
                      $geocode_data->address->postcode,
        'lat' => $geocode_data->lat,
        'lon' => $geocode_data->lon,
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
    ];

    $code_id = $db->insert('sg_game_codes')->fields($fields)->execute();
    $messenger->addMessage('Game Code ' . $fields['text'] . ' Created');

    // Send notification email to moderate
    $notify_email = $summergame_settings->get('summergame_homecode_notify_email');
    mail($notify_email,
      'New Home Code: ' . $fields['text'],
      \Drupal\Core\Url::fromRoute('summergame.admin.gamecode', ['code_id' => $code_id], ['absolute' => TRUE])->toString() . "\n\n" .
      $fields['text'] . ' created by User ID #' . $fields['creator_uid'] . "\n" .
      ($message ? 'User message: ' . $message . "\n" : '') .
      "\nAddress Info:\n" . str_replace('<br>', "\n", $clue['homecode']) . $clue['branchcode']
    );

    return;
  }

}
