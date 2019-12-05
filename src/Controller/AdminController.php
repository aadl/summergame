<?php /**
 * @file
 * Contains \Drupal\summergame\Controller\AdminController.
 */

namespace Drupal\summergame\Controller;

use Drupal\Core\Controller\ControllerBase;
use Predis\Client;
//use Drupal\Core\Database\Database;
//use Drupal\Core\Url;

/**
 * Admin controller for the Summer Game module.
 */
class AdminController extends ControllerBase {

  public function index() {
    $sg_admin = \Drupal::currentUser()->hasPermission('administer summergame');
    $admin_users = \Drupal::currentUser()->hasPermission('administer users');

    $limit = 25;
    $gc_rows = [];
    $creator_names = [];
    $db = \Drupal::database();

    $res = $db->query("SELECT * FROM sg_game_codes ORDER BY created DESC LIMIT $limit");
    while ($game_code = $res->fetchAssoc()) {
      // Load creator info
      $creator_uid = $game_code['creator_uid'];
      if (!isset($creator_names[$creator_uid])) {
        if ($account = \Drupal\user\Entity\User::load($creator_uid)) {
          $creator_names[$creator_uid] = $account->get('name')->value;
        }
        else {
          $creator_names[$creator_uid] = 'UNKNOWN';
        }
      }
      $creator_name = $creator_names[$creator_uid];

      if (!$sg_admin) {
        $game_code['text'] = preg_replace('/\B\w/', '*', $game_code['text']);
      }

      $valid_start = $game_code['valid_start'] ? date('n/d/Y H:i:s', $game_code['valid_start']) : 'Now';
      $valid_end = date('n/d/Y H:i:s', $game_code['valid_end']);
      $gc_rows[] = [
        'id' => $game_code['code_id'],
        'Text' => strlen($game_code['text']) > 25 ? substr($game_code['text'], 0, 25) . '...' : $game_code['text'],
        'Description' => $game_code['description'],
        'Clue' => $game_code['clue'],
        'ClueTrigger' => $game_code['clue_trigger'],
        'Hint' => $game_code['hint'],
        'Points' => $game_code['points'] . ($game_code['diminishing'] ? ' (diminishing)' : ''),
        'Created' => date('n/d/Y', $game_code['created']),
        'CreatedBy' => ($admin_users ? '<a href="/user/' . $creator_uid . '">' . $creator_name . '</a>' : $creator_name),
        'ValidDates' => $valid_start . '-<br>' . $valid_end,
        'GameTerm' => $game_code['game_term'],
        'Redemptions' => $game_code['num_redemptions'] . ' of ' . $game_code['max_redemptions']
      ];
    }

    // Badges
    $badge_rows = [];
    $badge_ids = \Drupal::entityQuery('node')
                 ->condition('type','sg_badge')
                 ->sort('nid', 'DESC')
                 ->range(0, 25)
                 ->execute();
    $badges = \Drupal\node\Entity\Node::loadMultiple($badge_ids);
    foreach ($badges as $badge) {
      $formula = $badge->get('field_badge_formula')->value;
      if (!$sg_admin) {
        $formula = preg_replace('/\B\w/', '*', $formula);
      }
      $badge_rows[] = [
        'BadgeID' => '<a href="/node/' . $badge->id() . '">' . $badge->id() . '</a>',
//        'Image' => $badge->field_badge_image->entity->getFileUri(),
        'Title' => $badge->get('title')->value,
        'Level' => $badge->get('field_badge_level')->value,
        'Description' => $badge->get('body')->value,
        'Formula' => strlen($formula) > 25 ? substr($formula, 0, 25) . '...' : $formula,
      ];
    }

    if (count($badges) < 25) {
      $res = $db->query('SELECT * FROM sg_badges ORDER BY bid DESC LIMIT ' . (25 - count($badges)));
      while ($badge = $res->fetchAssoc()) {
        if (!$sg_admin) {
          $badge['formula'] = preg_replace('/\B\w/', '*', $badge['formula']);
        }
        $badge_rows[] = [
          'BadgeID' => ($sg_admin ? '<a href="/summergame/admin/badge/' . $badge['bid'] . '">' . $badge['bid'] . '</a>' : $badge['bid']),
          'Image' => $badge['image'],
          'Title' => $badge['title'],
          'Level' => $badge['level'],
          'Description' => $badge['description'],
          'Formula' => strlen($badge['formula']) > 25 ? substr($badge['formula'], 0, 25) . '...' : $badge['formula'],
        ];
      }
    }
    $render[] = [
      '#attached' => [
        'library' => [
          'summergame/summergame-lib'
        ]
      ],
      '#cache' => [
        'max-age' => 0, // Don't cache, always get fresh data
      ],
      '#theme' => 'summergame_admin_page',
      '#print_page_url' => \Drupal::config('summergame.settings')->get('summergame_print_page'),
      '#summergame_player_search_form' => \Drupal::formBuilder()->getForm('Drupal\summergame\Form\SummerGamePlayerSearchForm'),
      '#summergame_gamecode_search_form' => \Drupal::formBuilder()->getForm('Drupal\summergame\Form\SummerGameGameCodeSearchForm'),
      '#sg_admin' => $sg_admin,
      '#gc_rows' => $gc_rows,
      '#badge_rows' => $badge_rows,
      '#limit' => $limit,
    ];

    return $render;
  }

