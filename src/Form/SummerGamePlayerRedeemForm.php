<?php

/**
 * @file
 * Contains \Drupal\summergame\Form\SummerGamePlayerRedeemForm.
 */

namespace Drupal\summergame\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SummerGamePlayerRedeemForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'summergame_player_redeem_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $pid = 0) {
    $player = summergame_player_load($pid);

    $form = [];
    $form['pid'] = [
      '#type' => 'value',
      '#value' => $player['pid'],
    ];
    $form['explaination'] = [
      '#markup' => '<p>You can find game codes for attending events, or by finding them around the library.</p>' .
      '<h3>Redeem a code for ' . ($player['nickname'] ? $player['nickname'] : $player['name']) . '</h3>'
    ];
    $form['code_text'] = [
      '#type' => 'textfield',
      '#title' => t('Code Text'),
      '#size' => 32,
      '#maxlength' => 255,
      '#default_value' => ($_GET['text'] ?? ''),
      '#prefix' => '<div class="container-inline">',
      '#suffix' => '</div>',
    ];
    $all_players = summergame_player_load_all($player['uid']);
    if (count($all_players) > 1) {
      $pid_options = [];
      if ($_SESSION['summergame_pid_defaults']) {
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
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Submit'),
      '#prefix' => '<div class="sg-form-actions">'
    ];
    $form['cancel'] = [
      '#type' => 'link',
      '#title' => 'Cancel',
      '#url' => \Drupal\Core\Url::fromRoute('summergame.player'),
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
      $status = summergame_redeem_code($player, $form_state->getValue('code_text'));
      if ($status['error']) {
        drupal_set_message($status['error'], 'error');
      }
      else if ($status['warning']) {
        drupal_set_message($status['warning'], 'warning');
      }
      else if ($status['success']) {
        drupal_set_message(['#markup' => $status['success']]);
        if (isset($status['clue'])) {
          drupal_set_message(['#markup' => 'New Clue: ' . $status['clue']]);
        }
      }
    }
    $form_state->setRedirect('summergame.player', ['pid' => $form_state->getValue('pid')]);

    return;
  }
}
