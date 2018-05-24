<?php

/**
 * @file
 * Contains \Drupal\accountfix\Form\SummerGamePlayerSearchForm
 */

namespace Drupal\summergame\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SummerGamePlayerSearchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'summergame_player_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $code_id = 0) {
    $form = [];

    $form['inline'] = [
      '#prefix' => '<div class="container-inline">',
      '#suffix' => '</div>',
    ];
    $form['inline']['search_term'] = [
      '#type' => 'textfield',
      '#title' => t('Search for a Player'),
      '#size' => 32,
      '#maxlength' => 32,
      '#default_value' => $term,
    ];
    $form['inline']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Search'),
    ];
    $form['hint'] = [
      '#value' => '<p>' .
                  '<em>Search by name, partial name, cell number, or game card number.<br />' .
                  '(e.g. "Anne Arbor", "Arbor", "3274200", "SRG45678")</em>' .
                  '</p>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('summergame.admin.players', ['search_term' => $form_state->getValue('search_term')]);

    return;
  }
}
