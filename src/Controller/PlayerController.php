<?php /**
 * @file
 * Contains \Drupal\summergame\Controller\PlayerController.
 */

namespace Drupal\summergame\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
//use Drupal\Core\Database\Database;
//use Drupal\Core\Url;

/**
 * Player controller for the Summer Game module.
 */
class PlayerController extends ControllerBase {

  public function index($pid) {
    $user = \Drupal::currentUser();

    if ($user->id() && $pid === 'extra') {
      if ($user->player['pid']) {
        drupal_set_message("Use the form below to add an extra player to your website account for another person in your household. " .
                           "You will be able to enter game codes and report reading / listening / " .
                           "watching activities for points. You will be able to switch the active player on your " .
                           "website account to specify which player receives points for online activities such as " .
                           "commenting, tagging, or writing reviews. If you wish this " .
                           "player to have a separate website identity for these online activites, please log " .
                           "out and create a new website account before signing up for the Summer Game.");
        $new_player = array('uid' => $user->id());
        $render = \Drupal::formBuilder()->getForm('Drupal\summergame\Form\SummerGamePlayerForm', $pid);
      }
      else {
        // If no player has signed up yet, redirect to the player page
        return new RedirectResponse('/summergame/player');
      }
    }
    else {
      $pid = (int) $pid;

      if ($pid) {
        $player = summergame_player_load(['pid' => $pid]);
      }
      else {
        // Default to the active player if none specified
        $player = summergame_player_load(['uid' => $user->id()]);
      }

      if ($player) {
        $summergame_settings = \Drupal::config('summergame.settings');
        $player_access = summergame_player_access($player['pid']);
        // Check if player's score card is private and we don't have access
        if (!$player['show_myscore'] && !$player_access) {
          drupal_set_message("Player #$pid's Score Card is private", 'error');
          $this->redirect('<front>');
        }
/*
        // Update checkout history for logged in user
        if ($user->uid && $player['uid'] == $user->uid && $user->profile_cohist) {
          $ch_list = db_fetch_array(db_query("SELECT * FROM sopac_lists WHERE uid = %d AND title = 'Checkout History' LIMIT 1", $user->uid));
          if ($ch_list['list_id']) {
            include_once(drupal_get_path('module', 'sopac') . '/sopac_user.php');
            sopac_update_history($ch_list);
          }
        }
*/
        $other_players = array();
        if ($player_access && $player['uid']) {
          $all_players = summergame_player_load_all($player['uid']);

          if (count($all_players) > 1) {
            foreach ($all_players as $extra_player) {
              if ($extra_player['pid'] != $player['pid']) {
                $other_players[] = $extra_player;
              }
            }
          }
        }

        // Prepare links to Other Players
/*
        if ($other_players) {
          $active_pid = 0;
          if ($player['uid'] != $user->uid) {
            $account = user_load($player['uid']);
            if ($account->sg_active_pid) {
              $active_pid = $account->sg_active_pid;
            }
          }
          else if ($user->sg_active_pid) {
            $active_pid = $user->sg_active_pid;
          }

          if (!$active_pid) {
            // Active PID is the lowest PID connected to account
            $active_pid = $player['pid'];
            foreach ($other_players as $other_player) {
              if ($other_player['pid'] < $active_pid) {
                $active_pid = $other_player['pid'];
              }
            }
          }

          $others_links = array();
          foreach ($other_players as $other_player) {
            $other_playername = $other_player['nickname'] ? $other_player['nickname'] : $other_player['name'];
            $l_options = array('html' => TRUE);
            $make_active = '';
            if ($other_player['pid'] == $active_pid) {
              $other_playername = '<span class="active-player hint--bottom" data-hint="This is your ACTIVE Player">' . $other_playername . ' &#x2713;</span>';
            }
            else {
              $options = array('html' => TRUE, 'attributes' => array('class' => 'hint--bottom',
                                                                     'data-hint' => 'Set this Player as your ACTIVE Player',
                                                                     'style' => 'font-size: 1.5em'));
              $make_active = ' :: ' . l('&#x25A1;', 'summergame/player/' . $other_player['pid'] . '/setactive', $options);
            }
            $link = l($other_playername, 'summergame/player/' . $other_player['pid'], $l_options) . $make_active;

            $others_links[] = '<li class="other-player">' . $link . '</li>';
          }

          // highlight active player
          if ($player['pid'] == $active_pid) {
            $playername = '<span class="active-player hint--bottom" data-hint="This is your ACTIVE Player">' . $playername . ' &#x2713;</span>';
          }
          else {
            $options = array('html' => TRUE, 'attributes' => array('class' => 'hint--bottom',
                                                                   'data-hint' => 'Set this Player as your ACTIVE Player',
                                                                   'style' => 'font-size: 1.5em'));
            $playername .= ' :: ' . l('&#x25A1;', 'summergame/player/' . $player['pid'] . '/setactive', $options);
          }
        }
*/
        // Determine Classic Reading Game status
        $completion_gamecode = $summergame_settings->get('summergame_completion_gamecode');
        $db = \Drupal::database();
        $row = $db->query("SELECT * FROM sg_ledger WHERE pid = " . $player['pid'] .
                          " AND metadata LIKE '%gamecode:$completion_gamecode%'")->fetchObject();
        if ($row->lid) {
          $completed_classic = 'Yes, completed on ' . date('F j, Y', $row->timestamp);
        }
        else {
         $completed_classic = false;
        }

        // Check for cell phone attachment code
        if (preg_match('/^[\d]{6}$/', $player['phone'])) {
          $char = chr(($player['pid'] % 26) + 65);
          $player['phone'] = 'TEXT ' . $char . $player['phone'] . ' to 4AADL (42235) to connect your phone';
        }

        // Lookup drupal user if admin
        $website_user = '';
        if ($user->hasPermission('administer summergame')) {
          if ($account = \Drupal\user\Entity\User::load($player['uid'])) {
            $website_user = $account->get('name')->value;
            if ($user->hasPermission('administer users')) {
              $website_user = '<a href="/user/' . $account->id() . '">' . $website_user . '</a>';
            }
          }
        }

        // Prepare Scorecards
        $render[] = [
          '#cache' => [
            'max-age' => 0, // Don't cache, always get fresh data
          ],
          '#theme' => 'summergame_player_page',
          '#summergame_points_enabled' => $summergame_settings->get('summergame_points_enabled'),
          '#playername' => ($player['nickname'] ? $player['nickname'] : $player['name']),
          '#player' => $player,
          '#player_access' => $player_access,
          '#other_players' => $other_players,
          '#points' => summergame_get_player_points($player['pid']),
          '#completed_classic' => $completed_classic,
          '#website_user' => $website_user,
        ];
      }
      else {
        // invalid PID or not authorized
        if ($pid) {
          drupal_set_message('Invalid Player ID: ' . $pid, 'error');
          return $this->redirect('<front>');
        }
        else {
          if ($user->id()) {
            $new_player = array('uid' => $user->uid);
            $render = \Drupal::formBuilder()->getForm('Drupal\summergame\Form\SummerGamePlayerForm', $pid);
          }
          else {
            if ($catalog_domain = variable_get('summergame_catalog_domain', '')) {
              $catalog_domain = 'https://' . $catalog_domain . '/';
            }
            drupal_set_message('You must log into a website account in order to access your Player page');
            return new RedirectResponse('/login?destination=summergame/player');
          }
        }
      }
    }

    return $render;
  }

