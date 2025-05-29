<?php /**
 * @file
 * Contains \Drupal\summergame\Controller\DefaultController.
 */

namespace Drupal\summergame\Controller;

use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;
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
    $total = $db->query("SELECT SUM(points) FROM sg_ledger WHERE timestamp > :cutoff " .
                        "AND metadata NOT LIKE '%leaderboard:no%' AND points > 0", [':cutoff' => $cutoff])->fetchField();
    $player_count = (int) $db->query("SELECT COUNT(DISTINCT pid) FROM sg_ledger WHERE timestamp > :cutoff " .
                                     "AND metadata NOT LIKE '%leaderboard:no%'", [':cutoff' => $cutoff])->fetchField();

    $current_game_term = \Drupal::config('summergame.settings')->get('summergame_current_game_term');
    $type = $_GET['type'] ?? $current_game_term;
    $range = $_GET['range'] ?? 'alltime';
    $staff = $_GET['staff'] ?? 0;

    $leaderboard = summergame_get_leaderboard($type, $range, $staff);

    if (\Drupal::config('summergame.settings')->get('summergame_suppress_current_leaderboard') &&
        $type == $current_game_term) {
      $leaderboard['rows'] = [];
    }

    return [
      '#attached' => [
        'library' => [
          'summergame/summergame-lib'
        ]
      ],
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

  /**
   * Wrapper for geocode API call
   */
  public function geocode($address = '') {
    $guzzle = \Drupal::httpClient();
    $geocode_url =  \Drupal::config('summergame.settings')->get('summergame_homecode_geocode_url');
    $response_body = [];

    $query = [
      'address' =>  $address,
      'key' => \Drupal::config('summergame.settings')->get('summergame_homecode_geocode_api_key'),
    ];

    try {
      $response = $guzzle->request('GET', $geocode_url, ['query' => $query]);
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError('Unable to lookup address');
    }

    if ($response) {
      $response_body = json_decode($response->getBody()->getContents());
    }

    return new JsonResponse($response_body);
  }

  public function map($game_term = '') {
    // Set default Game Term
    if (empty($game_term)) {
      $game_term = \Drupal::config('summergame.settings')->get('summergame_current_game_term');
    }
    $sg_admin = \Drupal::currentUser()->hasPermission('administer summergame');
    $summergame_points_enabled = \Drupal::config('summergame.settings')->get('summergame_points_enabled');
    $summergame_homecode_report_threshold = \Drupal::config('summergame.settings')->get('summergame_homecode_report_threshold');
    $explaination_markup = '<h1>Summer Game Locations</h1>';
    // $heatRadius = ($_GET['heatRadius'] ?? 0.001);

    $legend_markup = '';
/*
    if ($sg_admin) {
      $legend_markup = '<p>Heatmap Radius: <span id="heatRadius">' . $heatRadius . '</span></p>';
    }
*/
    if ($summergame_points_enabled) {
/*
      // Lawn & Library Codes Creation Explaination
      $explaination_markup .= '<p>Would you love to create your VERY OWN Summer Game Code??? YOU CAN with LAWN & LIBRARY CODES starting JUNE 21st!!!</p>' .
                              '<p>FIRST, stop by any of our AADL locations to pick up either a (new and improved) Lawn Code Sign OR a Library Code Card. THEN create your code by clicking "My Players." Scroll down to your My Summer Game page until you see "Player Details." You\'ll see the words, "Create Your Lawn Code or Library Code," click it and fill out the form to make your code real and active! Write the code legibly in ALLCAPS on your lawn sign or code card and get it out there for fellow players to find!!! If you make a Lawn Code, you can decide if you want a pin for it to be displayed on the Summer Game Map! (Serious note: No personal information is given on the map. Just the address linked to the code!). If you make a Library Code Card, you get to choose which Summer Game Stop post you want to attach it to (we have one at each of our locations)!!</p>';
*/
      // Creation unavailable Explaination
      $explaination_markup .= '<p>Lawn and Library Code creation for Summer Game 2024 ended at 11:59pm on August 11th, but the good news is that leaves you with plenty of time to find some codes out in the wild before Summer Game ends at 11:59pm on August 25th!!</p>';

      $explaination_markup .= '<p>DID YOU MAKE A LAWN CODE? Please make sure the code is displayed on YOUR lawn (or one you have permission to use) near a sidewalk, so that players aren\'t searching high and low or out in traffic. Thank you!!</p>' .
                              '<p>CAN\'T FIND A CODE? Use the "Can\'t find it?" link to report a missing Lawn Code! PLEASE DON\'T KNOCK ON ANY DOORS OR TRY TO ASK THE RESIDENT. Just use the tool!! Keep it cool!! The Summer Game doesn\'t involve knocking on peoples\' doors! EVER!!!!</p>' .
                              '<p>WANT TO CLEAR UP YOUR MAP? We know there is nothing more mildly inconvenient than trying to redeem a code you redeemed two weeks ago, so you\'ll notice we\'ve added a nifty new feature that allows you to hide Lawn Code pins from your map based on how long ago they were created! Just check off some of those boxes in the SG Map\'s key to only view the newest, oldest, and/or middle-aged-est Lawn Codes!</p>';
/*
      // Temporary Message While Lawn & Library Codes are not yet available
      $explaination_markup .= '<p>Welcome to the Summer Game Map. Find out where to find Summer Game codes and more!</p>' .
                              '<p>LAWN & LIBRARY CODES are returning in a few weeks! Check back then to see details.</p>' .
                              '<p><img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png"> = Library building locations, find codes around the building.</p>' .
                              '<p>Badge images indicate starting locations for earning those badges. Click the badge name to open the badge details page.</p>';
                              // "<p>Heatmap colors indicate approximate locations of the Lawn Code signs around town. They should all be visible from the street or sidewalk.</p>";
*/
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
<p>Summer Game has ended and all Lawn Codes and Library Codes have expired! If you put up a Lawn Code sign, please take it down but PLEASE KEEP IT! Don't toss those Summer Game Lawn signs!</p>
<p>If you have any questions or concerns, as always, <a href="http://aadl.org/contactus">contact us</a>.</p>
<p>Thanks to everyone who posted or found Lawn and Library codes!</p>
EOT;
/*
We don't have all the details yet, but we'll reuse the signs for the 2023 game, so store them until next June, or return them to your nearest Library and we'll find a good use for them in 2023.
      $legend_markup = '<p>Showing number of redemptions during the game.</p>' .
          '<img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png"> = 0-49 ' .
          '<img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png"> = 50-99 ' .
          '<img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-gold.png"> = 100-199 ' .
          '<img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-orange.png"> = 200-299 ' .
          '<img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png"> = 300+ ';
 */
    }

    return [
      '#attached' => [
        'drupalSettings' => [
          'hc_game_term' => $game_term,
          'hc_points_enabled' => $summergame_points_enabled,
          'hc_report_threshold' => $summergame_homecode_report_threshold,
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
        '<div id="map-wrapper">' .
        '<div id="mapid" style="height: 180px;"></div>' .
/*
        '<div class="legend-area"><h4>Nearby Lawn Codes</h4>' .
        '<span id="min">Fewer</span><span id="max">More</span>' .
        '<img id="gradient" src="" style="width:100%" />' .
        '</div>' .
*/
        '</div>',
    ];
  }

  public function map_data($game_term = '') {
    $summergame_points_enabled = \Drupal::config('summergame.settings')->get('summergame_points_enabled');

    if ($summergame_points_enabled) {
      $db = \Drupal::database();
      $sg_admin = \Drupal::currentUser()->hasPermission('administer summergame');
      if (empty($game_term)) {
        $game_term = \Drupal::config('summergame.settings')->get('summergame_current_game_term');
      }

      /*
      // Heatmap Data
      $heatmap = [];
      $min = $db->query("SELECT MIN(nearby_count) FROM sg_map_points WHERE game_term = '$game_term' AND display = 1")->fetchField();
      $heatmap['min'] = $min;
      $max = $db->query("SELECT MAX(nearby_count) FROM sg_map_points WHERE game_term = '$game_term' AND display = 1")->fetchField();
      $heatmap['max'] = $max;
      $map_points = $db->query("SELECT * FROM sg_map_points WHERE game_term = '$game_term' AND display = 1")->fetchAll();
      foreach ($map_points as $map_point) {
        $heatmap['data'][] = [
          'lat' => $map_point->lat,
          'lon' => $map_point->lon,
          'count' => $map_point->nearby_count,
        ];
      }
      */

      // Homecodes Data
      $homecodes = [];
      $res = $db->query("SELECT * FROM sg_game_codes WHERE game_term = :game_term AND clue LIKE '%\"homecode\"%'",
                        [':game_term' => $game_term]);
      while ($game_code = $res->fetchObject()) {
        $geocode_data = json_decode($game_code->clue);
        if ($geocode_data->display) {
          // Add game code data to geocode data
          $geocode_data->code_id = $game_code->code_id;
          $geocode_data->created = $game_code->created;
          $geocode_data->num_redemptions = $game_code->num_redemptions;

          // Add admin data
          if ($sg_admin) {
            $geocode_data->text = $game_code->text;
            $geocode_data->creator_uid = $game_code->creator_uid;
          }

          // Determine layerGroup depending on age
          $ts_diff = time() - $game_code->created;
          $days_old = floor($ts_diff / (60 * 60 * 24));

          if ($days_old < 3) {
            $geocode_data->layerGroup = 'A';
          }
          else if ($days_old < 7) {
            $geocode_data->layerGroup = 'B';
          }
          else if ($days_old < 14) {
            $geocode_data->layerGroup = 'C';
          }
          else if ($days_old < 21) {
            $geocode_data->layerGroup = 'D';
          }
          else {
            $geocode_data->layerGroup = 'E';
          }

          $homecodes[] = $geocode_data;
        }
      }

      // Bizcodes Data
      $bizcodes = [];
      $res = $db->query("SELECT * FROM sg_game_codes WHERE game_term = :game_term AND clue LIKE '%\"bizcode\"%'",
                        [':game_term' => $game_term]);
      while ($game_code = $res->fetchObject()) {
        $geocode_data = json_decode($game_code->clue);

        // Add game code data to geocode data
        $geocode_data->code_id = $game_code->code_id;
        $geocode_data->created = $game_code->created;
        $geocode_data->num_redemptions = $game_code->num_redemptions;

        $bizcodes[] = $geocode_data;
      }

      // Badges Data
      $badges = [];
      $play_test_term_id = \Drupal::config('summergame.settings')->get('summergame_play_test_term_id');
      $nids = \Drupal::entityQuery('node')
              ->accessCheck(FALSE)
              ->condition('type', 'sg_badge')
              ->condition('field_badge_game_term', $game_term)
              ->condition('status', 1)
              ->exists('field_badge_coordinates')
              ->sort('nid', 'DESC')
              ->execute();

      foreach ($nids as $nid) {
        $badge = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

        // Remove play test badges from display
        foreach ($badge->field_sg_badge_series_multiple as $badge_series) {
          if ($badge_series->target_id == $play_test_term_id) {
            continue 2;
          }
        }

        $image_url = '/files/badge-derivs/100/' . $badge->field_badge_image->entity->getFilename();
        list($lat, $lon) = explode(',', $badge->field_badge_coordinates->value);
        $badges[] = [
          'popup' => 'Badge Start Point<br>' . $badge->toLink()->toString(),
          'lat' => trim($lat),
          'lon' => trim($lon),
          'image' => $image_url,
        ];
      }
    }
    return new JsonResponse(['homecodes' => $homecodes, 'bizcodes' => $bizcodes, 'badges' => $badges]);
  }

  public function pdf($type = 'adult', $code_id = 0) {
    $file_path = \Drupal::service('extension.list.module')->getPath('summergame') . '/pdf/';
    $redis = new Client(\Drupal::config('summergame.settings')->get('summergame_redis_conn'));

    if ($type == 'gamecode') {
      $code_id = (int) $code_id;
      $db = \Drupal::database();
      $gamecode = $db->query("SELECT * FROM sg_game_codes WHERE code_id = $code_id")->fetchObject();

      $event_code = strtoupper($gamecode->text); // Code for the event, Need to be in all CAPS
      $event_points = $gamecode->points . ' POINTS'; // Points for the event
      $description = $gamecode->description; // Description of Code
      $description = array_reverse(explode("\n", wordwrap($description, 100)));

      $code_link = Url::fromRoute('summergame.player.gamecode',
                                  ['pid' => 0, 'text' => $event_code],
                                  ['absolute' => TRUE])->toString();

      $qrcode = Url::fromRoute('aadl_content_management.qr_code_image', [],
                               ['query' => ['data' => $code_link], 'absolute' => TRUE])->toString();

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

      // Display Sequence Number
      if ($gamecode->sequence_num) {
        $sequence_text = 'Code #' . $gamecode->sequence_num;
        if ($gamecode->sequence_total) {
          $sequence_text .= ' of ' . $gamecode->sequence_total;
        }
        $pdf->SetFont('Helvetica-Bold', '', 20);
        $pdf->SetXY($lrMargin, 192);
        $pdf->Cell($page_width - 35, 7, $sequence_text, 0);
      }

      // add the QR Code if Summer Game code
      if (strpos($gamecode->game_term, 'SummerGame') === 0) {
        $pdf->SetXY(-50, -50);
        $pdf->Image($qrcode, NULL, NULL, 30, 30, 'PNG');
      }

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

  public function badge_list($game_term = '') {
    $db = \Drupal::database();
    $summergame_settings = \Drupal::config('summergame.settings');
    $user = User::load(\Drupal::currentUser()->id());

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
    $badgelist_game_term = ($game_term ? $game_term : $summergame_settings->get('summergame_badgelist_game_term'));
    $play_test_term_id = $summergame_settings->get('summergame_play_test_term_id');
    $play_tester = $user->hasPermission('play test summergame');
    $badges = [];
    $list_tags = [];

    $query = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', $vocab)
      ->sort('weight');
    $tids = $query->accessCheck(TRUE)->execute();
    $terms = Term::loadMultiple($tids);

    foreach ($terms as $term) {
      $term_id = $term->id();

      // Check if Play Tester term and not a play tester, skip rest of loop if so
      if ($term_id == $play_test_term_id && !$play_tester) {
        continue;
      }

      $query = \Drupal::entityQuery('node')
        ->condition('type', 'sg_badge')
        ->condition('status', 1)
        ->condition('field_badge_game_term', $badgelist_game_term)
        ->condition('field_sg_badge_series_multiple', $term->id())
        ->sort('created' , 'ASC');
      $nodes = $query->accessCheck(TRUE)->execute();
      if (count($nodes)) {
        foreach ($nodes as $nid) {
          $node = Node::load($nid);

          // If badge is in the play tester series and user is not a play tester, skip it
          if (!$play_tester) {
            foreach ($node->field_sg_badge_series_multiple as $badge_series) {
              if ($badge_series->target_id == $play_test_term_id) {
                continue 2;
              }
            }
          }

          // Set Series info if not set yet
          if (!isset($badges[$term_id])) {
            $series_info = '';
            $series_level = 1; // default to series level 1

            if (isset($term->get('description')->value)) {
              // parse description and level from term description
              preg_match_all('/<p\b[^>]*>(.*?)<\/p>/is', $term->get('description')->value, $matches);
              if (isset($matches[1][0])) {
                $series_info = trim($matches[1][0]);
              }
              if (isset($matches[1][1])) {
                $series_level = (int) trim($matches[1][1]);
              }
            }

            switch ($series_level) {
              case 2:
                $level_output = "⭐️⭐️ Tricky";
                break;
              case 3:
                $level_output = "⭐️⭐️⭐️ Super Tricky";
                break;
              case 4:
                $level_output = "⭐️⭐️⭐️⭐️ Extremely Tricky";
                break;
              default:
                $level_output = "⭐️ Standard";
            }

            $badges[$term_id] = [
              'name' => $term->get('name')->value,
              'description' => $series_info,
              'level' => $level_output,
              'tags' => [],
              'diff_class' => 'diff' . $series_level,
              'classes' => ['diff' . $series_level],
            ];
          }

          if ($player['pid'] &&
              isset($player['bids'][$nid])) {
            $node->badge_earned = true;
          }

          // Add difficulty to node classes
          $node->classes = [$badges[$term_id]['diff_class']];

          // Update any badge tags on term and add to node
          if (isset($node->field_badge_tags)) {
            foreach ($node->field_badge_tags->referencedEntities() as $ref) {
              $tid = $ref->get('tid')->value;
              $badges[$term_id]['tags'][$tid] = $ref->get('name')->value;
              $badges[$term_id]['classes'][] = 'tag' . $tid;
              $node->classes[] = 'tag' . $tid;
              $list_tags[$tid] = [
                'name' => $ref->get('name')->value,
                'description' => $ref->get('description')->value,
              ];
            }
          }

          // Hidden Badges
          if ($reveal = $node->field_badge_reveal->value) {
            if ($player['pid']) {
              $required_parts = explode(',', $reveal);
              foreach ($required_parts as $required_part) {
                if (strpos($required_part, 'gamecode:') === 0) {
                  // Required Game Code, search player ledger
                  $game_term = $node->field_badge_game_term->value;
                  $ledger = $db->query("SELECT * FROM sg_ledger WHERE pid = :pid AND metadata LIKE :metadata AND game_term = :term LIMIT 1",
                                                     [':pid' => $player['pid'], ':metadata' => $required_part, ':term' => $game_term])->fetch();
                  if (!$ledger->lid) {
                    $node->hide_badge = TRUE;
                    break;
                  }
                }
                else {
                  // Required Badge
                  if (!isset($player['bids'][$required_part])) {
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

          $badges[$term_id]['nodes'][] = $node;
        }
      }
    }

    return [
      '#attached' => [
        'library' => [
          'summergame/summergame-badgelist-lib',
          'summergame/summergame-byteclub-lib'
        ],
      ],
      '#cache' => [
        'max-age' => 0, // Don't cache, always get fresh data
      ],
      '#theme' => 'summergame_player_badge_list',
      '#player' => $player,
      '#all_players' => $all_players,
      '#viewing_access' => true,
      '#game_term' => $badgelist_game_term,
      '#list_tags' => $list_tags,
      '#badge_list' => $badges,
      '#is_byteclub' => summergame_is_byteclub_page(),
    ];
  }

}