  public function gamecodes($search_term = '') {
    $sg_admin = \Drupal::currentUser()->hasPermission('administer summergame');
    $admin_users = \Drupal::currentUser()->hasPermission('administer users');
    $db = \Drupal::database();

    // Game Codes
    $rows = array();
    $creators = array();
    if ($search_term) {
      $wild_term = "%$search_term%";
      $res = $db->query("SELECT * FROM sg_game_codes " .
                        "WHERE text LIKE :text " .
                        "OR description LIKE :description " .
                        "OR clue LIKE :clue " .
                        "OR clue_trigger LIKE :clue_trigger " .
                        "OR hint LIKE :hint " .
                        "OR game_term LIKE :game_term " .
                        "OR game_term_override LIKE :game_term_override " .
                        "ORDER BY created DESC",
                        [':text' => $wild_term,
                         ':description' => $wild_term,
                         ':clue' => $wild_term,
                         ':clue_trigger' => $wild_term,
                         ':hint' => $wild_term,
                         ':game_term' => $wild_term,
                         ':game_term_override' => $wild_term]);
    }
    else {
      $res = db_query("SELECT * FROM sg_game_codes ORDER BY created DESC");
    }
    while ($game_code = $res->fetchAssoc()) {
      // Load creator info
      $creator_uid = $game_code['creator_uid'];
      if (!isset($creator_names[$creator_uid])) {
        if ($account = \Drupal\user\Entity\User::load($creator_uid)) {
          $creator_names[$creator_uid] = $account->get('name')->value;
        }
        else {
          $creator_names[$creator_uid] = 'UNKNOWN';
        }
      }
      $creator_name = $creator_names[$creator_uid];

      $game_code['text'] = (strlen($game_code['text']) > 25 ? substr($game_code['text'], 0, 25) . '...' : $game_code['text']);
      $valid_start = $game_code['valid_start'] ? date('n/d/Y H:i:s', $game_code['valid_start']) : 'Now';
      $valid_end = date('n/d/Y H:i:s', $game_code['valid_end']);
      $rows[] = array(
        'id' => $game_code['code_id'],
        'Text' => $sg_admin ? $game_code['text'] : preg_replace('/\B\w/', '*', $game_code['text']),
        'Description' => $game_code['description'],
        'Clue' => $game_code['clue'],
        'ClueTrigger' => $game_code['clue_trigger'],
        'Hint' => $game_code['hint'],
        'Points' => $game_code['points'] . ($game_code['diminishing'] ? ' (diminishing)' : ''),
        'Created' => date('n/d/Y', $game_code['created']),
        'CreatedBy' => ($admin_users ? '<a href="/user/' . $creator_uid . '">' . $creator_name . '</a>' : $creator_name),
        'ValidDates' => $valid_start . '-<br>' . $valid_end,
        'GameTerm' => $game_code['game_term'],
        'Redemptions' => $game_code['num_redemptions'] . ' of ' . $game_code['max_redemptions'],
      );
    }

    $render[] = [
      '#cache' => [
        'max-age' => 0, // Don't cache, always get fresh data
      ],
      '#theme' => 'summergame_admin_gamecodes_page',
      '#summergame_gamecode_search_form' => \Drupal::formBuilder()->getForm('Drupal\summergame\Form\SummerGameGameCodeSearchForm', ['search_term' => $search_term]),
      '#sg_admin' => $sg_admin,
      '#rows' => $rows,
    ];

    return $render;
  }
/*
  public function badges() {
    drupal_add_css(drupal_get_path('module', 'summergame') . '/summergame.css');
    $sg_admin = user_access('administer summergame');
    $admin_users = user_access('administer users');
    $content .= '<div id="summergame-admin-page">';
    $content .= '<h1>Summer Game Badges</h1>';

    // Badges
    if ($sg_admin) {
      $content .= '<ul class="create-new-code"><li class="button green">' . l("Create New Badge", 'summergame/admin/badge') . '</li></ul>';
    }
    $content .= '<h2 class="title">Badges</h2>';
    $sg_image_path = base_path() . file_directory_path() . '/sg_images/';
    $rows = array();
    $res = db_query("SELECT * FROM sg_badges ORDER BY bid DESC");
    while ($badge = db_fetch_array($res)) {
      if (!$sg_admin) {
        $badge['formula'] = preg_replace('/\B\w/', '*', $badge['formula']);
      }
      $rows[] = array(
        'Badge ID' => ($sg_admin ? l($badge['bid'], 'summergame/admin/badge/' . $badge['bid']) : $badge['bid']),
        'Image' => '<img src="' . $sg_image_path . $badge['image'] . '_100.png">',
        'Title' => '<strong>' . $badge['title'] . '</strong>',
        'Level' => $badge['level'],
        'Description' => $badge['description'],
        'Formula' => strlen($badge['formula']) > 25 ? substr($badge['formula'], 0, 25) . '...' : $badge['formula'],
      );
    }
    $content .= theme('table', array_keys($rows[0]), $rows);

    $content .= '</div>'; // #summergame-admin-page

    return $content;
  }
*/
  public function players($search_term = '') {

    if ($search_term == 'new') {
      return \Drupal::formBuilder()->getForm('Drupal\summergame\Form\SummerGamePlayerForm');
    }
    else {
      $db = \Drupal::database();
      $search_term = strtoupper($search_term);
      $params = [];
      $new_player = [];

      $sql = "SELECT sg_players.*, users_field_data.name AS username FROM sg_players LEFT JOIN users_field_data ON sg_players.uid = users_field_data.uid WHERE 1";

      if (is_numeric($search_term)) {
        // Search phone numbers
        $sql .= " AND sg_players.phone LIKE :phone";
        $params[':phone'] = "%$search_term%";
        $new_player['phone'] = $search_term;
      }
      else if (preg_match('/^S?[ART]G[\d]{5}$/', $search_term)) { //SRG12345, TG12345, AG12345
        $sql .= " AND sg_players.gamecard LIKE '%:gamecard%'";
        $params[':gamecard'] = $search_term;
        $new_player['gamecard'] = $search_term;
      }
      else if ($search_term) {
        $sql .= " AND (sg_players.name LIKE :playername OR sg_players.nickname LIKE :nickname OR users_field_data.name LIKE :username)";
        $params[':playername'] = "%$search_term%";
        $params[':nickname'] = "%$search_term%";
        $params[':username'] = "%$search_term%";
        $new_player['name'] = $search_term;
      }

      // Run the search
      $res = $db->query($sql, $params);
      $res->allowRowCount = TRUE;
      $count = $res->rowCount();

/*
      // Rerun query with OR on terms if no results
      if ($count == 0 && strpos($search_term, ' ') !== FALSE) {
        $params = [];
        $sql = "SELECT sg_players.*, users.name AS username FROM sg_players LEFT JOIN users ON sg_players.uid = users.uid WHERE (0 ";
        foreach (explode(' ', $search_term) as $term) {
          $sql .= "OR sg_players.name LIKE '%%%s%%' OR sg_players.nickname LIKE '%%%s%%' OR users.name LIKE '%%%s%%'";
          $params[] = $term;
          $params[] = $term;
          $params[] = $term;
        }
        $sql .= ")";
        $res = db_query($sql, $params);
        $count = mysqli_num_rows($res);
      }
*/
/*
      $content .= '<div style="float: right">' .
                  drupal_get_form('summergame_player_search_form', $search_term) .
                  '</div>';
      $content .= "<h2>Your search returned $count match" . ($count == 1 ? '' : 'es') . "</h2>";
*/

      if ($count == 0) {
        // No matches, create a new player
        drupal_set_message("No existing players to match your search \"$search_term\". Create a new player with that information below:");
        return \Drupal::formBuilder()->getForm('Drupal\summergame\Form\SummerGamePlayerForm'); // TODO Add new $new_player
      }
      else if ($count > 100) {
        return [
          '#markup' => "<h2>Your search returned more than 100 matches: ($count)<h2><h3>Please search again</h3>"
        ];
      }
      else {
        // Found 1-100 matches, display them in a table
        while ($player = $res->fetchAssoc()) {
          $rows[] = [
            'pid' => $player['pid'],
            'RealName' => $player['name'],
            'PlayerName' => $player['nickname'],
            'WebUser' => ($player['uid'] ? '<a href="/user/' . $player['uid'] . '">' . $player['username'] . '</a>' : ''),
            'Phone' => $player['phone'] ? $player['phone'] : '',
            'AgeGroup' => $player['agegroup'],
            'Gamecard' => $player['gamecard'],
            'School' => $player['school'],
            'Grade' => $player['grade'] ? $player['grade'] : '',
          ];
        }
      }
    }

    return [
      '#cache' => [
        'max-age' => 0, // Don't cache, always get fresh data
      ],
      '#theme' => 'summergame_admin_player_page',
      '#summergame_player_search_form' => \Drupal::formBuilder()->getForm('Drupal\summergame\Form\SummerGamePlayerSearchForm', $search_term),
      '#rows' => $rows,
    ];
  }

