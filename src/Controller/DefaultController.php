<?php /**
 * @file
 * Contains \Drupal\summergame\Controller\DefaultController.
 */

namespace Drupal\summergame\Controller;

use Drupal\Core\Controller\ControllerBase;
use Predis\Client;
use setasign\Fpdi;


//use Drupal\Core\Database\Database;
//use Drupal\Core\Url;

/**
 * Default controller for the Summer Game module.
 */
class DefaultController extends ControllerBase {

  public function leaderboard() {
    $db = \Drupal::database();
    $cutoff = strtotime('today');
    $total = $db->query("SELECT SUM(points) FROM sg_ledger WHERE timestamp > :cutoff", [':cutoff' => $cutoff])->fetchField();
    $player_count = (int) $db->query("SELECT COUNT(DISTINCT pid) FROM sg_ledger WHERE timestamp > :cutoff", [':cutoff' => $cutoff])->fetchField();

    $leaderboard = summergame_get_leaderboard();

    return [
      '#cache' => [
        'max-age' => 0, // Don't cache, always get fresh data
      ],
      '#theme' => 'summergame_leaderboard_page',
      '#total' => $total,
      '#player_count' => $player_count,
      '#leaderboard_date' => date('l, F j, Y', strtotime('yesterday')),
      '#leaderboard' => $leaderboard,
    ];
  }

