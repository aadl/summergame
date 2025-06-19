<?php

/**
 * @file
 * Contains \Drupal\accountfix\Form\SummerGameLeagueLeaveForm
 */

namespace Drupal\summergame\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SummerGameLeagueLeaveForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'summergame_league_leave_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $pid = 0, $league_id = 0) {
    $pid = (int) $pid;
    $league_id = (int) $league_id;

    if ($pid == $league_id) {
      // Player cannot leave their own league.
      \Drupal::messenger()->addError("You cannot leave your own League.");
      return $this->redirect('summergame.player.leagues', ['pid' => $pid]);
    }

    if (summergame_player_access($pid) && summergame_league_access($league_id)) {
      $db = \Drupal::database();
      $league_owner = summergame_player_load($league_id);
      $league_name = $league_owner['nickname'] ? $league_owner['nickname'] : $league_owner['name'];

      $form = [];
      $form['#attributes'] = ['class' => 'form-width-exception'];
      $form['pid'] = [
        '#type' => 'value',
        '#value' => $pid,
      ];
      $form['league_id'] = [
        '#type' => 'value',
        '#value' => $league_id,
      ];
      $form['warning'] = [
        '#markup' => "<h1>Leave League</h1><p>Are you sure you want to leave $league_name's League?</p>" .
                     "<p>If you wish to rejoin you will need to reenter the league code. This action cannot be undone.</p>",
      ];
      $form['inline'] = [
        '#prefix' => '<div class="container-inline">',
        '#suffix' => '</div>',
      ];
      $form['inline']['submit'] = [
        '#type' => 'submit',
        '#value' => t('Leave League'),
        '#prefix' => '<div class="sg-form-actions">'
      ];
      $form['inline']['cancel'] = [
        '#type' => 'link',
        '#title' => $this->t('Cancel'),
        '#url' => Url::fromRoute('summergame.player.leagues', ['pid' => $pid]),
        '#suffix' => '</div>'
      ];

      return $form;
    }
    else {
      // No access to Player
      \Drupal::messenger()->addError("Unable to access League");
      return $this->redirect('summergame.player.leagues');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $pid = $form_state->getValue('pid');
    $league_id = $form_state->getValue('league_id');
    summergame_player_leave_league($pid, $league_id);

    $form_state->setRedirect('summergame.player.leagues', ['pid' => $pid]);

    return;
  }
}
