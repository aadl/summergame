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

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $uid = 0) {
    $uid = (int) $uid;
    if ($uid) {
      $db = \Drupal::database();
      $form = [
        '#attributes' => ['class' => 'form-width-exception'],
      ];

      // Check for existing home code for this user
      $row = $db->query("SELECT * FROM sg_game_codes WHERE creator_uid = $uid AND clue LIKE '%\"homecode\"%'")->fetchObject();
      if (isset($row->code_id)) {
        $geocode_data = json_decode($row->clue);
        $form['display'] = [
          '#markup' => '<p>Your Home Code is:</p>' .
          '<h1>' . $row->text . '</h1>' .
          '<p><a href="/summergame/pdf/gamecode/' . $row->code_id . '">Download a sign</a> or Make Your Own!</p>' .
          '<p>Make sure to display the code where it is visible at:<br>' . $geocode_data->homecode . '</p>'
        ];
        $form['cancel'] = [
          '#type' => 'link',
          '#title' => 'Return to Player Page',
          '#url' => \Drupal\Core\Url::fromRoute('summergame.player'),
          '#suffix' => '</div>'
        ];
      }
      else {
        $form['uid'] = [
          '#type' => 'value',
          '#value' => $uid,
        ];
        $form['text'] = [
          '#type' => 'textfield',
          '#title' => t('Home Code Text'),
          '#default_value' => '',
          '#size' => 20,
          '#maxlength' => 12,
          '#description' => t('Game Code Text for your address (letters and numbers only, maximum 12 characters)'),
          '#required' => TRUE,
        ];
        $form['street'] = [
          '#type' => 'textfield',
          '#title' => t('Street Address'),
          '#default_value' => '',
          '#size' => 64,
          '#maxlength' => 128,
          '#description' => t('Street Address where the Game Code will be displayed (example "343 S. Fifth Ave")'),
          '#required' => TRUE,
        ];
        $form['zip'] = [
          '#type' => 'number',
          '#title' => t('Zip Code'),
          '#min' => 10000,
          '#max' => 99999,
          '#size' => 5,
          '#description' => t('5 digit Zip Code where the Game Code will be displayed (example "48103")'),
          '#required' => TRUE,
        ];
        $form['submit'] = [
          '#type' => 'submit',
          '#value' => t('Submit Code'),
          '#prefix' => '<div class="sg-form-actions">'
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
      drupal_set_message("Unable to load user from User ID", 'warning');
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
    $code = $db->query("SELECT code_id FROM sg_game_codes WHERE text LIKE :text", [':text' => $text])->fetchObject();
    if ($code->code_id) {
      $form_state->setErrorByName('text', 'Code text is already in use. Please select another code.');
    }
    $form_state->setValue('text', $text);

    // Check geocode of address
    $street = trim($form_state->getValue('street'));
    $guzzle = \Drupal::httpClient();
$geocode_search_url = "https://nominatim.openstreetmap.org/search";
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
      drupal_set_message('Unable to validate barcode for patron', 'error');
    }
    if ($response) {
      $response_body = json_decode($response->getBody()->getContents());
      if (!isset($response_body[0]->address->road)) {
        $form_state->setErrorByName('street', 'Unable to locate street address. Please try again.');
      }
      $form_state->setValue('geocode_data', $response_body[0]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $db = \Drupal::database();
    $messenger = \Drupal::messenger();
    $summergame_settings = \Drupal::config('summergame.settings');

    // Format code description
    $geocode_data = $form_state->getValue('geocode_data');
    $description = "You found a Home Code on " . $geocode_data->address->road;

    $city = $geocode_data->address->municipality ?? $geocode_data->address->city ?? $geocode_data->address->town ?? $geocode_data->address->village;
    $clue = [
      'homecode' => $geocode_data->address->house_number . ' ' . $geocode_data->address->road . '<br>' .
                    $city . ', ' . $geocode_data->address->state . '<br>' .
                    $geocode_data->address->postcode,
      'lat' => $geocode_data->lat,
      'lon' => $geocode_data->lon,
    ];

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
      $fields['text'] . ' created by User ID #' . $fields['creator_uid'] . "\n\nAddress Info:\n" . str_replace('<br>', "\n", $clue['homecode'])
    );

    return;
  }

}
