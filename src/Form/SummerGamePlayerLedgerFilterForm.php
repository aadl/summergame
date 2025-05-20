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
/*
    $comment_status = $all_option + $comment_status;
    $comment_refer = $all_option + $comment_refer;
*/
//    $defaults = \Drupal::service('user.data')->get('patron_comments', \Drupal::currentUser()->id(), 'admin_filter');

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
/*
    $form['filter_status'] = [
      '#type' => 'select',
      '#title' => "Status",
      '#default_value' => $defaults['filter_status'] ?? '',
      '#options' => $comment_status,
    ];
    $form['filter_refer'] = [
      '#type' => 'select',
      '#title' => "Refer",
      '#default_value' => $defaults['filter_refer'] ?? '',
      '#options' => $comment_refer,
    ];

    $form['filter_number'] = [
      '#type' => 'textfield',
      '#title' => "ID",
      '#default_value' => $defaults['filter_number'] ?? '',
      '#size' => "6",
    ];
*/
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
/*
      'filter_status' => $form_state->getValue('filter_status'),
      'filter_refer' => $form_state->getValue('filter_refer'),
      'filter_number' => $form_state->getValue('filter_number'),
*/
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
