<?php

/**
 * @file
 * Contains \Drupal\accountfix\Form\SummerGameDeletePlayerForm
 */

namespace Drupal\summergame\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SummerGameDeletePlayerForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'summergame_delete_player_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $pid = 0) {
    $pid = (int) $pid;
    if (summergame_player_access($pid)) {
      if ($player = summergame_player_load(['pid' => $pid])) {
        $form = [];
        $form['player'] = [
          '#type' => 'value',
          '#value' => $player,
        ];
        $player_name = ($player['nickname'] ? $player['nickname'] : $player['name']);
        $form['warning'] = [
          '#markup' => "Are you sure you want to delete player $player_name? All associated scores and badges will be deleted as well. This action cannot be undone.",
        ];
        $form['inline'] = [
          '#prefix' => '<div class="container-inline">',
          '#suffix' => '</div>',
        ];
        $form['inline']['submit'] = [
          '#type' => 'submit',
          '#value' => t('Delete'),
          '#prefix' => '<div class="sg-form-actions">'
        ];
        $form['inline']['cancel'] = [
          '#type' => 'link',
          '#title' => $this->t('Cancel'),
          '#url' => Url::fromRoute('summergame.player', ['pid' => $pid]),
          '#suffix' => '</div>'
        ];

        return $form;
      }
      else {
        // No Player ID
        \Drupal::messenger()->addError("Unable to find player entry");
        return $this->redirect('summergame.player');
      }
    }
    else {
      // No access to Player
      \Drupal::messenger()->addError("Unable to access player");
      return $this->redirect('summergame.player');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $player = $form_state->getValue('player');
    summergame_player_delete(['pid' => $player['pid']]);
    $player_name = ($player['nickname'] ? $player['nickname'] : $player['name']);
    \Drupal::messenger()->addMessage("Deleted player $player_name and all associated points and badges");
    $form_state->setRedirect('summergame.player');

    return;
  }
}