  public function leaderboard_old() {
    $summergame_settings = \Drupal::config('summergame.settings');

    $type = $_GET['type'];
    $range = $_GET['range'];
    $staff = $_GET['staff'];

    $rows = (int) $_GET['rows'] ? (int) $_GET['rows'] : 15;

    //drupal_add_css(drupal_get_path('module', 'summergame') . '/summergame.css');
    $args = [];

    // Switch for staff
    $staff_rid = $summergame_settings->get('summergame_staff_role_id');

    if ($staff_rid) {
      if ($staff) {
        $lb_title .= 'Staff ';
        $staff_query = " AND {user__roles}.roles_target_id = '$staff_rid' ";
      }
      else {
        $staff_query = ' AND {user__roles}.roles_target_id IS NULL ';
      }
    }

    // Determine type
    $terms = summergame_get_game_terms();
    $game_term = $summergame_settings->get('summergame_current_game_term');
    $type = (in_array($type, $terms) ? $type : $game_term);
    if ($type) {
      $type_query = 'AND {sg_ledger}.game_term LIKE :game_term ';
      $args[':game_term'] = $type;
      $lb_title .= $type;
    }
    else {
      $type_query = '';
      $lb_title .= 'Career';
    }

    $lb_title .= ' Leaderboard';

    // Determine range
    if ($range == 'day') {
      $range_query = 'AND {sg_ledger}.timestamp > ' . (time() - (60 * 60 * 24)) . ' ';
      $lb_title .= ' for Today (Last 24 hours)';
    }
    else if ($range == 'week') {
      $range_query = 'AND {sg_ledger}.timestamp > ' . (time() - (60 * 60 * 24 * 7)) . ' ';
      $lb_title .= ' for This Week (Last 7 Days)';
    }
    else {
      $range_query = '';
      $lb_title .= ' for All Time';
    }

    $leaderboard = array();

    $db = \Drupal::database();

    $res = $db->query('SELECT {sg_players}.pid, SUM(points) AS lb_total ' .
                      'FROM {sg_ledger}, {sg_players} ' .
                      "LEFT JOIN {user__roles} ON {sg_players}.uid = {user__roles}.entity_id AND {user__roles}.roles_target_id = '$staff_rid' " .
                      'WHERE {sg_players}.pid = {sg_ledger}.pid ' .
                      "AND {sg_ledger}.metadata NOT LIKE '%leaderboard:no%' " .
                      $type_query .
                      $range_query .
                      $staff_query .
                      'GROUP BY {sg_players}.pid ' .
                      'ORDER BY lb_total DESC ' .
                      'LIMIT ' . $rows, $args);

    while ($row = $res->fetchAssoc()) {
      $lb_player = summergame_player_load($row['pid']);
      if ($lb_player['show_leaderboard']) {
        $player_name = $lb_player['nickname'] ? $lb_player['nickname'] : $lb_player['name'];
      }
      else {
        $player_name = 'Player #' . $lb_player['pid'];
      }
      if ($lb_player['show_myscore'] || \Drupal::currentUser()->hasPermission('administer summergame')) {
        //$player_name = l($player_name, 'summergame/player/' . $lb_player['pid']);
      }
      $leaderboard[] = array(
        'Place' => ++$place,
        'Player' => $player_name,
        'Total Score' => array('data' => $row['lb_total'], 'class' => 'digits'),
      );
    }

    $render = [
      '#markup' => "<h1>$lb_title</h1>"
    ];
    $render[] = [
      '#type' => 'table',
      '#header' => array_keys($leaderboard[0]),
      '#rows' => $leaderboard,
      '#empty' => "No scores found"
    ];

    return $render;
  }
/*
  public function badge() {
    // Redirect to the right domain
    if ($sg_did = variable_get('summergame_default_domain_id', FALSE)) {
      $summergame_domain = domain_load($sg_did);
      domain_goto($summergame_domain);
    }

    $badge = db_fetch_object(db_query("SELECT * FROM sg_badges WHERE bid = %d", $bid));

    if ($badge->bid) {
      global $user;
      drupal_add_css(drupal_get_path('module', 'summergame') . '/summergame.css');

      if ($user->player['pid']) {
        $player_badge_status .= '<p class="player-badge-status">';
        $earned = db_fetch_array(db_query("SELECT * FROM sg_players_badges WHERE pid = %d AND bid = %d", $user->player['pid'], $badge->bid));
        if ($earned['timestamp']) {
          $player_badge_status .= 'You received this badge on ' . date('F j, Y g:i A', $earned['timestamp']);

          $share_links .= '<div class="share-links">';

          $share_links .= '<div>Share your accomplishment:</div>';

          $share_links .= '<div class="twitter-share">';
          $share_links .= '<script type="text/javascript" src="http://platform.twitter.com/widgets.js"></script>';
          $share_links .= '<a href="http://twitter.com/share/?url=/" class="twitter-share-button" data-text="I earned the ' .
                                  $badge->title . ' Badge in the @aadl #summergame! ' . url($_GET['q'], array('absolute' => TRUE)) .
                                  ' Play along at http://play.aadl.org/." data-count="none">Tweet</a>';
          $share_links .= '</div>';

          $share_links .= '<div class="facebook-share">';
          $share_links .= <<<FBL
<div id="fb-root"></div>
<script>(function(d, s, id) {
var js, fjs = d.getElementsByTagName(s)[0];
if (d.getElementById(id)) return;
js = d.createElement(s); js.id = id;
js.src = "//connect.facebook.net/en_US/all.js#xfbml=1";
fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>
<fb:like send="false" layout="standard" width="225" show_faces="true" colorscheme="light" action="like"></fb:like>
FBL;
          $share_links .= '</div>';

          $share_links .= '</div>';

          $player_badge_status .= $share_links;
        }
        else {
          $player_badge_status .= 'You have not yet earned this badge';
        }
        $player_badge_status .= '</p>';
      }

      // Handle hidden badges
      $hide_badge = FALSE;
      if ($badge->reveal) {
        if (count($user->player['bids'])) {
          $hidden_badge_titles = array();

          $required_bids = explode(',', $badge->reveal);
          foreach ($required_bids as $required_bid) {
            $required_badge = db_fetch_object(db_query("SELECT * FROM sg_badges WHERE bid = %d", $required_bid));
            $hidden_badge_titles[] = $required_badge->title;
            if (!$user->player['bids'][$required_bid]) {
              $hide_badge = TRUE;
              break;
            }
          }
          $hidden_details .= 'You must earn the following badge' .
                             (count($required_bids) == 1 ? '' : 's') .
                             ' to reveal this badge: ';
          $hidden_details .= implode(', ', $hidden_badge_titles);
        }
        else {
          // no player or player has no badges
          $hide_badge = TRUE;
        }

        if ($hide_badge) {
          $badge->image = 'hidden';
          $badge->title = 'Hidden Badge';
          $badge->description = $hidden_details;
        }
      }

      $badge_img_src = file_directory_path() . '/sg_images/' . $badge->image;
      if (file_exists($badge_img_src . '_400.png')) {
        $badge_img_src .= '_400.png';
      }
      else {
        $badge_img_src .= '_100.png';
      }
      $content .= '<div class="summergame-badge-detail">';
      $content .= '<div style="float: right; font-size: 0.75em;">[ <a href="javascript:location.reload(true)">refresh</a> ]</div>';
      $content .= l('<img src="' . base_path() . $badge_img_src . '">',
                    file_create_url($badge_img_src),
                    array('html' => TRUE));
      $content .= "<h1>$badge->title</h1>";
      $content .= "<p class=\"caption\">Difficulty: ";
      $content .= "<span class=\"badge-diff\">";
      $max_level = 4;
      $cur_level = intval($badge->level);
      $level_leftover = $max_level - $cur_level;
      for ($i = 0; $i < $cur_level; $i++) {
        $content .= "?";
      }
      $content .= "<span class=\"badge-diff-faded\">";
      for ($i = 0; $i < $level_leftover; $i++) {
        $content .= "?";
      }
      $content .= "</span></span>";
      if ($badge->type) {
        $content .= "<br>$badge->type Badge";
      }
      if ($badge->game_term) {
        $content .= "<br>Part of the $badge->game_term Game";
      }
      $content .= "</p>";
      $content .= "<p><strong>$badge->description</strong></p>";
      if ($badge->points) {
        $term = ($badge->game_term_override ? $badge->game_term_override : $badge->game_term);
        $points = ($badge->points_override ? $badge->points_override : $badge->points);
        $content .= "<p><em>$points $term point bonus when earned</em></p>";
      }

      $awarded = db_fetch_object(db_query("SELECT COUNT(pid) AS pcount FROM sg_players_badges WHERE bid = %d", $bid));
      $content .= "<p>This badge has been awarded to $awarded->pcount players</p>";

      if (!$hide_badge) {
        $player_count = 0;
        if (preg_match('/^{([\d,]+)}$/', $badge->formula, $matches)) {
          // Badge collection badge
          $formula_type = ' badges earned';
          $bids = explode(',', $matches[1]);
          $total_count = count($bids);
          foreach ($bids as $bid) {
            $badge_list .= theme_summergame_badge($bid);
          }
          $badge_count = db_fetch_object(db_query("SELECT COUNT(bid) AS bid_count FROM sg_players_badges WHERE pid = %d AND bid IN (%s)",
                                                  $user->player['pid'], $matches[1]));
          $player_count = $badge_count->bid_count;
        }
        else if (strpos($badge->formula, '::') !== FALSE) {
          // Multiple Badge
          list($total_count, $text_pattern) = explode('::', $badge->formula);
          $lid_count = db_fetch_object(db_query("SELECT COUNT(lid) AS lid_count FROM sg_ledger WHERE pid = %d AND (type LIKE '%s' OR metadata LIKE 'gamecode:%s') AND game_term = '%s'",
                                                  $user->player['pid'], $text_pattern, $text_pattern, $badge->game_term));
          $player_count = $lid_count->lid_count;
          $formula_type = $text_pattern . " scores in your ledger";
        }
        else {
          // Collection Badge
          $formula_type = ' criteria';
          $codes = explode(',', $badge->formula);
          $total_count = count($codes);
          $player_matches = array();
          $gc_rows = array();

          foreach ($codes as $code_id => $text_pattern) {
            $text_patterns = explode('|', $text_pattern);
            if (count($text_patterns) > 1) {
              $gc_rows[] = array('Game Code' => '<strong>One of the following:</strong>',
                                 'Description' => '',
                                 'Earned On' => '');
              $any_mode = TRUE;
            }
            else {
              $any_mode = FALSE;
            }

            foreach ($text_patterns as $pattern) {
              $gc_row = array('Game Code' => '',
                              'Description' => '',
                              'Earned On' => '');

              // is it a game code?
              $gc = db_fetch_object(db_query("SELECT * FROM sg_game_codes WHERE text = '%s'", $pattern));
              if ($gc->code_id) {
                $formula_type = ' game codes found';
                $gc_row['Game Code'] = '???????';
                if ($hint = $gc->hint) {
                  $hint = str_replace('\'', '\x27', str_replace('"', '\x22', $hint));
                  $gc_row['Description'] = "<a onclick=\"this.innerHTML = 'HINT: $hint';\">(click for hint)</a>";
                }
              }
              else {
                // not a game code
                $gc_row['Game Code'] = $pattern;
              }

              $ledger = db_fetch_object(db_query("SELECT * FROM sg_ledger WHERE pid = %d AND (type LIKE '%s' OR metadata LIKE 'gamecode:%s') AND game_term = '%s' LIMIT 1",
                                                 $user->player['pid'], $pattern, $pattern, $badge->game_term));
              if ($ledger->lid) {
                if (!$player_matches[$code_id]) {
                  $player_count++;
                  $player_matches[$code_id] = TRUE;
                }
                if ($gc->code_id) {
                  $gc_row['Game Code'] = $gc->text;
                  $gc_row['Description'] = $gc->description;
                }
                $gc_row['Earned On'] = date('F j, Y, g:i a', $ledger->timestamp);
              }

              if ($any_mode) {
                $gc_row['Game Code'] = '-- ' . $gc_row['Game Code'];
              }

              $gc_rows[] = $gc_row;
            }
          }
        }


        if ($user->player['pid']) {
          // Show progress bar
          if ($player_count) {
            $percentage = min(round(($player_count / $total_count) * 100), 100);
          }
          else {
            $percentage = 0;
          }
          $content .= "<h2>Your Player Progress: $percentage% ($player_count / $total_count $formula_type)</h2>";
          $content .= '<div class="meter">';
          if ($percentage == 0) {
            $percentage = 1;
          }
          $content .= "<span style=\"width: $percentage%\"></span>";
          $content .= '</div>'; // div.meter
          $content .= $player_badge_status;
        }

        if (count($gc_rows)) {
          $content .= theme('table', array_keys($gc_rows[0]), $gc_rows);
        }

        if ($badge_list) {
          $content .= '<div class="badge-collection-list">';
          $content .= '<h2>Required Badges:</h2>';
          $content .= '<div id="summergame-badges-page">';
          $content .= $badge_list;
          $content .= '</div>';
          $content .= '</div>';
        }
      }
      $content .= '</div>';
    }
    else {
      $content .= '<p>Sorry, no badge found with that ID.</p>';
    }

    return $content;
  }
*/
  public function pdf($type = 'adult', $code_id = 0) {
    $file_path = drupal_get_path('module', 'summergame') . '/pdf/';
    $redis = new Client(\Drupal::config('summergame.settings')->get('summergame_redis_conn'));

    if ($type == 'gamecode') {
      $code_id = (int) $code_id;
      $db = \Drupal::database();
      $gamecode = $db->query("SELECT * FROM sg_game_codes WHERE code_id = $code_id")->fetchObject();

      $event_code = strtoupper($gamecode->text); // Code for the event, Need to be in all CAPS
      $event_points = $gamecode->points . ' POINTS'; // Points for the event
      $description = $gamecode->description; // Description of Code
      $description = array_reverse(explode("\n", wordwrap($description, 100)));

      $code_link = \Drupal\Core\Url::fromRoute('summergame.player.gamecode',
                                               ['pid' => 0, 'text' => $event_code],
                                               ['absolute' => TRUE])->toString();

      $qrcode = 'http://qrickit.com/api/qr?d=' . //'http://api.qrserver.com/v1/create-qr-code/?data=' .
                urlencode($code_link);

      // initiate FPDI
      $pdf = new Fpdi\Fpdi('L', 'mm', 'Letter');
      $pdf->SetAutoPageBreak(FALSE);
      $pdf->AddPage();

      // set the sourcefile
      $pdf->setSourceFile($file_path . 'code_template.pdf');
      $tplidx = $pdf->importPage(1);
      $pdf->useTemplate($tplidx);

      $lrMargin = 20;
      $tMargin = 17;
      $pdf->SetMargins($lrMargin, $tMargin);
      $page_width = $pdf->GetPageWidth() - $lrMargin - $lrMargin;

      // now write some text
      $pdf->AddFont('Quicksand-Bold', '', 'Quicksand-Bold.php');
      $font_size = 100;
      $pdf->SetFont('Quicksand-Bold', '', $font_size);
      while ($pdf->GetStringWidth($event_code) > $page_width) {
        $font_size -= 5;
        $pdf->SetFont('Quicksand-Bold', '', $font_size);
      }
      $pdf->SetXY($lrMargin, 50);
      $pdf->Cell(0, 10, $event_code, 0, 1, 'C');

      //$pdf->SetFont('Quicksand-Bold', '', 95);
      $pdf->SetXY($lrMargin, 125);
      $pdf->Cell(0, 10, $event_points, 0, 1, 'C');

    /*
      // Description
      $desc_Y = 192;
      $pdf->SetFont('Quicksand-Bold', '', 13);
      foreach ($description as $desc_line) {
        $pdf->SetXY($pdf->lMargin, $desc_Y);
        $pdf->Cell($page_width - 35, 7, $desc_line, 0);
        $desc_Y -= 7;
      }
    */

      // add the QR Code
      $pdf->SetXY(-50, -50);
      $pdf->Image($qrcode, NULL, NULL, 30, 30, 'PNG');

      $pdf->Output($event_code . '_code.pdf', 'D');
    }
    else if ($type == 'youth') {
      $redis->incr('ygpdfcounter');
      drupal_goto($file_path . 'SG_Youth_2017.pdf');
    }
    else { // default to the adult single player form
      $redis->incr('agpdfcounter');
      drupal_goto($file_path . 'SG_Adult_Teen_2017.pdf');
    }

    return $this->redirect('summergame.admin');
  }

