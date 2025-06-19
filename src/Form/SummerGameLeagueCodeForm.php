<?php

/**
 * @file
 * Contains \Drupal\summergame\Form\SummerGameLeagueCodeForm.
 */

namespace Drupal\summergame\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SummerGameLeagueCodeForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'summergame_league_code_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $pid = 0) {
    $player = summergame_player_load($pid);

    if ($player['league_code']) {
      $form['league_code'] = [
        '#markup' => '<h3>Your League Code: ' . $player['league_code'] . '</h3>' .
                     '<p>Share this code with friends to let them join your league.</p>' .
                     '<p>Anyone with this code can join your league, so <strong>be mindful of how you share it!!</strong></p>' .
                     '<p>The name of your league is your player name (no, we can\'t change it).</p>',
      ];
      $form['delete'] = [
        '#type' => 'link',
        '#title' => $this->t('Delete My League'),
        '#url' => Url::fromRoute('summergame.league.delete', ['league_id' => $pid]),
        '#suffix' => '</div>'
      ];
    }
    else {
      // Display League Code Generation Button
      $form = [
        '#attributes' => ['class' => 'form-width-exception']
      ];
      $form['pid'] = [
        '#type' => 'value',
        '#value' => $player['pid'],
      ];
      $form['explaination'] = [
        '#markup' => '<h3>Make Your Own League</h3>' .
                     '<p>Click the Make My League button to make your league. It will make a randomized code that you can share with friends who want to join. ' .
                     'Anyone with that code can join your league, so <strong>be mindful of how you share it!!</strong></p>' .
                     "<p>The name of your league will be your player name (no, we can't change it).</p>",
      ];
      $form['create'] = [
        '#type' => 'submit',
        '#value' => t('Make My League'),
      ];
    }
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

    $trigger = $form_state->getTriggeringElement()['#id'];

    if ($trigger == 'edit-delete') {
      summergame_delete_league($pid);
    }
    else {
      summergame_generate_league_code($pid);
    }

    return;
  }
}