  public function players_merge($pid1 = 0, $pid2 = 0, $confirm = FALSE) {
    if ($confirm) {
      summergame_players_merge($pid1, $pid2);
      drupal_set_message("Player #$pid2 merged into Player #$pid1");
      return $this->redirect('summergame.player', ['pid' => $pid1]);
    }

    $p1 = summergame_player_load(['pid' => $pid1]);
    $p2 = summergame_player_load(['pid' => $pid2]);

    // Don't worry about Badge IDs
    unset($p1['bids'], $p2['bids']);

    if ($p1['pid'] && $p2['pid']) {
      $p1_points = summergame_get_player_points($pid1);
      $p1['total'] = $p1_points['career'];

      $p2_points = summergame_get_player_points($pid2);
      $p2['total'] = $p2_points['career'];

      $merge_table = [];
      foreach ($p2 as $field => $p2_data) {
        $arrows = (!empty($p2_data) && empty($p1[$field]) ? '>>>' : '');
        $merge_table[] = [
          'field' => $field,
          'p2data' => $p2_data,
          'arrows' => $arrows,
          'p1data' => $p1[$field],
        ];
      }

      return [
        '#cache' => [
          'max-age' => 0, // Don't cache, always get fresh data
        ],
        '#theme' => 'summergame_admin_players_merge_page',
        '#pid1' => $pid1,
        '#pid2' => $pid2,
        '#rows' => $merge_table,
      ];
    }
    else {
      drupal_set_message('Invalid Player IDs', 'error');
      return $this->redirect('summergame.admin');
    }
  }

