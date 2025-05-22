<?php

/**
 * @file
 * Contains \Drupal\patron_comments\Form\SummerGamePlayerLedgerFilterForm.
 */

namespace Drupal\summergame\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SummerGamePlayerLedgerFilterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'summergame_player_ledger_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $pid = NULL, $ledger_types = NULL) {
    $form = [
      '#prefix' => '<div class="container-inline">',
      '#suffix' => '</div>',
    ];

    $all_option = [0 => 'All'];
    $ledger_types = $all_option + $ledger_types;

    $form['pid'] = [
      '#type' => 'value',
      '#value' => $pid,
    ];
    $form['filter_type'] = [
      '#type' => 'select',
      '#title' => "Type",
      '#default_value' => $_GET['filter_type'] ?? '',
      '#options' => $ledger_types,
    ];
    $form['filter_search'] = [
      '#type' => 'textfield',
      '#title' => "Search",
      '#default_value' => $_GET['filter_search'] ?? '',
      '#size' => "16",
    ];
    $form['filter_submit'] = [
      '#type' => 'submit',
      '#value' => "Filter",
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    ;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Grab filter data from form
    $filter_values = [
      'filter_type' => $form_state->getValue('filter_type'),
      'filter_search' => $form_state->getValue('filter_search'),
    ];
    // Add game_term to filter values
    if (isset($_GET['term'])) {
      $filter_values['term'] = $_GET['term'];
    }

    // Redirect to current page with variables
    $form_state->setRedirect('summergame.player.ledger', ['pid' => $form_state->getValue('pid')], [
      'query' => $filter_values,
    ]);
  }
}
