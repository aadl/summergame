<?php

/**
 * @file
 * Contains \Drupal\summergame\Form\SummerGamePlayerForm.
 */

namespace Drupal\summergame\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SummerGamePlayerForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'summergame_player_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $pid = 0) {
    $db = \Drupal::database();
    $player = summergame_player_load($pid);
    $summergame_settings = \Drupal::config('summergame.settings');
    $user = \Drupal::currentUser();

    $form = [];
    if ($player['pid']) {
      $form['pid'] = [
        '#type' => 'value',
        '#value' => $player['pid'],
      ];
      if (!$player['gamecard']) {
        $game_term = $summergame_settings->get('summergame_current_game_term');
        $signup = $db->query("SELECT * FROM sg_ledger WHERE pid = " . $player['pid'] .
                             " AND type = 'Signup' AND game_term = '$game_term'")->fetchObject();
        if (!$signup->lid) {
          $form['signup_eligible'] = [
            '#type' => 'value',
            '#value' => TRUE,
          ];
        }
      }
      $form['title'] = [
        '#value' => '<p style="float: right">' .
                    '<a href="/summergame/player/' . $player['pid'] . '">Back to Player Score Card page</a>' .
                    '</p>' .
                    '<h1>Edit Summer Game Player Information</h1>',
      ];
      $submit_text = 'Save Player Changes';
    }
    else {
      $form['title'] = [
        '#value' => '<h1>Summer Game Player Signup</h1>',
      ];
      $submit_text = 'Sign Up!';
    }

    $form['buttons'] = [
      '#prefix' => '<div class="summergame-player-edit-buttons">',
      '#suffix' => '</div>',
    ];
    if ($player['pid']) {
      $form['buttons']['delete'] = [
        '#value' => '<div class="summergame-player-delete-link">' .
                    '<a href="/summergame/player/delete/' . $player['pid'] . '">Delete Player</a>' .
                    '</div>',
      ];
    }
    $form['buttons']['submit'] = [
      '#type' => 'submit',
      '#value' => t($submit_text),
      '#attributes' => ['onClick' => 'resetDirty()'],
    ];

    if ($player['pid']) {
      $cancel_path = 'summergame/player/' . $player['pid'];
    }
    else if (strpos($_GET['q'], 'admin') !== FALSE) {
      $cancel_path = 'summergame/admin';
    }
    else {
      $cancel_path = '';
    }
    $form['buttons']['cancel'] = [
      '#value' => '<a href="/' . $cancel_path . '">Cancel</a>',
    ];

    if ($player['pid'] && $user->hasPermission('administer summergame')) {
      $form['admin'] = [
        '#prefix' => '<fieldset style="float:right"><legend>STAFF ONLY</legend>',
        '#suffix' => '</fieldset>',
      ];

      $form['admin']['merge_id'] = [
        '#type' => 'textfield',
        '#title' => t('Merge this player into Player ID'),
        '#size' => 8,
        '#maxlength' => 8,
        '#description' => t("Enter another Player ID number to merge this player infomation into that player record"),
        '#prefix' => "<fieldset class=\"collapsible collapsed\"><legend>MERGE PLAYER</legend>",
        '#suffix' => "</fieldset>",
      ];
    }

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => t('Name'),
      '#default_value' => $player['name'],
      '#size' => 30,
      '#maxlength' => 64,
      '#description' => t('Enter your real name'),
      '#required' => TRUE,
    ];
    $form['nickname'] = [
      '#type' => 'textfield',
      '#title' => t('Nickname'),
      '#default_value' => $player['nickname'],
      '#size' => 30,
      '#maxlength' => 64,
      '#description' => t('Enter an optional nickname (will be used for public display if allowed)'),
    ];
    if ($user->hasPermission('administer summergame')) {
      $form['phone'] = [
        '#type' => 'textfield',
        '#title' => t('Phone Number'),
        '#default_value' => $player['phone'],
        '#size' => 30,
        '#maxlength' => 64,
        '#description' => t('Enter a phone number to play by text message (rates may apply)'),
      ];
    }
    else if ($player['phone']) {
      $form['phone'] = [
        '#type' => 'value',
        '#value' => $player['phone'],
      ];
    }
    /*
    $form['gamecard'] = [
      '#type' => 'textfield',
      '#title' => t('Summer Game Card Number'),
      '#default_value' => $player['gamecard'],
      '#size' => 16,
      '#maxlength' => 16,
      '#description' => t('Did you pick up a score card? Enter its number here to help us track your progress'),
    );
    // Enter a referral code if eligible
    if (summergame_referral_available($player)) {
      $form['referred_by'] = [
        '#type' => 'textfield',
        '#title' => t('Referred By'),
        '#size' => 16,
        '#maxlength' => 16,
        '#description' => t('Were you referred by a friend? Enter their referral code here to earn a point bonus for both of you!'),
      );
    }
    */
    // Set up privacy default values
    $privacy_defaults = [];
    if ($player['show_leaderboard']) {
      $privacy_defaults[] = 'show_leaderboard';
    }
    if ($player['show_myscore']) {
      $privacy_defaults[] = 'show_myscore';
    }
    if ($player['show_titles']) {
      $privacy_defaults[] = 'show_titles';
    }
    $form['privacy'] = [
      '#type' => 'checkboxes',
      '#title' => 'Privacy Options',
      '#options' => [
        'show_leaderboard' => 'Show my nickname and total score on the public leaderboard',
        'show_myscore' => 'Allow others to see my summer game scores and awards page',
        'show_titles' => 'Display the titles of Books/Movies/Music on my Score Card for others to see',
      ],
      '#default_value' => $privacy_defaults,
      '#description' => 'Select what other people can see about your Summer Game progress',
    ];
    $form['agegroup'] = [
      '#type' => 'select',
      '#title' => t('Age Group'),
      '#default_value' => $player['pid'] ? $player['agegroup'] : 'adult',
      '#options' => [
        'youth' => 'Youth',
        'teen' => 'Teen',
        'adult' => 'Adult'
      ],
      '#description' => 'Select your age group',
      '#attributes' => ['onChange' => 'checkForSchool(this)'],
    ];
    if ($player['agegroup'] == 'youth' || $player['agegroup'] == 'teen') {
      $school_style = 'display: block';
    }
    else {
      $school_style = 'display: none';
    }

    $form['school_info'] = [
      '#prefix' => '<div id="school-details-div" style="' . $school_style . '">',
      '#suffix' => '</div>',
    ];
    $school_autocomplete = [];
    $res = $db->query('SELECT name FROM sg_schools ORDER BY name ASC');
    while ($school = $res->fetchObject()) {
      $school_autocomplete[] = $school->name;
    }
    $form['school_info']['school'] = [
      '#type' => 'autocomplete',
      '#data' => $school_autocomplete,
      '#title' => t('School'),
      '#default_value' => $player['school'],
      '#size' => 30,
      '#maxlength' => 64,
      '#description' => t('Are you a student? Please enter the name of your school to help us know'),
    ];
    $form['school_info']['grade'] = [
      '#type' => 'select',
      '#title' => t('Grade'),
      '#default_value' => $player['grade'],
      '#options' => [
        '' => 'N/A',
        -1 => 'Preschool',
        0  => 'Kindergarten',
        1  => '1st Grade',
        2  => '2nd Grade',
        3  => '3rd Grade',
        4  => '4th Grade',
        5  => '5th Grade',
        6  => '6th Grade',
        7  => '7th Grade',
        8  => '8th Grade',
        9  => '9th Grade',
        10 => '10th Grade',
        11 => '11th Grade',
        12 => '12th Grade',
      ],
      '#description' => t('Please let us know your upcoming grade if you\'re a student'),
    ];

    if ($user->hasPermission('administer users')) {
      if ($summergame_user_search_path = $summergame_settings->get('summergame_user_search_path')) {
        $search_link = ' (<a href="/' . $summergame_user_search_path . '">Search for user accounts if needed</a>)';
      }
      $form['uid'] = [
        '#type' => 'textfield',
        '#title' => t('User ID'),
        '#default_value' => $player['uid'],
        '#size' => 8,
        '#maxlength' => 8,
        '#description' => t("Website User ID to connect this player") . $search_link,
      ];
    }
    else if ($player['uid']) {
      $form['uid'] = [
        '#type' => 'value',
        '#value' => $player['uid'],
      ];
    }

    $form['buttons2'] = $form['buttons'];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    if ($trigger['#id'] == 'edit-add-barcode') {
      $barcode = preg_replace('/[^0-9]/', '', $form_state->getValue('barcode'));
      // Make sure barcode is correct format
      if (!preg_match('/21621[0-9]{9}/', $barcode)) {
        $form_state->setErrorByName('barcode', $this->t('Invalid format. Barcodes are 14 digits long and start with "21621"'));
      }
      else {
        // Make sure barcode isn't already attached to Account
        if (in_array($barcode, $form_state->getValue('barcodes'))) {
          $form_state->setErrorByName('barcode', $this->t('Barcode already attached to account'));
        }
        else {
          // Make sure barcode exists in Evergreen
          $api_url = \Drupal::config('arborcat.settings')->get('api_url');
          $guzzle = \Drupal::httpClient();

          $query = [
            'barcode' => $barcode,
          ];
          if ($form_state->getValue('name')) {
            $query['name'] = $form_state->getValue('name');
          }
          else if ($form_state->getValue('street')) {
            $query['street'] = $form_state->getValue('street');
          }
          else if ($form_state->getValue('phone')) {
            $query['phone'] = $form_state->getValue('phone');
          }

          $response = $guzzle->request('GET', "$api_url/patron/validate_barcode", ['query' => $query]);
          $response_body = json_decode($response->getBody()->getContents());

          if ($response_body->status == 'ERROR') {
            $form_state->setErrorByName($response_body->error, $response_body->message);
          }
          else {
            $form_state->setValue('barcode', $barcode);
            $form_state->setValue('patron_id', $response_body->patron_id);
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Set Barcode and Patron ID fields, generate API Key
    $account = $form_state->getValue('account');
    $account->field_barcode[] = $form_state->getValue('barcode');
    $account->field_patron_id[] = $form_state->getValue('patron_id');
    $account->field_api_key[] = arborcat_generate_api_key();
    $account->save();

    drupal_set_message('Successfully added library card barcode to your website account');

    $form_state->setRedirect('entity.user.canonical', ['user' => $account->get('uid')->value]);

    return;
  }

  public function removeBarcodeSubmit(array &$form, FormStateInterface $form_state) {
    $te = $form_state->getTriggeringElement();
    $delta = str_replace('edit-remove-barcode-', '', $te['#id']);

    $account = $form_state->getValue('account');
    unset($account->field_barcode[$delta]);
    unset($account->field_patron_id[$delta]);
    unset($account->field_api_key[$delta]);
    $account->save();

    drupal_set_message('Successfully removed barcode from your website account');

    $form_state->setRedirect('entity.user.canonical', ['user' => $account->get('uid')->value]);

    return;
  }

  public function primaryBarcodeSubmit(array &$form, FormStateInterface $form_state) {
    $te = $form_state->getTriggeringElement();
    $top_delta = str_replace('edit-make-primary-barcode-', '', $te['#id']);

    $account = $form_state->getValue('account');

    // Reorder Barcodes
    $field_barcodes = $account->get('field_barcode')->getValue();
    $new_field_barcodes = [$field_barcodes[$top_delta]['value']];
    foreach ($field_barcodes as $delta => $field_barcode) {
      if ($delta != $top_delta) {
        $new_field_barcodes[] = $field_barcode['value'];
      }
    }
    unset($account->field_barcode);
    foreach ($new_field_barcodes as $new_field_barcode) {
      $account->field_barcode[] = $new_field_barcode;
    }

    // Reorder Patron IDs
    $field_patron_ids = $account->get('field_patron_id')->getValue();
    $new_field_patron_ids = [$field_patron_ids[$top_delta]['value']];
    foreach ($field_patron_ids as $delta => $field_patron_id) {
      if ($delta != $top_delta) {
        $new_field_patron_ids[] = $field_patron_id['value'];
      }
    }
    unset($account->field_patron_id);
    foreach ($new_field_patron_ids as $new_field_patron_id) {
      $account->field_patron_id[] = $new_field_patron_id;
    }

    // Reorder API Keys
    $field_api_keys = $account->get('field_api_key')->getValue();
    $new_field_api_keys = [$field_api_keys[$top_delta]['value']];
    foreach ($field_api_keys as $delta => $field_api_key) {
      if ($delta != $top_delta) {
        $new_field_api_keys[] = $field_api_key['value'];
      }
    }
    unset($account->field_api_key);
    foreach ($new_field_api_keys as $new_field_api_key) {
      $account->field_api_key[] = $new_field_api_key;
    }

    $account->save();

    drupal_set_message('Successfully reordered barcodes');

    return;
  }
}