  public function lego_results() {
    $time_start = microtime(true);
    ////////////////////////////////////////////////////////////////////////////
    $redis = new Client(\Drupal::config('summergame.settings')->get('summergame_redis_conn'));

    $results = [];
    $keys = $redis->keys('lego_vote*');
    foreach ($keys as $key) {
      $vote = $redis->get($key);
      $results[$vote[1]][$vote]++;
    }

    $content = '<h1>Lego Voting Results</h1>';
    foreach ($results as $group_char => $group_results) {
      arsort($group_results);
      $content .= "<h2>Group $group_char</h2>";
      $content .= '<table><tr><th>ENTRY</th><th>VOTES</th></tr>';
      foreach ($group_results as $entry => $votes) {
        $content .= "<tr><td>$entry</td><td>$votes</td></tr>";
      }
      $content .= '</table></div>';
    }

    ////////////////////////////////////////////////////////////////////////////
    $time = microtime(true) - $time_start;
    $content .= "<p>Execution time: $time seconds</p>";

    return [
      \Drupal::formBuilder()->getForm('\Drupal\summergame\Form\SummerGameLegoResultsAddForm'),
      ['#markup' => $content],
    ];
  }

  public function stats($game_term = '', $year = 0, $month = 0) {
    $db = \Drupal::database();

    // Get Game Term
    $game_term = (empty($game_term) ? \Drupal::config('summergame.settings')->get('summergame_current_game_term') : $game_term);
    $year = (empty($year) ? date('Y') : $year);
    $month = (empty($month) ? date('n') : $month);
    $end_month = ($month % 12) + 1;

    // Find Start and End Dates for game term
    // $earliest = date('Y-m-d', $db->query("SELECT MIN(timestamp) FROM `sg_ledger` WHERE `game_term` = '$game_term'")->fetchField());
    // $latest = date('Y-m-d', $db->query("SELECT MAX(timestamp) FROM `sg_ledger` WHERE `game_term` = '$game_term'")->fetchField());

    $date = new \DateTime("$year-$month-1");
    $end = new \DateTime("$year-$end_month-1");

    $page_header = [
      '#markup' => "<h1>Daily Stats for $game_term, " . $date->format('l F j, Y') . ' - ' . $end->format('l F j, Y') . '</h1>',
    ];

    $table = [
      '#type' => 'table',
      '#header' => ['Date', 'Player Count', 'Total Points', 'Shop Point Total', 'Classic Shop Point Total', 'Game Code Count', 'Badge Count'],
    ];

    while ($date <= $end) {
      $timestamp = $date->getTimestamp();
      $player_count = $db->query("SELECT COUNT(DISTINCT pid) FROM `sg_ledger` WHERE `game_term` = '$game_term' AND `timestamp` < $timestamp")->fetchField();
      $point_total = $db->query("SELECT SUM(points) FROM `sg_ledger` WHERE `game_term` = '$game_term' AND points > 0 AND `timestamp` < $timestamp")->fetchField();
      $shop_total = $db->query("SELECT SUM(points) FROM `sg_ledger` WHERE `game_term` = '$game_term' AND `type` = 'Shop Order' AND `timestamp` < $timestamp")->fetchField();
      $classic_shop_total = $db->query("SELECT SUM(points) FROM `sg_ledger` WHERE `game_term` = 'SummerGameClassic' AND `type` = 'Shop Order' AND `timestamp` < $timestamp")->fetchField();
      $code_count = $db->query("SELECT COUNT(*) FROM `sg_ledger` WHERE `game_term` = '$game_term' AND `type` = 'Game Code' AND `timestamp` < $timestamp")->fetchField();
      $badge_count = $db->query("SELECT COUNT(*) FROM `sg_ledger` WHERE `game_term` = '$game_term' AND `type` = 'Badge Bonus' AND `timestamp` < $timestamp")->fetchField();

      $table['#rows'][] = [$date->format('Y-m-d'), $player_count, $point_total, $shop_total, $classic_shop_total, $code_count, $badge_count];

      $date->modify('+1 day');
    }

    return [
      $page_header,
      $table,
    ];
  }