  public function badge_list() {
    // check if pid to fade unearned badges
    $db = \Drupal::database();
    $player = summergame_get_active_player();

    $vocabs = ['Summer_Game_2018'];
    $badges = [];
    foreach ($vocabs as $vocab) {
      $query = \Drupal::entityQuery('taxonomy_term')
        ->condition('vid', $vocab)
        ->sort('weight');
      $tids = $query->execute();
      $terms = \Drupal\taxonomy\Entity\Term::loadMultiple($tids);

      foreach ($terms as $term) {
        $series_info = explode("\n", strip_tags($term->get('description')->value));
        $series = $term->get('name')->value;
        $query = \Drupal::entityQuery('node')
          ->condition('type', 'sg_badge')
          ->condition('status', 1)
          ->condition('field_sg_2018_badge_series', $term->id());
        $nodes = $query->execute();
        if (count($nodes)) {
          $badges[$vocab][$series]['description'] = $series_info[0];
          $series_level = (int) $series_info[2];
          $max_level = 4;
          $level_diff = $max_level - $series_level;
          $level_output = '';
          for ($i = 0; $i < $series_level; $i++) {
            $level_output .= '&starf;';
          }
          for ($i = 0; $i < $level_diff; $i++) {
            $level_output .= '&star;';
          }
          $badges[$vocab][$series]['level'] = $level_output;
          foreach ($nodes as $nid) {
            $node = entity_load('node', $nid);
            if ($player['pid'] &&
                in_array($nid, $player['bids'])) {
              $node->badge_earned = true;
            }
            $badges[$vocab][$series]['nodes'][] = $node;
          }
        }
      }
    }

    return [
      '#cache' => [
        'max-age' => 0, // Don't cache, always get fresh data
      ],
      '#theme' => 'summergame_player_badge_list',
      '#badge_list' => $badges
    ];
  }

}
