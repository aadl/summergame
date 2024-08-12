<?php

/**
 * @file
 * Contains \Drupal\summergame\Form\SummerGamePlayerConsumeForm.
 */

namespace Drupal\summergame\Form;

use Drupal\Core\Url;
use Drupal\Core\Link;
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
    $user_players = summergame_player_load_all($player['uid']);
    $guzzle = \Drupal::httpClient();
    $api_url = \Drupal::config('arborcat.settings')->get('api_url');
    $db = \Drupal::database();

    // Prepare links to other players if needed
    $other_players_markup = '';
    if (count($user_players) > 1) {
      $other_players_links = ' -- Switch to another player: ';
      foreach ($user_players as $user_player) {
        if ($user_player['pid'] != $player['pid']) {
          // Add to other player list
          $name = $user_player['nickname'] ? $user_player['nickname'] : $user_player['name'];
          $other_players_links .= Link::createFromRoute($name, 'summergame.player.consume', ['pid' => $user_player['pid']])->toString() . ' ';
        }
      }
    }

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
                   ($player['nickname'] ? $player['nickname'] : $player['name']) . "</strong>$other_players_links</p>" .
                   '<p>Earn 50 points for each of the first 10 items that you log! After completion, you can earn a 50 point bonus once per day that you finish something.</p>'
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
    $form['consume_day'] = [
      '#type' => 'select',
      '#options' => [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
      ],
      '#default_value' => 'today',
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
      '#url' => Url::fromRoute('summergame.player'),
      '#suffix' => '</div>'
    ];

    // Display Classic Reading Game Log
    $log_rows = [];
    $row_number = 0;
    $player_log = summergame_get_player_log($pid);
    foreach ($player_log as $log_row) {
      $delete = ['data' => ['#markup' => '[ <a href="/summergame/player/' . $pid . '/ledger/' .
                $log_row->lid . '/deletescore">X</a> ]']];
      $log_rows[] = [++$row_number, $log_row->description, date('F j', $log_row->timestamp), $delete];
    }

    $form['log_count'] = [
      '#type' => 'value',
      '#value' => count($log_rows),
    ];

    // Determine Classic Reading Game status
    if ($completed = summergame_get_classic_status($pid)) {
      $log_text = 'You completed the Classic Reading Game on ' . date('F j, Y', $completed);
    }
    else if (count($log_rows) >= 10) {
      // Completed, display the completion code (shouldn't be displayed with auto submit but here just in case)
      $completion_gamecode = \Drupal::config('summergame.settings')->get('summergame_completion_gamecode');
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
    $player = summergame_player_load($pid);
    $consume_type = $form_state->getValue('consume_type');
    $consume_day = $form_state->getValue('consume_day');
    $title = $form_state->getValue('title');
    $finished = $form_state->getValue('finished');
    $log_count = $form_state->getValue('log_count');

    $points = 0;
    $award_classic_completion = FALSE;
    $type = 'Read Watched Listened';
    $metadata = [
      'consume_type' => $consume_type,
    ];

    // Check for time offset
    if ($consume_day == 'yesterday') {
      $metadata['time_adjust'] = '-86400';
    }

    if ($finished && (($consume_type == 'read' || $consume_type == 'listen') || ($player['agegroup'] == 'adult' && $consume_type == 'watch'))) {
      $metadata['logged'] = 1;
      $log_count++;

      if (!summergame_get_classic_status($pid)) {
        $type .= ' Classic Reading Game';
        $points = 50;

        if ($log_count >= 10) {
          // Not completed yet but has 10 or more logged rows
          $award_classic_completion = TRUE;
        }
      }
    }

    if ($points == 0) {
      // check for daily read watch listen bonus
      $check_date = date('mdY', ($consume_day == 'yesterday' ? time() - 86400 : time()));
      $res = $db->query("SELECT *, DATE_FORMAT(FROM_UNIXTIME(`timestamp`), '%m%d%Y') AS 'date_formatted' " .
                        "FROM sg_ledger " .
                        "WHERE pid=:pid " .
                        "AND type LIKE 'Read Watched Listened%' " .
                        "AND points = 50 " .
                        "HAVING date_formatted=:check_date " .
                        "LIMIT 1", [':pid' => $pid, ':check_date' => $check_date])->fetchObject();
      if (!isset($res->lid)) {
        $type .= ' Daily Bonus';
        $points = 50;
      }
    }

    $points = summergame_player_points($pid, $points, $type, $title, $metadata);
    \Drupal::messenger()->addMessage("Earned $points points for $title");

    // Check for classic reading game completion
    if ($award_classic_completion) {
      $completion_gamecode = \Drupal::config('summergame.settings')->get('summergame_completion_gamecode');
      summergame_redeem_code($player, $completion_gamecode);
    }

    return;
  }

}
