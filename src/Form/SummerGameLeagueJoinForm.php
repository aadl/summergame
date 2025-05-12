<?php

/**
 * @file
 * Contains \Drupal\summergame\Form\SummerGameLeagueJoinForm.
 */

namespace Drupal\summergame\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SummerGameLeagueJoinForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'summergame_league_join_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $pid = 0) {
    $player = summergame_player_load($pid);

    $form = [
      '#attributes' => ['class' => 'form-width-exception']
    ];
    $form['pid'] = [
      '#type' => 'value',
      '#value' => $player['pid'],
    ];
    $form['explaination'] = [
      '#markup' => '<h3>Enter a League code for ' . ($player['nickname'] ? $player['nickname'] : $player['name']) . '</h3>'
    ];
    $form['league_code'] = [
      '#type' => 'textfield',
      '#title' => t('League Code'),
      '#size' => 32,
      '#maxlength' => 255,
      '#default_value' => ($_GET['text'] ?? ''),
      '#prefix' => '<div class="container-inline">',
      '#suffix' => '</div>',
      '#attributes' => ['autofocus' => '']
    ];
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
        '#title' => 'Add these Players to the League'
      ];
    }
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Submit'),
      '#prefix' => '<div class="sg-form-actions">'
    ];
    $form['cancel'] = [
      '#type' => 'link',
      '#title' => 'Return to Player Page',
      '#url' => Url::fromRoute('summergame.player'),
      '#suffix' => '</div>'
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
    $pids = [];
    if (is_array($form_state->getValue('pids'))) {
      foreach ($form_state->getValue('pids') as $pid => $selected) {
        if ($pid == $selected) {
          $pids[] = $pid;
        }
      }
      $_SESSION['summergame_pid_defaults'] = json_encode($pids);
    }
    else {
      $pids[] = $form_state->getValue('pid');
    }

    foreach ($pids as $pid) {
      $player = summergame_player_load(['pid' => $pid]);
      $status = summergame_player_join_league($pid, $form_state->getValue('league_code'));
    }

    return;
  }
}
