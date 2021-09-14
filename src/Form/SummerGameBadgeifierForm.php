<?php

/**
 * @file
 * Contains \Drupal\accountfix\Form\SummerGameBadgeifierForm
 */

namespace Drupal\summergame\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SummerGameBadgeifierForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'summergame_badgeifier_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $form['pid'] = [
      '#type' => 'textfield',
      '#title' => t('Player ID'),
      '#size' => 10,
      '#maxlength' => 10,
      '#description' => t('Player ID of the player to receive the Badge'),
    ];
    $form['badge_id'] = [
      '#type' => 'textfield',
      '#title' => t('Badge ID'),
      '#size' => 10,
      '#maxlength' => 10,
      '#description' => t('ID of the Badge to award to the player'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Badgeify'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $pid = $form_state->getValue('pid');
    $badge_id = $form_state->getValue('badge_id');

    if ($player = summergame_player_load(['pid' => $pid])) {
      $player_link = "<a href=\"/summergame/player/$pid\">Player #$pid</a>";
      // Award badge to the player
      if (summergame_player_award_badge($pid, $badge_id)) {
        \Drupal::messenger()->addMessage(['#markup' => "Awarded Badge #$badge_id to $player_link"]);
      }
      else {
        \Drupal::messenger()->addError(['#markup' => "Badge #$badge_id NOT awarded to $player_link. Already awarded?"]);
      }
    }
    else {
      \Drupal::messenger()->addError("No player with ID #$pid could be found");
    }

    $form_state->setRedirect('summergame.admin');
    return;
  }
}
