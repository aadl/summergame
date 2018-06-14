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
    $guzzle = \Drupal::httpClient();
    $api_url = \Drupal::config('arborcat.settings')->get('api_url');

    if ($bnum) {
      // Get Bib Record from API
      $json = json_decode($guzzle->get("$api_url/record/$bnum/harvest")->getBody()->getContents());
      $bib = $json->bib;
      if ($bib) {
        $form['bib'] = [
          '#type' => 'value',
          '#value' => $bib,
        ];
        $title = title_case($bib['title']);
        if ($bib['title_medium']) {
          $title .= ' ' . title_case($bib['title_medium']);
        }
      }
    }

    $mat_types = $guzzle->get("$api_url/mat-names")->getBody()->getContents();
    $mat_names = json_decode($mat_types);

    $form['pid'] = [
      '#type' => 'value',
      '#value' => $pid,
    ];
    $form['mat_code'] = [
      '#type' => 'select',
      '#title' => t("I've been enjoying this"),
      '#default_value' => $bib['mat_code'],
      '#options' => $mat_names,
      '#prefix' => "<div class=\"container-inline\">",
      '#suffix' => "</div>",
    ];
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => t('Titled'),
      '#default_value' => ($title ?? ''),
      '#size' => 64,
      '#maxlength' => 128,
      '#description' => t('Title of the Book/Movie/Music that you are reporting'),
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Score!'),
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
