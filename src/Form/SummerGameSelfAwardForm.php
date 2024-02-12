<?php

/**
 * @file
 * Contains \Drupal\summergame\Form\SummerGameSelfAwardForm.
 */

namespace Drupal\summergame\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SummerGameSelfAwardForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'summergame_self_award_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $pid = 0, $bid = 0) {
    $db = \Drupal::database();
    $player = summergame_player_load($pid);
    $badge = \Drupal::entityTypeManager()->getStorage('node')->load($bid);
    $tasks = explode('|', substr($badge->field_badge_formula->value, strlen('SELFAWARD:')));

/*
    // Multiple player setup
    $all_players = summergame_player_load_all($player['uid']);
    if (count($all_players) > 1) {
      $pid_options = [];
      if (isset($_SESSION['summergame_pid_defaults'])) {
        $pid_defaults = json_decode($_SESSION['summergame_pid_defaults']);
      }
      else {
        $pid_defaults = [$player['pid']];
      }
      foreach ($all_players as $user_player) {
        $pid_options[$user_player['pid']] = ($user_player['nickname'] ? $user_player['nickname'] : $user_player['name']);
      }
      $form['pids'] = [
        '#type' => 'checkboxes',
        '#options' => $pid_options,
        '#default_value' => $pid_defaults,
        '#title' => 'Redeem for players'
      ];
    }
*/

    $form = [
      '#attributes' => ['class' => 'form-width-exception']
    ];
    $form['pid'] = [
      '#type' => 'value',
      '#value' => $player['pid'],
    ];
    $form['bid'] = [
      '#type' => 'value',
      '#value' => $bid,
    ];
    $form['game_term'] = [
      '#type' => 'value',
      '#value' => $badge->field_badge_game_term->value,
    ];

    $playername = ($player['nickname'] ? $player['nickname'] : $player['name']);
    $form['tasks']['tasks_table_start']['#markup'] = "<table><tr><th>Task progress for $playername</th><th>Completed</th></tr>";

    foreach ($tasks as $i => $task) {
      $row_prefix = "<tr><td>$task</td><td>";
      $row_suffix = '</td></tr>';

      // Check if player has completed task
      $completed = $db->query("SELECT COUNT(lid) AS completed FROM `sg_ledger` WHERE `pid` = $pid AND metadata LIKE '%badgetask:$bid,$i%'")->fetchField();
      if ($completed) {
        $form['tasks']['completed-' . $i] = [
          '#type' => 'item',
          '#prefix' => $row_prefix,
          '#markup' => 'COMPLETED: Task ' . $i + 1,
          '#suffix' => $row_suffix,
        ];
      }
      else {
        $form['tasks']['submit-' . $i] = [
          '#type' => 'submit',
          '#prefix' => $row_prefix,
          '#value' => t('Task ' . $i + 1 . ' Completed'),
          '#suffix' => $row_suffix,
        ];
      }
    }
    $form['tasks_table_end']['#markup'] = '</table>';

    $form['cancel'] = [
      '#type' => 'link',
      '#title' => 'Return to Player Page',
      '#url' => Url::fromRoute('summergame.player'),
    ];

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
    $pid = $form_state->getValue('pid');
    $bid = $form_state->getValue('bid');
    $game_term = $form_state->getValue('game_term');
    $te = $form_state->getTriggeringElement();
    $task_id = str_replace('edit-submit-', '', $te['#id']);
    $task_description = $te['#value'];

    summergame_player_points($pid, 0, 'Badge Task', $task_description, "badgetask:$bid,$task_id", $game_term);
    \Drupal::messenger()->addMessage('Completed Task ' . $task_id + 1);

    return;
  }
}
