<?php

/**
 * @file
 * Contains \Drupal\summergame\Form\SummerGameGameCodeBatchForm.
 */

namespace Drupal\summergame\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SummerGameGameCodeBatchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'summergame_game_code_batch_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Get field names from database for sg_game_codes
    $field_names = [];
    $db = \Drupal::database();
    $columns = $db->query("SHOW COLUMNS FROM {sg_game_codes}")->fetchAll();
    foreach ($columns as $column) {
      $field_names[] = $column->Field;
    }

    $form = [
      '#attributes' => ['class' => 'form-width-exception']
    ];

    $form['csv'] = [
      '#type' => 'textarea',
      '#title' => t('Batch Text'),
      '#rows' => 12,
      '#description' => t('Comma separated values for batch creation. First row determines field mapping:') . '<br>' .
                        '&bull; ' . implode('<br>&bull; ', $field_names),
      '#required' => TRUE,

    ];
    $form['actions'] = [
      '#prefix' => '<div class="sg-form-actions">',
      '#suffix' => '</div>'
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Batch Create'),
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('summergame.admin'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Parse the rows
    $csv = str_getcsv($form_state->getValue('csv'), "\n");

    // Grab field names from first row of text
    $field_names = str_getcsv(array_shift($csv));

    // Check for required fields
    $required_fields = ['text','description','points'];
    foreach ($required_fields as $required_field) {
      if (!in_array($required_field, $field_names)) {
        $form_state->setErrorByName('csv', 'Missing required column "' . $required_field . '"');
      }
    }

    $form_state->setValue('field_names', $field_names);
    $form_state->setValue('csv_rows', $csv);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $summergame_settings = \Drupal::config('summergame.settings');
    $now = time();
    $db = \Drupal::database();

    $field_names = $form_state->getValue('field_names');
    $csv_rows = $form_state->getValue('csv_rows');

    foreach ($csv_rows as $csv_row) {
      // Assign field names
      $csv_row = array_combine($field_names, str_getcsv($csv_row));

      // Check whether new game code is unique
      $code = $db->query("SELECT code_id FROM sg_game_codes WHERE text LIKE :text", [':text' =>  $csv_row['text']])->fetchObject();
      if ($code->code_id) {
        \Drupal::messenger()->addError('Code ' . $csv_row['text'] . ' is already in use. Please select another code.');
        continue;
      }

      // Set up fields
      $fields = [
        'creator_uid' => \Drupal::currentUser()->id(),
        'created' => $now,
        'text' => strtoupper(str_replace(["\r", "\n", ' '], '', $csv_row['text'])),
        'description' => str_replace(["\r", "\n"], '', $csv_row['description']),
        'hint' => isset($csv_row['hint']) ? str_replace(["\r", "\n"], '', trim($csv_row['hint'])) : '',
        'clue' => isset($csv_row['clue']) ? str_replace(["\r", "\n"], '', $csv_row['clue']) : '',
        'clue_trigger' => isset($csv_row['clue_trigger']) ? strtoupper(str_replace(["\r", "\n", ' '], '', $csv_row['clue_trigger'])) : '',
        'points' => $csv_row['points'],
        'diminishing' => isset($csv_row['diminishing']) ? $csv_row['diminishing'] : 0,
        'max_redemptions' => isset($csv_row['max_redemptions']) ? $csv_row['max_redemptions'] : 0,
        'valid_start' => isset($csv_row['valid_start']) ? strtotime($csv_row['valid_start']) : $now,
        'valid_end' => isset($csv_row['valid_end']) ? strtotime($csv_row['valid_end']) : '',
        'game_term' => isset($csv_row['game_term']) ? trim($csv_row['game_term']) : $summergame_settings->get('summergame_current_game_term'),
        'everlasting' => isset($csv_row['everlasting']) ? $csv_row['everlasting'] : 0,
        'link' => isset($csv_row['link']) ? $csv_row['link'] : '',
      ];

      // Set end time if blank to default end date, otherwise one month out
      if (empty($fields['valid_end'])) {
        $fields['valid_end'] = strtotime($summergame_settings->get('summergame_gamecode_default_end'));
        if (empty($fields['valid_end'])) {
          $fields['valid_end'] = strtotime('+1 month');
        }
      }

      $db->insert('sg_game_codes')->fields($fields)->execute();
      \Drupal::messenger()->addMessage('Game Code ' . $fields['text'] . ' Created');
    }

    $form_state->setRedirect('summergame.admin');

    return;
  }
}