  public function badgestats($game_term = '') {
    $game_term = (empty($game_term) ? \Drupal::config('summergame.settings')->get('summergame_current_game_term') : $game_term);
    $output = "<h1>Badge Stats for $game_term</h1>";

    $db = \Drupal::database();
    $vocab = 'sg_badge_series';
    $badges = [];

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
        ->condition('field_badge_game_term', $game_term)
        ->condition('field_sg_badge_series', $term->id());
      $nodes = $query->execute();

      if (count($nodes)) {
        $output .= '<h2>' . $series . ' :: ' . count($nodes) . ' Badges</h2>';
        foreach ($nodes as $nid) {
          $badge = entity_load('node', $nid);

          $awarded = $db->query("SELECT COUNT(pid) AS pcount FROM sg_players_badges WHERE bid=:bid", [':bid' => $badge->id()])->fetch();
          $title = $badge->get('title')->value;
          $formula = $badge->get('field_badge_formula')->value;

          $output .= '<p><a href="/node/' . $badge->id() . '"><strong>' . $title . '</strong></a> : ' . $awarded->pcount . '<br>';
          $output .= "Formula: <em>$formula</em><br>";

          if (preg_match('/^{([\d,]+)}$/', $formula, $matches)) {
            // Badge collection badge
            $output .= 'BADGE COLLECTION BADGE';
          }
          else if (strpos($badge->formula, '::')) {
            // Multiple of a ledger type formula
            $output .= 'LEDGER MULTIPLE BADGE';
          }
          else {
            // Collection badge
            $output .= 'COLLECTION BADGE<br><ul>';
            foreach (explode(',', $formula) as $text_pattern) {
              $query = "SELECT COUNT(lid) AS ledger_count FROM sg_ledger WHERE 1 AND (";
              $args = [];

              $text_patterns = explode('|', $text_pattern);
              foreach ($text_patterns as $i => &$pattern) {
                $args[':type_' . $i] = $pattern;
                $args[':metadata_' . $i] = 'gamecode:' . $pattern;
                $pattern = "(type LIKE :type_$i OR metadata LIKE :metadata_$i)";
              }
              $query .= implode(' OR ', $text_patterns);

              $query .= ") AND game_term = :game_term";
              $args[':game_term'] = $game_term;
              $ledger_count = $db->query($query, $args)->fetchObject();

              $output .= '<li>' . $text_pattern . ' :: ' . $ledger_count->ledger_count . '</li>';
            }
            $output .= '</ul>';
          }

          $output .= "</p>";
        }
      }
    }

    return [
      '#markup' => $output,
    ];
  }
}
