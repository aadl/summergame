<?php /**
 * @file
 * Contains \Drupal\summergame\Controller\DefaultController.
 */

namespace Drupal\summergame\Controller;

use Drupal\Core\Controller\ControllerBase;
use Predis\Client;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    $total = $db->query("SELECT SUM(points) FROM sg_ledger WHERE timestamp > :cutoff AND points > 0", [':cutoff' => $cutoff])->fetchField();
    $player_count = (int) $db->query("SELECT COUNT(DISTINCT pid) FROM sg_ledger WHERE timestamp > :cutoff", [':cutoff' => $cutoff])->fetchField();

    $type = $_GET['type'] ?? \Drupal::config('summergame.settings')->get('summergame_current_game_term');
    $range = $_GET['range'] ?? 'alltime';
    $staff = $_GET['staff'] ?? 0;

    $leaderboard = summergame_get_leaderboard($type, $range, $staff);

    return [
      '#cache' => [
        'max-age' => 0, // Don't cache, always get fresh data
      ],
      '#theme' => 'summergame_leaderboard_page',
      '#total' => $total,
      '#player_count' => $player_count,
      '#game_terms' => summergame_get_game_terms(),
      '#type' => $type,
      '#range' => $range,
      '#staff' => $staff,
      '#sg_admin' => \Drupal::currentUser()->hasPermission('administer summergame'),
      '#leaderboard_timestamp' => $leaderboard['timestamp'],
      '#leaderboard' => $leaderboard['rows'],
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
    $lb_title = '';

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

    $place = 0;
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
  public function homecodes_redirect($game_term = '') {
    return $this->redirect('summergame.map', ['game_term' => $game_term]);
  }

  public function map($game_term = '') {
    // Set default Game Term
    if (empty($game_term)) {
      $game_term = \Drupal::config('summergame.settings')->get('summergame_current_game_term');
    }
    $summergame_points_enabled = \Drupal::config('summergame.settings')->get('summergame_points_enabled');
    $explaination_markup = '<h1>Summer Game Locations</h1>';
    $legend_markup = '';

    if ($summergame_points_enabled) {
      // Temporary Message While Lawn & Library Codes are being developed
      $explaination_markup .= '<p>Welcome to the new Summer Game Map. Updates are coming, stay tuned!</p>';
/*
      $explaination_markup .= <<<EOT
<p>The thought may be DWELLING in your mind, "What's a HOME CODE?" Well that's a great question! A Home Code is your VERY OWN PERSONALIZED code for your HOME!</p>
<p>The way HOME CODES work is simple! You create your code by clicking "My Players". Then scroll down your My Summer Game page until you see "Player Details". You'll see the words, "Create a Home Code", click that and... well... CREATE A HOME CODE! Your Home Code is 100% created BY you FOR other passerby! You can choose whether or not you want your Home Code displayed on the map of ALL THE HOME CODES (A serious, no puns note: No personal information is given on the map. Just the address linked to the code!) Once your Home Code is created, you can MAKE a sign or PRINT a sign to put in your window! WHAT FUN!</p>
<p><strong>MADE A CODE? PLEASE make sure the code is displayed where it can easily be seen from the curb / sidewalk / parking lot / driveway / what have you.</strong></p>
<p><strong>CAN'T FIND A CODE? Just use the Can't Find It link on the pin you can't find below! PLEASE DON'T KNOCK ON ANY DOORS OR TRY TO ASK THE RESIDENT. You might be at the wrong house, or even if you're at the right one, FINDING HOME CODES DOESN'T INVOLVE KNOCKING ON ANY DOORS! Capisce? Let's not make the whole town mad at the Summer Game, ok? Thanks for your help!</strong></p>
EOT;

      // Display current player redemption status while game is running
      if ($player = summergame_get_active_player()) {
        $player_name = ($player['nickname'] ? $player['nickname'] : $player['name']);
        $legend_markup = '<p>Showing redemption status for player <strong>' . $player_name . '</strong>: ' .
          '<img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png"> = Code Redeemed ' .
          '<img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png"> = Code Available ' .
          '</p>';
      }
*/
    }
    else {
      $explaination_markup .= <<<EOT
<p>Summer Game has ended and all Home Codes have expired! If you put up a home code this Summer, please take it down!</p>
<p>This map will show total number of redemptions for each code during the offseason. If you have any questions or concerns, as always, <a href="http://aadl.org/contactus">contact us</a>.</p>
<p>Thanks to everyone who posted or found a home code!</p>
EOT;

      $legend_markup = '<p>Showing number of redemptions during the game.</p>' .
          '<img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png"> = 0-49 ' .
          '<img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png"> = 50-99 ' .
          '<img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-gold.png"> = 100-199 ' .
          '<img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-orange.png"> = 200-299 ' .
          '<img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png"> = 300+ ';
    }

    return [
      '#attached' => [
        'drupalSettings' => [
          'hc_game_term' => $game_term,
          'hc_points_enabled' => $summergame_points_enabled,
        ],
        'library' => [
          'summergame/summergame-map-lib',
        ]
      ],
      '#cache' => [
        'max-age' => 0, // Don't cache, always get fresh data
      ],
      '#markup' => $explaination_markup .
        '<h1>Summer Game Map</h1>' .
        $legend_markup .
        '<div id="mapid" style="height: 180px;"></div>',
    ];
  }

  public function homecodes_markerdata($game_term = '') {
    // Build JSON array of home code marker data
    $response = [];
    $db = \Drupal::database();
    $player = summergame_get_active_player();
    $summergame_points_enabled = \Drupal::config('summergame.settings')->get('summergame_points_enabled');
    if (empty($game_term)) {
      $game_term = \Drupal::config('summergame.settings')->get('summergame_current_game_term');
    }

    // Find all home codes
    $res = $db->query("SELECT * FROM sg_game_codes WHERE game_term = :game_term AND clue LIKE '%\"homecode\"%'",
                      [':game_term' => $game_term]);
    while ($game_code = $res->fetchObject()) {
      $geocode_data = json_decode($game_code->clue);
      if ($geocode_data->display) {
        /*
        if ($summergame_points_enabled) {
          if ($player) {
            // see if player has redeemed this code
            $ledger_row = $db->query("SELECT * FROM sg_ledger WHERE pid = :pid AND metadata LIKE :metadata",
                                     [':pid' => $player['pid'], ':metadata' => 'gamecode:' . $game_code->text])->fetchObject();
            if ($ledger_row) {
              $geocode_data->homecode = 'REDEEMED: ' . $geocode_data->homecode;
              $geocode_data->redeemed = 1;
            }
            else {
              // Not redeemed, add code id to enable "unable to find code" report
              $geocode_data->code_id = $game_code->code_id;
            }
          }
        }
        else {
          // Off-season, remove address and replace with first redepmtion date
          $ledger_row = $db->query("SELECT * FROM sg_ledger WHERE metadata LIKE :metadata ORDER BY timestamp ASC LIMIT 1",
                                   [':metadata' => 'gamecode:' . $game_code->text])->fetchObject();
          if ($ledger_row) {
            $geocode_data->homecode = "Redeemed $game_code->num_redemptions times,<br>starting " . date('F j, Y', $ledger_row->timestamp);
          }
          else {
            $geocode_data->homecode = 'Never redeemed';
          }
        }
*/
        // Add number of redemptions
        $geocode_data->num_redemptions = $game_code->num_redemptions;

        $response[] = $geocode_data;
      }
    }

    return new JsonResponse($response);
  }

  public function map_data($game_term = '') {
    $response = [];
    $db = \Drupal::database();
    $map_points = $db->query("SELECT * FROM sg_map_points WHERE game_term = '$game_term' AND display = 1")->fetchAll();
    foreach ($map_points as $map_point) {
      $response[] = [
        'lat' => $map_point->lat,
        'lon' => $map_point->lon,
        'value' => $map_point->nearby_count,
      ];
    }

    return new JsonResponse($response);
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
      $pdf->AddFont('Helvetica-Bold', '', 'helveticab.php');
      $font_size = 100;
      $pdf->SetFont('Helvetica-Bold', '', $font_size);
      while ($pdf->GetStringWidth($event_code) > $page_width) {
        $font_size -= 5;
        $pdf->SetFont('Helvetica-Bold', '', $font_size);
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
      // drupal_goto($file_path . 'SG_Youth_2017.pdf');
    }
    else { // default to the adult single player form
      $redis->incr('agpdfcounter');
      // drupal_goto($file_path . 'SG_Adult_Teen_2017.pdf');
    }

    return $this->redirect('summergame.admin');
  }

  public function badge_list() {
    $db = \Drupal::database();
    $summergame_settings = \Drupal::config('summergame.settings');
    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());

    // check if pid to fade unearned badges
    $player = summergame_get_active_player();
    $all_players = [];
    if ($player['pid']) {
      $all_players = summergame_player_load_all($player['uid']);
    }
    if (isset($_GET['pid'])) {
      $player_info = summergame_player_load($_GET['pid']);
      if ($player_info['uid'] == $player['uid'] || $user->hasPermission('administer summergame')) {
        $player = $player_info;
      } else {
        return [
          '#cache' => [
            'max-age' => 0, // Don't cache, always get fresh data
          ],
          '#theme' => 'summergame_player_badge_list',
          '#viewing_access' => false
        ];
      }
    }

    $vocab = 'sg_badge_series';
    $badgelist_game_term = $summergame_settings->get('summergame_badgelist_game_term');
    $play_test_term_id = $summergame_settings->get('summergame_play_test_term_id');
    $play_tester = $user->hasPermission('play test summergame');
    $badges = [];

    $query = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', $vocab)
      ->sort('weight');
    $tids = $query->execute();
    $terms = \Drupal\taxonomy\Entity\Term::loadMultiple($tids);

    foreach ($terms as $term) {
      // Check if Play Tester term and not a play tester, skip rest of loop if so
      if ($term->id() == $play_test_term_id && !$play_tester) {
        continue;
      }

      $series_info = explode("\n", strip_tags($term->get('description')->value));
      $series = $term->get('name')->value;

      $query = \Drupal::entityQuery('node')
        ->condition('type', 'sg_badge')
        ->condition('status', 1)
        ->condition('field_badge_game_term', $badgelist_game_term)
        ->condition('field_sg_badge_series_multiple', $term->id())
        ->sort('created' , 'ASC');
      $nodes = $query->execute();
      if (count($nodes)) {
        foreach ($nodes as $nid) {
          $node = \Drupal\node\Entity\Node::load($nid);

          // If badge is in the play tester series and user is not a play tester, skip it
          if (!$play_tester) {
            foreach ($node->field_sg_badge_series_multiple as $badge_series) {
              if ($badge_series->target_id == $play_test_term_id) {
                continue 2;
              }
            }
          }

          $game_term = $node->field_badge_game_term->value;

          // Set Series info if not set yet
          if (!isset($badges[$game_term][$series]['description'])) {
            $badges[$game_term][$series]['description'] = $series_info[0];

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
            $badges[$game_term][$series]['level'] = $level_output;
          }

          if ($player['pid'] &&
              isset($player['bids'][$nid])) {
            $node->badge_earned = true;
          }

          // Hidden Badges
          if ($reveal = $node->field_badge_reveal->value) {
            if ($player['pid']) {
              $required_parts = explode(',', $reveal);
              foreach ($required_parts as $required_part) {
                if (strpos($required_part, 'gamecode:') === 0) {
                  // Required Game Code, search player ledger
                  $ledger = $db->query("SELECT * FROM sg_ledger WHERE pid = :pid AND metadata LIKE :metadata AND game_term = :term LIMIT 1",
                                                     [':pid' => $player['pid'], ':metadata' => $required_part, ':term' => $game_term])->fetch();
                  if (!$ledger->lid) {
                    $node->hide_badge = TRUE;
                    break;
                  }
                }
                else {
                  // Required Badge
                  if (!$player['bids'][$required_part]) {
                    $node->hide_badge = TRUE;
                    break;
                  }
                }
              }
            }
            else {
              $node->hide_badge = TRUE;
            }
          }

          $badges[$game_term][$series]['nodes'][] = $node;
        }
      }
    }

    return [
      '#cache' => [
        'max-age' => 0, // Don't cache, always get fresh data
      ],
      '#theme' => 'summergame_player_badge_list',
      '#player' => $player,
      '#all_players' => $all_players,
      '#viewing_access' => true,
      '#badge_list' => $badges
    ];
  }

}
