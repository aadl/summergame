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
    $player = summergame_player_load($pid);

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
    $form['explaination'] = [
      '#markup' => '<h3>Mark this badge as completed for ' . ($player['nickname'] ? $player['nickname'] : $player['name']) . '</h3>'
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
        '#title' => 'Redeem for players'
      ];
    }
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('I Completed This Badge!'),
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

    $bid = $form_state->getValue('bid');

    foreach ($pids as $pid) {
      $awarded = summergame_player_award_badge($pid, $bid);
      if ($awarded) {
        $player = summergame_player_load(['pid' => $pid]);
        $name = ($player['nickname'] ? $player['nickname'] : $player['name']);
        $badge_node = \Drupal::entityTypeManager()->getStorage('node')->load($bid);
        $badge_title = $badge_node->get('title')->value;
        \Drupal::messenger()->addMessage("$name earned the $badge_title Badge!");
      }
    }

    return;
  }
}
