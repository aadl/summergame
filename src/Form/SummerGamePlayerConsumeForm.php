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
  public function buildForm(array $form, FormStateInterface $form_state, $pid = 0) {
    $player = summergame_player_load($pid);
    $guzzle = \Drupal::httpClient();
    $api_url = \Drupal::config('arborcat.settings')->get('api_url');
    $db = \Drupal::database();

    $finished_default = 0;

    $form = [
      '#attributes' => ['class' => 'form-width-exception'],
    ];

    if ($_GET['bnum']) {
      // Get Bib Record from API
      $json = json_decode($guzzle->get($api_url . '/record/' . $_GET['bnum'] . '/harvest')->getBody()->getContents(), TRUE);
      $bib = $json['bib'];

      if ($bib) {
        $form['bnum'] = [
          '#type' => 'value',
          '#value' => $bib['id'],
        ];
        $title = $bib['title'];
        if ($bib['title_medium']) {
          $title .= ' ' . title_case($bib['title_medium']);
        }
        $finished_default = 1;
      }
    }

    $form['pid'] = [
      '#type' => 'value',
      '#value' => $pid,
    ];
    $form['message'] = [
      '#markup' => '<p>Logging points for player: <strong>' .
                   ($player['nickname'] ? $player['nickname'] : $player['name']) . '</strong>. ' .
                   'Earn 50 points for each day that you log something!</p>'
    ];
    $form['consume_type'] = [
      '#type' => 'select',
      '#options' => [
        'read' => 'I read something',
        'watch' => 'I watched something',
        'listen' => 'I listened to something',
      ],
      '#required' => TRUE,
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
    $form['finished'] = [
       '#type' => 'checkbox',
       '#title' => 'and I finished it!',
       '#default_value' => $finished_default,
       '#description' => 'If you read/listened, add this title to your offical Summer Game log',
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Score!'),
      '#prefix' => '<div class="sg-form-actions">'
    ];
    $form['cancel'] = [
      '#type' => 'link',
      '#title' => 'Return to Player Page',
      '#url' => \Drupal\Core\Url::fromRoute('summergame.player'),
      '#suffix' => '</div>'
    ];

    // Display Classic Reading Game Log
    $log_rows = [];
    $player_log = summergame_get_player_log($pid);
    foreach ($player_log as $log_row) {
      $delete = ['data' => ['#markup' => '[ <a href="/summergame/player/' . $pid . '/ledger/' .
                $log_row->lid . '/deletescore">X</a> ]']];
      $log_rows[] = [++$row_number, $log_row->description, date('F j', $log_row->timestamp), $delete];
    }

    // Determine Classic Reading Game status
    $completion_gamecode = \Drupal::config('summergame.settings')->get('summergame_completion_gamecode');
    $row = $db->query("SELECT * FROM sg_ledger WHERE pid = :pid AND metadata LIKE :metadata",
                      [':pid' => $pid, ':metadata' => "%gamecode:$completion_gamecode%"])->fetchObject();
    if ($row->lid) {
      $log_text = 'You completed the Classic Reading Game on ' . date('F j, Y', $row->timestamp);
    }
    else if (count($log_rows) >= 10) {
      // Completed, display the completion code
      $log_text = "You've completed the Classic Reading Game! " .
                  '<a href="/summergame/player/' . $pid . '/gamecode?text=' . $completion_gamecode . '">' .
                  "Enter code $completion_gamecode to receive the Badge</a>";
    }
    else {
      $log_text = 'Read/Listen to 10 items and mark them finished to complete the Classic Reading Game!';
    }

    $form['log_listing'] = [
      '#prefix' => "<h2>Summer Game Log</h2><p>$log_text</p>",
      '#type' => 'table',
      '#header' => ['', 'Title', 'Finished Date', ''],
      '#rows' => $log_rows,
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
    $db = \Drupal::database();

    $bnum = $form_state->getValue('bnum');
    $pid = $form_state->getValue('pid');
    $consume_type = $form_state->getValue('consume_type');
    $title = $form_state->getValue('title');
    $finished = $form_state->getValue('finished');

    $points = 0;
    $type = 'Read Watched Listened';
    $metadata = [
      'consume_type' => $consume_type,
    ];

    // check for daily read watch listen bonus
    $res = $db->query("SELECT * FROM sg_ledger WHERE pid=:pid AND type='Read Watched Listened Daily Bonus' ORDER BY lid DESC LIMIT 1", [':pid' => $pid])->fetch();
    if (date('mdY', $res->timestamp) != date('mdY', time())) {
      $type .= ' Daily Bonus';
      $points = 50;
    }

    if ($finished && ($consume_type == 'read' || $consume_type == 'listen')) {
      $metadata['logged'] = 1;
    }

    $points = summergame_player_points($pid, $points, $type, $title, $metadata);
    drupal_set_message("Earned $points points for $title");

    return;
  }

}
