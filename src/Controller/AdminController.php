<?php /**
 * @file
 * Contains \Drupal\summergame\Controller\AdminController.
 */

namespace Drupal\summergame\Controller;

use Drupal\Core\Controller\ControllerBase;
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
        'Image' => $badge->field_badge_image->entity->getFileUri(),
        'Title' => $badge->get('title')->value,
        'Level' => '',
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
        $sql .= " AND sg_players.phone LIKE '%:phone%'";
        $params[':phone'] = $search_term;
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

    if ($p1['pid'] && $p2['pid']) {
      $p1_points = summergame_get_player_points($pid1);
      $p1['total'] = $p1_points['career'];
dpm($p1);
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
}
