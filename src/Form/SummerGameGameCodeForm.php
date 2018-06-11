<?php

/**
 * @file
 * Contains \Drupal\summergame\Form\SummerGameGameCodeForm.
 */

namespace Drupal\summergame\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SummerGameGameCodeForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'summergame_game_code_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $code_id = 0) {
    $db = \Drupal::database();
    $code_id = (int) $code_id;

    $form = [];

    if ($code_id) {
      $game_code = $db->query("SELECT * FROM sg_game_codes WHERE code_id = $code_id")->fetchAssoc();
    }
    if ($game_code['code_id']) {
      $form['code_id'] = [
        '#type' => 'value',
        '#value' => $game_code['code_id'],
      ];
/*
      // Search for code in catalog
      $l = sopac_get_locum();
      $tags = $l->db_query('SELECT * FROM insurge_tags WHERE tag="sg:code=' . $game_code['text'] . '"', 0);
      foreach ($tags as $tag) {
        $bibs[] = $l->get_bib_item($tag['bnum']);
      }

      if (count($bibs)) {
        $gc_bibs = '<p><h2>Game Code in Catalog:</h2><ul>';
        foreach ($bibs as $bib) {
          $gc_bibs .= '<li>' . l($bib['title'], 'catalog/record/' . $bib['_id']);
          if ($bib['author']) {
            $gc_bibs .= ', by ' . $bib['author'];
          }
          // link for deleting tag, has delay on refresh for tag removal to process
          $delURL = l('[Remove]', 'catalog/record/' . $bib['_id'], [
            'attributes' => [
              'target' => '_blank',
              'onclick' => 'setTimeout("document.location.reload()", 5000)',
            ),
            'query' => 'deltag=sg:code=' . $game_code['text'] . '&uid=106148',
          ));
          $gc_bibs .= '&nbsp;' . $delURL . '</li>';
        }
        $gc_bibs .= '</ul></p>';
      }
*/
    }

    $form['text'] = [
      '#type' => 'textfield',
      '#title' => t('Code Text'),
      '#default_value' => $game_code['text'],
      '#size' => 32,
      '#maxlength' => 255,
      '#description' => t('Keyword text for this game code (e.g. APPLESAUCE)'),
      '#required' => TRUE,
    ];
    $form['description'] = [
      '#type' => 'textarea',
      '#title' => t('Description'),
      '#default_value' => $game_code['description'],
      '#description' => t('Description of the game code award (e.g. Attended Apple Peeling Event)'),
      '#required' => TRUE,
    ];
    $form['clue'] = [
      '#type' => 'textarea',
      '#title' => t('Clue'),
      '#default_value' => $game_code['clue'],
      '#description' => t('Clue leading to THIS game code. Will be revealed when Clue Trigger code is entered'),
    ];
    $form['clue_trigger'] = [
      '#type' => 'textfield',
      '#title' => t('Clue Trigger'),
      '#default_value' => $game_code['clue_trigger'],
      '#size' => 32,
      '#maxlength' => 255,
      '#description' => t('Game code text to trigger this clue (e.g. APPLES)'),
    ];
    $form['hint'] = [
      '#type' => 'textarea',
      '#title' => t('Hint'),
      '#default_value' => $game_code['hint'],
      '#description' => t('Does this code need a hint to figuring it out? Enter a hint here to help figure it out. (e.g. This code is a kind of food made by Mott\'s)'),
    ];
    $form['points'] = [
      '#type' => 'textfield',
      '#title' => t('Points'),
      '#default_value' => $game_code['points'],
      '#size' => 16,
      '#maxlength' => 8,
      '#description' => t('Points to be awarded for the game code (e.g. 100)'),
      '#required' => TRUE,
    ];
    $form['diminishing'] = [
      '#type' => 'checkbox',
      '#title' => t('Diminishing?'),
      '#default_value' => $game_code['diminishing'],
      '#description' => 'Each successive redemption of the game code will be worth 1 less than the previous redemption',
    ];
    $form['max_redemptions'] = [
      '#type' => 'select',
      '#title' => t('Max Redemptions'),
      '#default_value' => $game_code['max_redemptions'],
      '#options' => [
        '0' => t('Unlimited'),
        '1' => t('Single User'),
        '2' => '2',
        '3' => '3',
        '4' => '4',
        '5' => '5',
        '10' => '10',
        '25' => '25',
        '50' => '50',
        '100' => '100',
      ],
      '#description' => t('Number of players who can receive points for this award'),
    ];
    $form['valid_start'] = [
      '#type' => 'textfield',
      '#title' => t('Code Start Time Limit'),
      '#default_value' => date('Y-m-d H:i:s', ($game_code['valid_start'] ? $game_code['valid_start'] : time())),
      '#size' => 32,
      '#maxlength' => 32,
      '#description' => t('Date/Time for when the code becomes active (e.g. "2012-07-01 9:00 AM")'),
      '#required' => TRUE,
    ];
    $form['valid_end'] = [
      '#type' => 'textfield',
      '#title' => t('Code End Time Limit'),
      '#default_value' => $game_code['valid_end'] ? date('Y-m-d H:i:s', $game_code['valid_end']) : \Drupal::config('summergame.settings')->get('summergame_gamecode_default_end'),
      '#size' => 32,
      '#maxlength' => 32,
      '#description' => t('Date/Time for when the code stops being active (e.g. "2013-08-31 12:00 AM")'),
      '#required' => TRUE,
    ];
    $form['game_term'] = [
      '#type' => 'textfield',
      '#title' => t('Game Term'),
      '#default_value' => $game_code['game_term'] ? $game_code['game_term'] : \Drupal::config('summergame.settings')->get('summergame_current_game_term'),
      '#size' => 32,
      '#maxlength' => 32,
      '#description' => t('Points will be awarded under this Game Term'),
      '#required' => TRUE,
    ];
    $form['everlasting'] = [
      '#type' => 'checkbox',
      '#title' => t('Everlasting?'),
      '#default_value' => $game_code['everlasting'],
      '#description' => 'Should this code be active in following seasons of the Game? (e.g. a code for a collection badge)',
    ];
    if (!$game_code['code_id']) {
      $form['tag_bib_confirm'] = [
        '#type' => 'radios',
        '#title' => t('Is this code for a catalog item?'),
        '#options' => ['tag_bib_yes' => t('Yes'), 'tag_bib_no' => t('No')],
        '#required' => TRUE,
      ];
    }
    $form['tag_bib'] = [
      '#type' => 'textfield',
      '#title' => t('Tag Bib Number'),
      '#size' => 32,
      '#description' => t('Enter a Bib Number/ID to automatically add this game code as a tag to that Bib record in the catalog'),
      '#suffix' => $gc_bibs,
    ];

    $form['inline'] = [
      '#prefix' => '<div class="container-inline">',
      '#suffix' => '</div>',
    ];
    $form['inline']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save Game Code'),
    );
    $form['inline']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => \Drupal\Core\Url::fromRoute('summergame.admin'),
    ];

    if ($game_code['code_id']) {
    $form['inline']['delete'] = [
      '#type' => 'link',
      '#title' => $this->t('DELETE'),
      '#url' => \Drupal\Core\Url::fromRoute('summergame.admin.gamecode.delete', ['code_id' => $game_code['code_id']]),
    ];
  }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Remove non-alphanumerics from Game Code text
    $text = preg_replace('/[^A-Za-z0-9]/', '', $form_state->getValue('text'));

    // Check whether new game code is unique
    if (!$form_state->getValue('code_id')) {
      $db = \Drupal::database();
      $code = $db->query("SELECT code_id FROM sg_game_codes WHERE text LIKE :text", [':text' => $text])->fetchObject();
      if ($code->code_id) {
        $form_state->setErrorByName('text', 'Code text is already in use. Please select another code.');
      }
    }

    $points = (int) $form_state->getValue('points');
    if ($points < 1) {
      $form_state->setErrorByName('points', 'Please enter a number for the point value');
    }

    // Update form_state fields
    $form_state->setValue('text', $text);
    $form_state->setValue('points', $points);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $db = \Drupal::database();

    // Set up fields
    $fields = [
      'text' => strtoupper(str_replace(["\r", "\n", ' '], '', $form_state->getValue('text'))),
      'description' => str_replace(["\r", "\n"], '', $form_state->getValue('description')),
      'hint' => str_replace(["\r", "\n"], '', trim($form_state->getValue('hint'))),
      'clue' => str_replace(["\r", "\n"], '', $form_state->getValue('clue')),
      'clue_trigger' => strtoupper(str_replace(["\r", "\n", ' '], '', $form_state->getValue('clue_trigger'))),
      'points' => $form_state->getValue('points'),
      'diminishing' => $form_state->getValue('diminishing'),
      'max_redemptions' => $form_state->getValue('max_redemptions'),
      'valid_start' => strtotime($form_state->getValue('valid_start')),
      'valid_end' => strtotime($form_state->getValue('valid_end')),
      'game_term' => trim($form_state->getValue('game_term')),
      'everlasting' => $form_state->getValue('everlasting'),
    ];

    // Set end time if blank to default end date, otherwise one month out
    if (empty($fields['valid_end'])) {
      $fields['valid_end'] = strtotime(\Drupal::config('summergame.settings')->get('summergame_gamecode_default_end'));
      if (empty($fields['valid_end'])) {
        $fields['valid_end'] = strtotime('+1 month');
      }
    }

    if ($code_id = $form_state->getValue('code_id')) {
      // Update existing code
      $db->update('sg_game_codes')->fields($fields)->condition('code_id', $code_id)->execute();
      drupal_set_message('Game Code ' . $values['text'] . ' Updated');
    }
    else {
      $fields['creator_uid'] = \Drupal::currentUser()->id();
      $fields['created'] = time();

      $db->insert('sg_game_codes')->fields($fields)->execute();
      drupal_set_message('Game Code ' . $fields['text'] . ' Created');
    }

    // Add tag to catalog if selected
    if ($tag_bib = $form_state->getValue('tag_bib')) {
      $tag_bib = trim($tag_bib);
      $result = summergame_tag_bib($tag_bib, $fields['text'], $fields['game_term']);
      if (isset($result['error'])) {
        drupal_set_message('Game Code Tag ERROR ' . $result['error']);
      } else {
        drupal_set_message('Added Game Code Tag to Catalog for ' . $result['success']->title);
      }
    }

    $form_state->setRedirect('summergame.admin');

    return;
  }
}
