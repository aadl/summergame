<?php

/**
 * @file
 * Contains \Drupal\accountfix\Form\SummerGameLeagueDeleteForm
 */

namespace Drupal\summergame\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SummerGameLeagueDeleteForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'summergame_league_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $league_id = 0) {
    $league_id = (int) $league_id;

    if (summergame_player_access($league_id)) {
      $db = \Drupal::database();
      $league_owner = summergame_player_load($league_id);
      $league_player_count = $db->select('sg_players_leagues', 'spl')
                                ->condition('lid', $league_id)
                                ->countQuery()
                                ->execute()
                                ->fetchField();
      $league_name = $league_owner['nickname'] ? $league_owner['nickname'] : $league_owner['name'];
      $form = [];
      $form['#attributes'] = ['class' => 'form-width-exception'];
      $form['league_id'] = [
        '#type' => 'value',
        '#value' => $league_id,
      ];
      $form['warning'] = [
        '#markup' => "<h1>Delete My League</h1><p>Are you sure you want to delete $league_name's League? It currently has $league_player_count Members.</p>" .
                     "<p>If you subsequently create a new league all members will need to enter your new code to rejoin. This action cannot be undone.</p>",
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
        '#url' => Url::fromRoute('summergame.player.leagues', ['pid' => $league_id]),
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
    $league_id = $form_state->getValue('league_id');
    summergame_delete_league($league_id);

    $form_state->setRedirect('summergame.player.leagues', ['pid' => $league_id]);

    return;
  }
}
