<?php

/**
 * @file
 * Contains \Drupal\accountfix\Form\SummerGamePlayerQuickTransferForm
 */

namespace Drupal\summergame\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SummerGamePlayerQuickTransferForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'summergame_player_quick_transfer_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $player = [], $other_players = []) {
    $form['quick'] = [
      '#type' => 'details',
      '#title' => 'QUICK POINTS TRANSFER',
    ];
    $form['quick']['from_pid'] = [
      '#type' => 'value',
      '#value' => $player['pid'],
    ];
    $other_player_options = [];
    foreach ($other_players as $other_player) {
      $other_player_options[$other_player['pid']] = ($other_player['nickname'] ? $other_player['nickname'] : $other_player['name']);
    }
    $form['quick']['to_pid'] = [
      '#type' => 'select',
      '#title' => t('To Player'),
      '#options' => $other_player_options,
      '#description' => t('Select player to receive points'),
    ];
    $form['quick']['points'] = [
      '#type' => 'textfield',
      '#title' => t('Points'),
      '#size' => 10,
      '#maxlength' => 10,
      '#description' => t('Number of points to award to the player'),
    ];
    $form['quick']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Transfer Points'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $from_pid = $form_state->getValue('from_pid');
    $to_pid = $form_state->getValue('to_pid');
    $points = $form_state->getValue('points');
    $game_term = \Drupal::config('summergame.settings')->get('summergame_current_game_term');
    $metadata = 'access:player leaderboard:no'; // restrict ledger display to those with player access

    summergame_player_points($from_pid, -$points, 'Staff Adjustment',
                             "Transfer to Player #$to_pid" . ($description ? ', ' . $description : ''),
                             $metadata . ' delete:no', $game_term);
    summergame_player_points($to_pid, $points, 'Staff Adjustment',
                             "Transfer from Player #$from_pid" . ($description ? ', ' . $description : ''),
                             $metadata, $game_term);
    \Drupal::messenger()->addMessage("Transferred $points $game_term points from $from_player_link to $player_link");

    return;
  }
}
