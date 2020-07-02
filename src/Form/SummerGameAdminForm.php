<?php

/**
 * @file
 * Contains \Drupal\summergame\Form\SummerGameAdminForm.
 */

namespace Drupal\summergame\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class SummerGameAdminForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'summergame_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('summergame.settings');

    foreach (Element::children($form) as $variable) {
      $config->set($variable, $form_state->getValue($form[$variable]['#parents']));
    }
    $config->save();

    if (method_exists($this, '_submitForm')) {
      $this->_submitForm($form, $form_state);
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['summergame.settings'];
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $summergame_settings = \Drupal::config('summergame.settings');
    $form = [];
    $form['summergame_points_enabled'] = [
      '#type' => 'checkbox',
      '#title' => t('Summergame Points Enabled'),
      '#default_value' => $summergame_settings->get('summergame_points_enabled'),
      '#description' => t('Allow players to earn points?'),
    ];
    $form['summergame_show_gamecodes_in_catalog'] = [
      '#type' => 'checkbox',
      '#title' => t('Show Gamecodes in Catalog'),
      '#default_value' => $summergame_settings->get('summergame_show_gamecodes_in_catalog'),
      '#description' => t('Display Gamecodes in the Catalog?'),
    ];
    $form['summergame_lego_enabled'] = [
      '#type' => 'checkbox',
      '#title' => t('Summergame Lego Contest Voting Enabled'),
      '#default_value' => $summergame_settings->get('summergame_lego_enabled'),
      '#description' => t('Turn on voting for the Lego Contest?'),
    ];
    $form['summergame_dod_enabled'] = [
      '#type' => 'checkbox',
      '#title' => t('Summergame Do Or Diag Enabled'),
      '#default_value' => $summergame_settings->get('summergame_dod_enabled'),
      '#description' => t('Turn on the special Do or Diag game?'),
    ];
    $form['summergame_current_game_term'] = [
      '#type' => 'textfield',
      '#title' => t("Current Default Game Term"),
      '#default_value' => $summergame_settings->get('summergame_current_game_term'),
      '#size' => 32,
      '#maxlength' => 32,
      '#description' => t("Default Game Term to apply to earned points (e.g. SummerGame2011)"),
    ];
    $form['summergame_badgelist_game_term'] = [
      '#type' => 'textfield',
      '#title' => t("Badge List Game Term"),
      '#default_value' => $summergame_settings->get('summergame_badgelist_game_term'),
      '#size' => 32,
      '#maxlength' => 32,
      '#description' => t("Game Term for badges displayed on the Badge List page (/badgelist)"),
    ];
    $form['summergame_gamecode_default_end'] = [
      '#type' => 'textfield',
      '#title' => t("Default Game Code End"),
      '#default_value' => $summergame_settings->get('summergame_gamecode_default_end'),
      '#size' => 32,
      '#maxlength' => 32,
      '#description' => t('Default Date/Time for when a code stops being active (e.g. "2013-08-31 12:00 AM")'),
    ];
    $form['summergame_completion_gamecode'] = [
      '#type' => 'textfield',
      '#title' => t("Completion Game Code"),
      '#default_value' => $summergame_settings->get('summergame_completion_gamecode'),
      '#size' => 32,
      '#maxlength' => 32,
      '#description' => t("Game Code awarded for completion of the Classic Reading Game (e.g. PUPPYLOVE13)"),
    ];
    $form['summergame_game_limits'] = [
      '#type' => 'textarea',
      '#title' => 'Game Limits by Type',
      '#default_value' => $summergame_settings->get('summergame_game_limits'),
      '#description' => 'Set Game Limits for Types of scoring points. JSON array keyed by type and value with game long point limit',
    ];
    $form['summergame_user_search_path'] = [
      '#type' => 'textfield',
      '#title' => t("User Search Path"),
      '#default_value' => $summergame_settings->get('summergame_user_search_path'),
      '#size' => 32,
      '#maxlength' => 32,
      '#description' => t("Path to your user search form"),
    ];
    $form['summergame_print_page'] = [
      '#type' => 'textfield',
      '#title' => t("Print Page"),
      '#default_value' => $summergame_settings->get('summergame_print_page'),
      '#size' => 64,
      '#maxlength' => 128,
      '#description' => t("Path to the page to print summergame game cards"),
    ];
    $form['summergame_catalog_domain'] = [
      '#type' => 'textfield',
      '#title' => t("Catalog Domain"),
      '#default_value' => $summergame_settings->get('summergame_catalog_domain'),
      '#size' => 32,
      '#maxlength' => 32,
      '#description' => t("Links to catalog pages will be directed to this domain. Leave blank for same domain. (No http:// or trailing slash)"),
    ];
    $form['summergame_redis_conn'] = [
      '#type' => 'textfield',
      '#title' => t('Redis Connection String'),
      '#default_value' => \Drupal::config('summergame.settings')->get('summergame_redis_conn'),
      '#size' => 64,
      '#maxlength' => 128,
      '#description' => t('Connection information for the redis server (e.g. tcp://127.0.0.1:6379?database=15)'),
    ];
    $user_roles = ['' => 'NONE'];
    foreach (user_roles(TRUE) as $user_role) {
      $role_id = $user_role->get('id');
      $role_label = $user_role->get('label');
      $user_roles[$role_id] = $role_label;
    }
    $form['summergame_staff_role_id'] = [
      '#type' => 'select',
      '#title' => 'Staff Role',
      '#options' => $user_roles,
      '#default_value' => $summergame_settings->get('summergame_staff_role_id'),
      '#description' => t('Select the role that separates staff from regular players on the leaderboard'),
    ];
    $form['summergame_play_test_term_id'] = [
      '#type' => 'textfield',
      '#title' => t("Play Tester Term ID"),
      '#default_value' => $summergame_settings->get('summergame_play_test_term_id'),
      '#size' => 32,
      '#maxlength' => 32,
      '#description' => t("Term ID of Badges to restrict display on Badge List to play testers"),
    ];
    $form['summergame_couch_dsn'] = [
      '#type' => 'textfield',
      '#title' => t("Couch DSN"),
      '#default_value' => $summergame_settings->get('summergame_couch_dsn'),
      '#size' => 64,
      '#maxlength' => 128,
      '#description' => t("DSN of the Couch database server to place Gamecodes in Bib Records"),
    ];
    $form['summergame_couch_db'] = [
      '#type' => 'textfield',
      '#title' => t("Couch DB"),
      '#default_value' => $summergame_settings->get('summergame_couch_db'),
      '#size' => 32,
      '#maxlength' => 32,
      '#description' => t("Name of the Couch Database to place Gamecodes in Bib Records"),
    ];
    $form['summergame_shop_message_threshold'] = [
      '#type' => 'textfield',
      '#title' => 'Shop Message Threshold',
      '#default_value' => $summergame_settings->get('summergame_shop_message_threshold'),
      '#size' => 32,
      '#maxlength' => 32,
      '#description' => t("Current Game Term Points required to display the special shop message"),
    ];
    $form['summergame_shop_message'] = [
      '#type' => 'textarea',
      '#title' => 'Shop Message',
      '#default_value' => $summergame_settings->get('summergame_shop_message'),
      '#description' => 'Message to be displayed under the player shop balance when current points is greater than current point threshold',
    ];
    $form['game_display_name'] = [
        '#type' => 'textfield',
        '#title' => 'Game Display Name',
        '#default_value' => $summergame_settings->get('game_display_name'),
        '#description' => 'The name to be displayed throughout the SummerGame UI',
    ];
    $form['summergame_homecode_geocode_url'] = [
        '#type' => 'textfield',
        '#title' => 'Home Code Geocode URL',
        '#default_value' => $summergame_settings->get('summergame_homecode_geocode_url'),
        '#description' => 'URL Address of Geocoding Service for Home Code address lookup (e.g. https://nominatim.openstreetmap.org/search)',
    ];
    $form['summergame_homecode_notify_email'] = [
        '#type' => 'textfield',
        '#title' => 'Home Code Notify Email',
        '#default_value' => $summergame_settings->get('summergame_homecode_notify_email'),
        '#description' => 'Email address to send notifications of new home codes',
    ];

    return parent::buildForm($form, $form_state);
  }

}
