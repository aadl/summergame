<?php

/**
 * @file
 * Contains \Drupal\accountfix\Form\SummerGamePlayerDeleteScoreForm
 */

namespace Drupal\summergame\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SummerGamePlayerDeleteScoreForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'summergame_player_delete_score_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $pid = 0, $lid = 0) {
    $pid = (int) $pid;
    $lid = (int) $lid;
    if (summergame_player_access($pid)) {
      $db = \Drupal::database();
      $ledger = $db->query("SELECT * FROM sg_ledger WHERE pid = :pid AND lid = :lid",
                           [':pid' => $pid, ':lid' => $lid])->fetchObject();

      if ($ledger->lid) {
        // Cannot delete points that are protected (e.g. Shop points)
        if (strpos($ledger->metadata, 'delete:no') === 0) {
          drupal_set_message('Sorry, these points are protected and cannot be deleted', 'error');
          $form_state->setRedirect('summergame.player', ['pid' => $pid]);
        }
        else {
          $form = [];
          $form['lid'] = [
            '#type' => 'value',
            '#value' => $ledger->lid,
          ];
          $form['pid'] = [
            '#type' => 'value',
            '#value' => $pid,
          ];
          $description = trim(preg_replace('/[\w]+:[\w]+/', '', $ledger->description));
          $form['warning'] = [
            '#markup' => "Are you sure you want to delete $ledger->points points for \"$description\"? This action cannot be undone.",
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
            '#url' => \Drupal\Core\Url::fromRoute('summergame.player', ['pid' => $pid]),
            '#suffix' => '</div>'
          ];

          return $form;
        }
      }
      else {
        // No ledger ID
        drupal_set_message("Unable to find ledger entry", 'error');
        return $this->redirect('summergame.player', ['pid' => $pid]);
      }
    }
    else {
      // No access to Player
      drupal_set_message("Unable to access player", 'error');
      return $this->redirect('summergame.player');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $db = \Drupal::database();
    $db->delete('sg_ledger')->condition('lid', $form_state->getValue('lid'))->execute();

    drupal_set_message('Score has been deleted.');

    $form_state->setRedirect('summergame.player', ['pid' => $form_state->getValue('pid')]);

    return;
  }
}
