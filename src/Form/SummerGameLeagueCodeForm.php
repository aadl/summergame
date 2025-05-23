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
        '#markup' => '<h3>Your League Code: ' . $player['league_code'] . '</h3>'
      ];
      $form['pid'] = [
        '#type' => 'value',
        '#value' => $player['pid'],
      ];
      $form['delete'] = [
        '#type' => 'submit',
        '#value' => t('Delete League'),
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
      $form['create'] = [
        '#type' => 'submit',
        '#value' => t('Generate League Code'),
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
