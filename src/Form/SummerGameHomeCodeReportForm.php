<?php

/**
 * @file
 * Contains \Drupal\summergame\Form\SummerGameHomeCodeReportForm.
 */

namespace Drupal\summergame\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SummerGameHomeCodeReportForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'summergame_home_code_report_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $code_id = 0) {
    $db = \Drupal::database();
    $code_id = (int) $code_id;

    if ($player = summergame_get_active_player()) {
      $form = [
        '#attributes' => ['class' => 'form-width-exception']
      ];

      if ($code_id) {
        $game_code = $db->query("SELECT * FROM sg_game_codes WHERE code_id = $code_id AND clue LIKE '%\"homecode\"%'")->fetchObject();
      }

      if ($game_code->code_id) {
        // Check to see if player has already reported this code
        $player_id = $player['pid'];
        $geocode_data = json_decode($game_code->clue);
        if (!isset($geocode_data->reports->$player_id)) {
          $form['player'] = [
            '#type' => 'value',
            '#value' => $player,
          ];
          $form['code_id'] = [
            '#type' => 'value',
            '#value' => $game_code->code_id,
          ];

          $form['display'] = [
            '#markup' => '<h1>Report Lawn Code</h1>' .
            '<p>Having trouble finding the Lawn Code for at the following address?</p>' .
            '<p>' . $geocode_data->homecode . '</p>' .
            "<p>If you report this Lawn Code, we'll send a reminder to the owner to make sure it's viewable in the right location.</p>"
          ];

          $form['inline'] = [
            '#prefix' => '<div class="container-inline">',
            '#suffix' => '</div>',
          ];
          $form['inline']['submit'] = array(
            '#type' => 'submit',
            '#value' => t('Report Lawn Code'),
            '#prefix' => '<div class="sg-form-actions">'
          );
          $form['inline']['cancel'] = [
            '#type' => 'link',
            '#title' => $this->t('Back to Map'),
            '#url' => \Drupal\Core\Url::fromRoute('summergame.map'),
          ];
        }
        else {
          \Drupal::messenger()->addError('You have already reported this Lawn Code');
          return $this->redirect('summergame.map');
        }
      }
      else {
        \Drupal::messenger()->addError('Unable to load Lawn Code with ID ' . $code_id);
        return $this->redirect('summergame.map');
      }
    }
    else {
      \Drupal::messenger()->addError('Unable to load player record for current user');
      return $this->redirect('summergame.map');
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $db = \Drupal::database();
    $summergame_homecode_report_threshold = \Drupal::config('summergame.settings')->get('summergame_homecode_report_threshold');
    $code_id = $form_state->getValue('code_id');
    $player = $form_state->getValue('player');

    // Grab all home code related data from code id
    $code_data = $db->query("SELECT * FROM sg_game_codes s, users_field_data u WHERE s.code_id = :code_id AND s.creator_uid = u.uid",
                            [':code_id' => $code_id])->fetchObject();

    // Update Home Code reports with player id
    $player_pid = $player['pid'];
    $geocode_data = json_decode($code_data->clue);
    if (!isset($geocode_data->reports)) {
      $geocode_data->reports = new \stdClass();
    }
    $geocode_data->reports->$player_pid = $player_pid;
    $db->update('sg_game_codes')->fields(['clue' => json_encode($geocode_data)])->condition('code_id', $code_id)->execute();

    $homecode_email = \Drupal::config('summergame.settings')->get('summergame_homecode_notify_email');

    if (count((array)$geocode_data->reports) >= $summergame_homecode_report_threshold) {
      // Send email to Home Code owner
      $headers = "From: $homecode_email" . "\r\n" .
                "Reply-To: $homecode_email" . "\r\n" .
                "X-Mailer: PHP/" . phpversion() .
                "Content-Type: text/html; charset=\"us-ascii\"";

      mail($code_data->mail,
        'Your Summer Game Lawn Code has been reported',
        "Hello there!\n" .
        "Some Summer Game players have reached out to let us know they are having difficulty locating your Lawn Code at this address:\n\n" .
        str_replace('<br>', "\n", $geocode_data->homecode) . "\n\n" .
        "Would you please check to make sure your code is written clearly and is visible from the street or sidewalk?\n" .
        "If you didn't make a lawn code or if you have any questions, please Contact Us for more information!\n" .
        "Thank you so much and happy playing!\n\n-The Summer Game Team",
        $headers
      );
    }

    // Send email to staff
    $reports = (array)$geocode_data->reports;
    mail($homecode_email,
      'Lawn Code Reported: ' . $code_data->text,
      "We have received a report of a Lawn Code that a player was unable to find.\n\n" .
      "Lawn Code Details:\n" .
      \Drupal\Core\Url::fromRoute('summergame.admin.gamecode', ['code_id' => $code_data->code_id], ['absolute' => TRUE])->toString() . "\n" .
      "Code Text: $code_data->text\n" .
      "Code description: $code_data->description\n" .
      "Number of Code Redemptions: $code_data->num_redemptions\n" .
      "Reported by: " . count($reports) . " (Player IDs: " . implode(", ", $reports) . ")\n" .
      "Address: " . str_replace('<br>', " ", $geocode_data->homecode) . "\n" .
      "Creator Username: $code_data->name\n" .
      "Creator email: $code_data->mail\n\n" .
      "Reporting Player Details:\n" .
      Url::fromRoute('summergame.player', ['pid' => $player['pid']], ['absolute' => TRUE])->toString() . "\n" .
      "Player Name: " . $player['name'] . "\n" .
      "Player Nickname: " . $player['nickname'] . "\n" .
      "Player Drupal User ID: " . $player['uid']
    );

    \Drupal::messenger()->addMessage('Lawn Code Reported. Thank you for helping with the Summer Game!');
    $form_state->setRedirect('summergame.map');

    return;
  }
}
