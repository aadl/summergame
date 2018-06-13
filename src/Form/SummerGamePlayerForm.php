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
    $route = \Drupal::routeMatch()->getRouteName();
    $db = \Drupal::database();
    $summergame_settings = \Drupal::config('summergame.settings');
    $user = \Drupal::currentUser();

    if ($route == 'summergame.player.new') {
      // Check if user logged in
      if ($uid = $user->id()) {
        // Check if existing player
        if ($player = summergame_player_load(['uid' => $uid])) {
          return $this->redirect('summergame.player', ['pid' => $player['pid']])->send();
        }
        else {
          // Website user, no player, set up empty player record
          $player = ['uid' => $uid];
        }
      }
      else {
        drupal_set_message('You need to log in to the website before you can sign up a player');
        return $this->redirect('<front>')->send();
      }
    }
    else {
      $player = summergame_player_load($pid);
    }

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
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Check for merge ID
    if ($form_state->getValue('pid') && $form_state->getValue('merge_id')) {
      $form_state->setRedirect('summergame.admin.players.merge',
                              ['pid1' => $form_state->getValue('merge_id'), 'pid2' => $form_state->getValue('pid')]);
    }
    else {
      $player_info = [
        'name' => $form_state->getValue('name'),
        'nickname' => trim($form_state->getValue('nickname')),
        'gamecard' => str_replace(' ', '', strtoupper($form_state->getValue('gamecard'))),
        'agegroup' => $form_state->getValue('agegroup'),
        'school' => $form_state->getValue('school'),
        'phone' => NULL, // default, handled below
      ];

      // Special field handling
      if ($form_state->getValue('phone')) {
        $phone = preg_replace('/[^\d]/', '', $form_state->getValue('phone'));
        if (strlen($phone) == 7) {
          // preface with local area code
          $phone = '1734' . $phone;
        }
        else if (strlen($phone) == 10) {
          // preface with a 1
          $phone = '1' . $phone;
        }
        $player_info['phone'] = $phone;
      }
      $player_info['grade'] = ($form_state->getValue('grade') == '' ? NULL : $form_state->getValue('grade'));

      foreach ($form_state->getValue('privacy') as $name => $value) {
        $player_info[$name] = ($value ? 1 : 0);
      }

      if ($form_state->getValue('uid')) {
        $player_info['uid'] = $form_state->getValue('uid');
      }

      if ($form_state->getValue('pid')) {
        $player_info['pid'] = $form_state->getValue('pid');

        if ($form_state->getValue('signup_eligible') && $form_state->getValue('gamecard')) {
          $signup_bonus = TRUE;
        }
      }
      else {
        $signup_bonus = TRUE;
      }

      $player = summergame_player_save($player_info);

      if (\Drupal::config('summergame.settings')->get('summergame_points_enabled')) {
        if ($signup_bonus) {
          $points = summergame_player_points($player['pid'], 100, 'Signup',
                                             'Signed Up for the Summer Game');
          drupal_set_message("Earned $points Summer Game points for signing up!");
        }
    /*
        // Check for referral bonus
        if ($form_state->getValue('referred_by']) {
          // Check for referral player
          $referring_player = summergame_player_load(array('friend_code' => $form_state->getValue('referred_by']));
          if ($referring_player['pid']) {
            // Make sure no one has already gotten points for this player
            $existing_bonus = db_fetch_object(db_query("SELECT * FROM sg_ledger WHERE metadata LIKE '%referred:%s%'", $player['pid']));
            if ($existing_bonus->points) {
              drupal_set_message('Sorry, you entered a Referral Code, but your player has already been awarded points for a referral.');
            }
            else {
              summergame_player_points($player['pid'], 500, 'Referral',
                                       'Referred by Player #' . $referring_player['pid'], 'referred_by:' . $referring_player['pid']);
              summergame_player_points($referring_player['pid'], 500, 'Referral',
                                       'Referral Bonus for Player #' . $player['pid'], 'referred:' . $player['pid']);
              drupal_set_message('You were referred by Player #' . $referring_player['pid'] . ' and you each earned a 500 point bonus!');
            }
          }
          else {
            drupal_set_message('Sorry, you entered a referral code, but no player with that code exists');
          }
        }
    */
      }

      $form_state->setRedirect('summergame.player', ['pid' => $player['pid']]);
    }
    return;
  }
}
