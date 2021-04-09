<?php

/**
 * @file
 * Contains \Drupal\accountfix\Form\SummerGameInterventionForm
 */

namespace Drupal\summergame\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SummerGameInterventionForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'summergame_intervention_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $form['to_pid'] = [
      '#type' => 'textfield',
      '#title' => t('Player ID'),
      '#size' => 10,
      '#maxlength' => 10,
      '#description' => t('Player ID of the player to receive points'),
    ];
    $form['points'] = [
      '#type' => 'textfield',
      '#title' => t('Points'),
      '#size' => 10,
      '#maxlength' => 10,
      '#description' => t('Number of points to award to the player'),
    ];
    $form['game_term'] = [
      '#type' => 'textfield',
      '#title' => t('Game Term'),
      '#default_value' => \Drupal::config('summergame.settings')->get('summergame_current_game_term'),
      '#size' => 32,
      '#maxlength' => 32,
      '#description' => t('Game Term where the points will be awarded'),
    ];
    $form['description'] = [
      '#type' => 'textfield',
      '#title' => t('Description'),
      '#size' => 64,
      '#maxlength' => 64,
      '#description' => t('Add a description for this points award (optional)'),
    ];
    $form['from_pid'] = [
      '#type' => 'textfield',
      '#title' => t('From Player ID'),
      '#size' => 10,
      '#maxlength' => 10,
      '#description' => t('Player ID of the player giving points in a transfer (optional)'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('POINTS MAKE'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $to_pid = $form_state->getValue('to_pid');
    $points = $form_state->getValue('points');
    $game_term = $form_state->getValue('game_term');
    $description = $form_state->getValue('description');
    $from_pid = $form_state->getValue('from_pid');

    if ($player = summergame_player_load(['pid' => $to_pid])) {
      $player_link = '<a href="/summergame/player/' . $to_pid . '">Player #' . $to_pid . '</a>';
      if ($from_pid) {
        if ($from_player = summergame_player_load(['pid' => $from_pid])) {
          // Tranfer points from from_player to player
          $from_player_link = '<a href="/summergame/player/' . $from_pid . '">Player #' . $from_pid . '</a>';
          summergame_player_points($from_pid, -$points, 'Geekly Intervention',
                                   "Transfer to Player #$to_pid" . ($description ? ', ' . $description : ''),
                                   'delete:no', $game_term);
          summergame_player_points($to_pid, $points, 'Geekly Intervention',
                                   "Transfer from Player #$from_pid" . ($description ? ', ' . $description : ''),
                                   '', $game_term);
          \Drupal::messenger()->addMessage(['#markup' => "Transferred $points $game_term points from $from_player_link to $player_link"]);
        }
        else {
          \Drupal::messenger()->addError("No player with ID #$from_pid could be found");
        }
      }
      else {
        // Award points to the player
        summergame_player_points($to_pid, $points, 'Geekly Intervention',
                                 "Points awarded" . ($description ? ', ' . $description : ''),
                                 '', $game_term);
        \Drupal::messenger()->addMessage(['#markup' => "Awarded $points $game_term points to $player_link"]);
      }
    }
    else {
      \Drupal::messenger()->addError("No player with ID #$to_pid could be found");
    }

    $form_state->setRedirect('summergame.admin');
    return;
  }
}