  private function auth_redemptions($pid, $type) {
    $user = \Drupal::currentUser();
    if ($user->isAuthenticated()) {
      if (!$pid) {
        $player = summergame_player_load(['uid' => $user->id()]);
        $pid = $player['pid'];
        return new RedirectResponse("/user/login?destination=/summergame/player/$pid/$type");
      }
      $player = summergame_player_load($pid);
      $pid = $player['pid'];
      if ($pid && summergame_player_access($pid)) {
        if ($type == 'gamecode') {
          return $redeem_form = \Drupal::formBuilder()->getForm('Drupal\summergame\Form\SummerGamePlayerRedeemForm', $pid);
        } else {
          return $redeem_form = \Drupal::formBuilder()->getForm('Drupal\summergame\Form\SummerGamePlayerConsumeForm', $pid);
        }
      } else {
        drupal_set_message("Invalid ID or no access for player #$pid", 'error');
        return new RedirectResponse('/summergame/player');
      }
    } else {
      drupal_set_message('You must be logged in to redeem a Summer Game code.');
      return new RedirectResponse("/user/login?destination=/summergame/player/0/$type");
    }
  }

  public function redeem($pid = 0) {
    $response = $this->auth_redemptions($pid, 'gamecode');

    if ($response instanceof RedirectResponse) {
      return $response;
    }

    return [
      '#theme' => 'summergame_player_redeem',
      '#redeem_form' => $response,
      '#type' => 'gamecode'
    ];
  }
/*
  public function friends() {
    global $user;
    drupal_add_css(drupal_get_path('module', 'summergame') . '/summergame.css');
    $pid = intval($pid);

    if ($pid) {
      $player = summergame_player_load(array('pid' => $pid));
    }
    else if ($user->uid) {
      // Default to the logged in player if none specified
      $player = $user->player;
    }

    if ($player) {
      // Following, Followers, Friends
      $following = array();
      $following_pids = array();
      $followers = array();
      $friends = array();

      $res = db_query("SELECT * FROM sg_ledger WHERE pid = %d AND metadata LIKE '%%fc_player:%%'", $player['pid']);
      while ($row = db_fetch_object($res)) {
        // grab player ID
        preg_match('/fc_player:([\d]+)/', $row->metadata, $matches);
        $following_player = summergame_player_load($matches[1]);
        if ($following_player['show_leaderboard']) {
          $player_name = $following_player['nickname'] ? $following_player['nickname'] : $following_player['name'];
        }
        else {
          $player_name = 'Player #' . $following_player['pid'];
        }
        if ($following_player['show_myscore'] || user_access('administer summergame')) {
          $player_name = l($player_name, 'summergame/player/' . $following_player['pid']);
        }
        $following[] = array(
          'count' => ++$following_counter,
          'player' => $player_name,
        );
        $following_pids[] = $matches[1];
      }
      $res = db_query("SELECT * FROM sg_ledger WHERE metadata LIKE 'fc_player:%d'", $player['pid']);
      while ($row = db_fetch_object($res)) {
        $follower_player = summergame_player_load($row->pid);
        if ($follower_player['show_leaderboard']) {
          $player_name = $follower_player['nickname'] ? $follower_player['nickname'] : $follower_player['name'];
        }
        else {
          $player_name = 'Player #' . $follower_player['pid'];
        }
        if ($follower_player['show_myscore'] || user_access('administer summergame')) {
          $player_name = l($player_name, 'summergame/player/' . $follower_player['pid']);
        }
        $followers[] = array(
          'count' => ++$follower_counter,
          'player' => $player_name,
        );
        if (in_array($row->pid, $following_pids)) {
          $friends[] = array(
            'count' => ++$friend_counter,
            'player' => $player_name,
          );
        }
      }

      $content .= '<div id="friend-code">';
      $content .= '<div id="friend-title"><h2>FRIEND CODE: ' . $player['friend_code'] . '</h2>';
      if (count($followers) == 0) {
        if ($player['friend_code']) {
          $action = 'I WANT A DIFFERENT';
          $hint = 'Get a new random Friend Code. You can\'t change it once you give it out.';
        }
        else {
          $action = 'GENERATE';
          $hint = "Create a random Friend Code for yourself. You can try again if you don't like it.";
        }
        $options = array('html' => TRUE, 'attributes' => array('class' => 'hint--bottom',
                                                               'data-hint' => $hint,
                                                               ));
        $content .= '[ ' . l($action . ' CODE', 'summergame/player/gfc/' . $player['pid'], $options) . ' ]';
      }
      $content .= '</div>';
      $content .= "<p>You now can have a code of your own! If another player enters your Friend Code, they'll start following you, and " .
                  "you'll EACH earn 100 points. If you enter the Friend Code of any of your followers, you'll become Friends and each earn an additional " .
                  "50 point bonus.</p>";
      $following_header = '<span class="hint--bottom" data-hint="You have entered these players\' Friend Codes">Following: ' .
                          count($following) . '</span>';
      $content .= theme('table',
                        array(array('data' => $following_header, 'colspan' => 2)),
                        $following);
      $followers_header = '<span class="hint--bottom" data-hint="These Players have entered your Friend Code">Followers: ' .
                          count($followers) . '</span>';
      $content .= theme('table',
                        array(array('data' => $followers_header, 'colspan' => 2)),
                        $followers);
      $friends_header = '<span class="hint--left" data-hint="You and these Players have entered each others\' Friend Codes">Friends: ' .
                        count($friends) . '</span>';
      $content .= theme('table',
                        array(array('data' => $friends_header, 'colspan' => 2)),
                        $friends);
      $content .= '</div>'; // $friend-code
    }
    else {
      $content .= '<p>No Player Found</p>';
    }

    return $content;
  }
*/
  public function consume($pid = 0) {
    $response = $this->auth_redemptions($pid, 'consume');

    if ($response instanceof RedirectResponse) {
      return $response;
    }

    return [
      '#theme' => 'summergame_player_redeem',
      '#redeem_form' => $response,
      '#type' => 'consume'
    ];
  }

