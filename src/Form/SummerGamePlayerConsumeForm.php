<?php

/**
 * @file
 * Contains \Drupal\summergame\Form\SummerGamePlayerConsumeForm.
 */

namespace Drupal\summergame\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SummerGamePlayerConsumeForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'summergame_player_consume_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $pid = 0, $bnum = 0) {
    $player = summergame_player_load($pid);

    // if ($bnum) {
    //   if ($bib = $locum->get_bib_item($bnum)) {
    //     $form['bib'] = [
    //       '#type' => 'value',
    //       '#value' => $bib,
    //     ];
    //     $title = title_case($bib['title']);
    //     if ($bib['title_medium']) {
    //       $title .= ' ' . title_case($bib['title_medium']);
    //     }
    //     $finished_default = 1;
    //   }
    // }

    $form['pid'] = [
      '#type' => 'value',
      '#value' => $pid,
    ];
    $form['mat_code'] = [
      '#type' => 'select',
      '#title' => t("I've been enjoying this"),
      // '#default_value' => $bib['mat_code'],
      // '#options' => $locum->locum_config['formats'],
      '#prefix' => "<div class=\"container-inline\">",
      '#suffix' => "</div>",
    ];
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => t('Titled'),
      // '#default_value' => $title,
      '#size' => 64,
      '#maxlength' => 128,
      '#description' => t('Title of the Book/Movie/Music that you are reporting'),
      '#required' => TRUE,
    ];
    // $form['duration'] = [
    //   '#type' => 'textfield',
    //   '#title' => t('for this many minutes or pages (optional)'),
    //   '#size' => 16,
    //   '#maxlength' => 16,
    //   '#description' => t('Enter the # of pages OR the length in minutes for 1 point per page or minute! (Maximum 500 points)'),
    // ];
    // $form['finished'] = [
    //   '#type' => 'checkbox',
    //   '#title' => 'and I finished it!',
    //   // '#default_value' => $finished_default,
    //   '#description' => 'check this box to receive a 100 point bonus for finishing this item (optional)',
    // ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Score!'),
    ];
    $form['cancel'] = [
      '#type' => 'link',
      '#title' => 'Cancel',
      '#url' => \Drupal\Core\Url::fromRoute('summergame.player')
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
    $db = \Drupal::connection();

    // check for daily read watch listen bonus
    $type = 'Read Watched Listened';
    $points = 0;
    $game_term = \Drupal::config('summergame.settings')->get('summergame_current_game_term');
    $res = $db->query("SELECT * FROM sg_ledger WHERE pid=:pid AND type='Read Watched Listened Daily Bonus' ORDER BY lid DESC LIMIT 1", [':pid' => $form_state->getValue('pid')])->fetch();
    if (date('mdY', $res->timestamp) != date('mdY', time())) {
      $type .= ' Daily Bonus';
      $points = 10;
    }

    $db->insert('sg_ledger')
      ->fields([
        'pid' => $form_state->getValue('pid'),
        'points' => $points,
        'type' => $type,
        'description' => $form_state->getValue('title'),
        'game_term' => $game_term,
        'timestamp' => time()
      ])
      ->execute();

      drupal_set_message("You logged a $type for $points!");
  }

}
