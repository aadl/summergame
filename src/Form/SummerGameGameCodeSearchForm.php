<?php

/**
 * @file
 * Contains \Drupal\summergame\Form\SummerGameGameCodeSearchForm
 */

namespace Drupal\summergame\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SummerGameGameCodeSearchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'summergame_game_code_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $search_term = '') {
    $form = [
      '#attributes' => ['class' => 'form-width-exception']
    ];

    $form['inline'] = [
      '#prefix' => '<div class="container-inline">',
      '#suffix' => '</div>',
    ];
    $form['inline']['search_term'] = [
      '#type' => 'textfield',
      '#title' => t('Search Game Codes'),
      '#size' => 32,
      '#maxlength' => 32,
      '#default_value' => $search_term,
    ];
    $form['inline']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Search'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('summergame.admin.gamecodes', ['search_term' => $form_state->getValue('search_term')]);

    return;
  }
}
