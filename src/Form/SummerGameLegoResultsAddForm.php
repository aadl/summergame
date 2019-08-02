<?php

/**
 * @file
 * Contains \Drupal\accountfix\Form\SummerGameLegoResultsAddForm
 */

namespace Drupal\summergame\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Predis\Client;

class SummerGameLegoResultsAddForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'summergame_lego_results_add_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['add'] = [
      '#type' => 'textfield',
      '#title' => t('Add Lego Vote'),
      '#size' => 16,
      '#maxlength' => 16,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Add Vote'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $redis = new Client(\Drupal::config('summergame.settings')->get('summergame_redis_conn'));

    $add = strtoupper($form_state->getValue('add'));
    $redis->set('lego_vote:' . $add[1] . ':' . time(), $add);
    drupal_set_message("Added 1 vote for $add");

    return;
  }
}