  public function edit() {

  }

  public function set_active() {

  }

  public function gcpc() {
    if ($player = summergame_player_load(['pid' => $pid])) {
      if (!$player['phone']) {
        // Generate a new cell phone code
        $code = 0;
        while ($code == 0) {
          $code = rand(100000, 999999);
          $collision = $db->query("SELECT pid FROM sg_players WHERE phone = :code", [':code' => $code])->fetch();
          if ($collision->pid) {
            $code = 0;
          }
        }
        $player['phone'] = $code;
        summergame_player_save($player);
        $char = chr(($player['pid'] % 26) + 65);
        drupal_set_message('TEXT ' . $char. $code . ' to 4AADL (42235) to connect your phone');
      }
      return new RedirectResponse('summergame/player/' . $player['pid']);
    }
    return new RedirectResponse('summergame/player');
  }

  public function gfc() {
    if ($player = summergame_player_load(['pid' => $pid])) {
      // Check to see if player already has a code
      if ($player['friend_code']) {
        // Check if anyone has redeemed it already
        $res = $db->query("SELECT COUNT(*) AS fcount FROM sg_ledger WHERE pid = :pid AND metadata LIKE '%fc_follower:%'", [':pid' => $pid]);
        $fcount = $res->fetch();
        if ($fcount->fcount) {
          $followers = $fcount->fcount . ' follower' . ($fcount->fcount == 1 ? '' : 's');
          drupal_set_message("Cannot regenerate Friend Code once it has been redeemed ($followers)", 'error');
          return new RedirectResponse('summergame/player/' . $player['pid']);
        }
      }

      // Generate a new referral code
      $nums = '34679';
      $num_max_idx = strlen($nums) - 1;
      $lines = file(drupal_get_path('module', 'summergame') . '/upgoer5words.txt');

      $code = '';
      while ($code == '') {
        $word = str_replace("'", '', trim($lines[array_rand($lines)]));
        if (strlen($word) > 3) {
          $code = strtoupper($word);
          for ($i = 0; $i < 3; $i++) {
            $code .= $nums[mt_rand(0, $num_max_idx)];
          }
          $collision = $db->query("SELECT pid FROM sg_players WHERE friend_code = :code", [':code' => $code])->fetch();
          if ($collision->pid) {
            $code = '';
          }
        }
      }
      $player['friend_code'] = $code;
      summergame_player_save($player);
      drupal_set_message("Your play.aadl.org Friend Code is $code. Earn bonus points when a friend enters that code as a Game Code.");
      return new RedirectResponse('summergame/player/' . $player['pid']);
    }
    return new RedirectResponse('summergame/player');
  }

  public function ledger($pid) {
    $pid = intval($pid);

    if ($pid) {
      $player = summergame_player_load(array('pid' => $pid));
    }

    if ($player) {
      $player_access = summergame_player_access($player['pid']);

      if (!$player['show_myscore'] && !$player_access) {
        drupal_set_message("Player #$pid's Score Card is private", 'error');
        return $this->redirect('<front>');
      }

      // build the pager
      $page = pager_find_page();
      $per_page = 100;
      $offset = $per_page * $page;

      $db = \Drupal::database();
      if (isset($_GET['term'])) {
        $total = $db->query("SELECT COUNT(lid) as total FROM sg_ledger WHERE pid = :pid AND game_term = :term",
        [':pid' => $pid, ':term' => $_GET['term']])->fetch();
        $total = $total->total;
        $result = $db->query("SELECT * FROM sg_ledger WHERE pid = :pid AND game_term = :term ORDER BY timestamp DESC LIMIT $offset, $per_page",
        [':pid' => $pid, ':term' => $_GET['term']]);
      } else {
        $total = $db->query("SELECT COUNT(lid) as total FROM sg_ledger WHERE pid = :pid",
          [':pid' => $pid])->fetch();
        $total = $total->total;
        $result = $db->query("SELECT * FROM sg_ledger WHERE pid = :pid ORDER BY timestamp DESC LIMIT $offset, $per_page",
          [':pid' => $pid]);
      }

      $pager = pager_default_initialize($total, $per_page);

      while ($row = $result->fetchAssoc()) {
        // Change bnum: code to a link to the bib record
        if (preg_match('/bnum:([\w-]+)/', $row['metadata'], $matches)) {
          if (preg_match('/^\d{7}$/', $matches[1])) {
            $row['description'] = '<img src="//cdn.aadl.com/covers/' . $matches[1] . '_100.jpg" width="50"> ' . $row['description'];
          }
          if ($row['type'] != 'Download of the Day' || $player_access) { // Don't link to DotD records
            $row['description'] = '<a href="/catalog/record/' . $matches[1] .  '">' .$row['description'] . '</a>';
          }
        }
        // Translate material code to catalog material type
        if (preg_match('/mat_code:([a-z])/', $row['metadata'], $matches)) {
          $row['description'] = 'Points for ' . $locum->locum_config['formats'][$matches[1]] .
                                ', ' . $row['description'];
        }
        // handle game codes
        if (preg_match('/gamecode:([\w]+)/', $row['metadata'], $matches)) {
          if ($player_access) {
            $row['type'] .= ': ' . $matches[1];
          }
          else {
            // Check if there is a hint for this game code
            $hint_row = $db->query("SELECT hint FROM sg_game_codes WHERE text = :text", [ ':text' => $matches[1]])->fetch();
            if ($hint_row->hint) {
              $row['description'] = $hint_row->hint;
            }
          }
        }
        // link to nodes
        if (preg_match('/nid:([\d]+)/', $row['metadata'], $matches)) {
          if ($row['type'] != 'Download of the Day' || $player_access) { // Don't link to DotD records
            $node = node_load($matches[1]);
            $node_title = $node->get('title')->value;
            $nid = $node->get('nid')->value;
            $row['description'] .= ": <a href=\"/node/$nid\">$node_title</a>";
            // and link to comment
            if (preg_match('/cid:([\d]+)/', $row['metadata'], $matches)) {
              $comment = $matches[1];
              $row['description'] .= " (<a href=\"/node/$nid#comment-$comment\">See comment</a>)";
            }
          }
        }

        $table_row = [
          'date' => date('F j, Y, g:i a', $row['timestamp']),
          'type' => $row['type'],
          'description' => ($player['show_titles'] || $player_access ? $row['description'] : ''),
          'points' => $row['points']
        ];
        if ($player_access) {
          if (strpos($row['metadata'], 'delete:no') === 0) {
            $table_row['remove'] = '';
          }
          else {
            $table_row['remove'] = 'hmmm';
            $table_row['remove'] = '<a href="summergame/player/' . $player['pid'] . '/ledger/' . $row['lid'] . '/deletescore">DELETE</a>';
          }
        }
        $score_table[] = $table_row;
      }

      if (count($score_table)) {
        $ledger = $score_table;
      }
      else {
        $ledger = NULL;
      }
    }

    return [
      '#cache' => [
        'max-age' => 0, // Don't cache, always get fresh data
      ],
      '#theme' => 'summergame_player_ledger',
      '#ledger' => $ledger,
      '#pager' => [
        '#type' => 'pager',
        '#quantity' => 5
      ]
    ];
  }
}
