<?php /**
 * @file
 * Contains \Drupal\summergame\Controller\PlayerController.
 */

namespace Drupal\summergame\Controller;

use Drupal\user\Entity\User;
use Drupal\node\Entity\Node;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\summergame\Helper\BadgeRenderer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Messenger\Messenger;

//use Drupal\Core\Database\Database;
//use Drupal\Core\Url;

/**
 * Player controller for the Summer Game module.
 */
class PlayerController extends ControllerBase {

  public function index($pid) {
    $user = \Drupal::currentUser();
    if (!$user->isAuthenticated()) {
      return new RedirectResponse('/user/login?destination=/summergame/player');
    }
/*
    if ($user->id() && $pid === 'extra') {
      if ($user->player['pid']) {
        \Drupal::messenger()->addMessage("Use the form below to add an extra player to your website account for another person in your household. " .
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
*/
    $pid = (int) $pid;

    if ($pid) {
      $player = summergame_player_load(['pid' => $pid]);
    }
    else {
      // Default to the active player if none specified
      if ($player = summergame_get_active_player()) {
        return new RedirectResponse('/summergame/player/' . $player['pid']);
      }
    }

    if ($player) {
      $db = \Drupal::database();
      $summergame_settings = \Drupal::config('summergame.settings');
      $player_access = summergame_player_access($player['pid']);
      $current_game_term = $summergame_settings->get('summergame_current_game_term');
      $commerce_game_term = \Drupal::config('commerce_summergame.settings')->get('commerce_summergame_game_term');
      $commerce_shop_term = \Drupal::config('commerce_summergame.settings')->get('commerce_summergame_shop_term');

      // Check if player's score card is private and we don't have access
      if (!$player['show_myscore'] && !$player_access) {
        \Drupal::messenger()->addError("Player #$pid's Score Card is private");
        return $this->redirect('<front>');
      }

      $other_players = array();
      $quick_transfer = '';
      if ($player_access && $player['uid']) {
        $all_players = summergame_player_load_all($player['uid']);

        if (count($all_players) > 1) {
          foreach ($all_players as $extra_player) {
            if ($extra_player['pid'] != $player['pid']) {
              $other_players[] = $extra_player;
            }
          }

          if ($user->hasPermission('administer summergame')) {
            $quick_transfer = \Drupal::formBuilder()->getForm('Drupal\summergame\Form\SummerGamePlayerQuickTransferForm', $player, $other_players);
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
      $row = $db->query("SELECT * FROM sg_ledger WHERE pid = " . $player['pid'] .
                        " AND metadata LIKE '%gamecode:$completion_gamecode%'")->fetchObject();
      if (isset($row->lid)) {
        $completed_classic = date('F j, Y', $row->timestamp);
      }
      else {
        $completed_classic = FALSE;
      }

      // Check for cell phone attachment code
      if (preg_match('/^[\d]{6}$/', $player['phone'] ?? '')) {
        $char = summergame_get_phone_character($player['pid']);
        $player['phone'] = 'TEXT ' . $char . $player['phone'] . ' to 734-327-4200 to connect your phone';
      }

      // Lookup drupal user if admin
      $website_user = '';
      $homecode = '';
      if ($account = User::load($player['uid'])) {
        if ($user->id() == $account->id() || $user->hasPermission('administer summergame')) {
          $website_user = $account->get('name')->value;

          // Lookup home code for player
          $homecode = summergame_get_homecode($account->id());
          if (isset($homecode->text)) {
            $homecode = '<a href="/summergame/user/' . $account->id() . '/homecode">' . $homecode->text . '</a>';
          }
          else if ($summergame_settings->get('summergame_homecode_form_enabled')) {
            $homecode = '<a href="/summergame/user/' . $account->id() . '/homecode">Create a Lawn Code or Library Code</a>';
          }
          else {
            $homecode = '';
          }

          if ($user->id() == $account->id() || $user->hasPermission('administer users')) {
            $website_user = '<a href="/user/' . $account->id() . '">' . $website_user . '</a>';
          }
        }
      }

      // Get Player Balances
      $balances = [];
      if (\Drupal::moduleHandler()->moduleExists('commerce_summergame')) {
        $balances = commerce_summergame_get_player_balances($player['pid']);
      }

      // Get Points-o-Matic weekly scores
      $pointsomatic_weekly_totals = [];
      if (\Drupal::moduleHandler()->moduleExists('pointsomatic')) {
        $pointsomatic_weekly_totals = pointsomatic_get_player_weekly_totals($player['pid']);
      }

      // Get player progress against limits
      $progress = [];
      $game_limits = json_decode($summergame_settings->get('summergame_game_limits'), TRUE);

      foreach ($game_limits as $ledger_type => $game_limit) {
        $sql = "SELECT SUM(points) AS total FROM sg_ledger " .
               "WHERE pid = :pid " .
               "AND type = :type " .
               "AND game_term = :game_term";
        $type_total = $db->query($sql, [':pid' => $pid, ':type' => $ledger_type, ':game_term' => $current_game_term])->fetchField();
        $progress[] = ['type' => $ledger_type, 'total' => ($type_total ?? 0), 'limit' => $game_limit];
      }

      // Prepare Scorecards
      $render[] = [
        '#attached' => [
          'library' => [
            'summergame/summergame-lib'
          ]
        ],
        '#cache' => [
          'max-age' => 0, // Don't cache, always get fresh data
        ],
        '#theme' => 'summergame_player_page',
        '#summergame_points_enabled' => $summergame_settings->get('summergame_points_enabled'),
        '#playername' => ($player['nickname'] ? $player['nickname'] : $player['name']),
        '#player' => $player,
        '#player_access' => $player_access,
        '#other_players' => $other_players,
        '#quick_transfer' => $quick_transfer,
        '#points' => summergame_get_player_points($player['pid'], $current_game_term),
        '#game_terms_played'=>summergame_get_player_game_terms($pid),
        '#balances' => $balances,
        '#pointsomatic_weekly_totals' => $pointsomatic_weekly_totals,
        '#progress' => $progress,
        '#summergame_current_game_term' => $current_game_term,
        '#commerce_shop_term' => $commerce_shop_term,
        '#summergame_shop_message_threshold' => $summergame_settings->get('summergame_shop_message_threshold'),
        '#summergame_shop_message' => $summergame_settings->get('summergame_shop_message'),
        '#commerce_game_term' => $commerce_game_term,
        '#completed_classic' => $completed_classic,
        '#website_user' => $website_user,
        '#homecode' => $homecode,
        '#game_display_name' => $summergame_settings->get('game_display_name'),
        '#summergame_leagues_enabled' => \Drupal::config('summergame.settings')->get('summergame_leagues_enabled'),
      ];
    }
    else {
      // invalid PID or not authorized
      if ($pid) {
        \Drupal::messenger()->addError('Invalid Player ID: ' . $pid);
        return $this->redirect('<front>');
      }
      else {
        if ($user->id()) {
          return $this->redirect('summergame.player.new');
        }
        else {
          \Drupal::messenger()->addMessage('You must log into a website account in order to access your Player page');
          return new RedirectResponse('/user/login?destination=summergame/player');
        }
      }
    }
    // }

    return $render;
  }

  public function get_game_term_scorecard($pid, $game_term){
   $user = \Drupal::currentUser();
    if (!$user->isAuthenticated()) {
      return new RedirectResponse('/user/login?destination=/summergame/player');
    }


    $pid = (int) $pid;

    if ($pid) {
      $player = summergame_player_load(['pid' => $pid]);
    }
    else {
      // Default to the active player if none specified
      if ($player = summergame_get_active_player()) {
        return new RedirectResponse('/summergame/player/' . $player['pid']);
      }
    }

    //verify game_term
    if ($player) {
      $summergame_settings = \Drupal::config('summergame.settings');

      $player_access = summergame_player_access($player['pid']);

      // Check if player's score card is private and we don't have access
      if (!$player['show_myscore'] && !$player_access) {
        \Drupal::messenger()->addError("Player #$pid's Score Card is private");
        return $this->redirect('<front>');
      }


      $game_terms = summergame_get_player_game_terms($player['pid']);
      if (!in_array($game_term, $game_terms)) {
        $points = NULL;
        $game_term_valid = false;
      } else {
        $points = summergame_get_player_points($player['pid'], $game_term);
        $game_term_valid = true;
      }

      //logic for controller goes here
      $renderArray = [
        '#theme' => 'summergame_player_scorecard',
        '#points' => $points,
        '#game_term_valid'=>$game_term_valid,
        '#page_game_term' => $game_term,
        '#player' => $player,
        '#directory'=>'themes/custom/aadl',
        '#attached'=>[
          'library'=> array('summergame/summergame-lib'),
          ],
        '#cache' => ['max-age' => 0]
      ];

      return $renderArray;

    } else {
      // invalid PID or not authorized
      if ($pid) {
        \Drupal::messenger()->addError('Invalid Player ID: ' . $pid);
        return $this->redirect('<front>');
      }
      else {
        if ($user->id()) {
          return $this->redirect('summergame.player.new');
        }
        else {
          \Drupal::messenger()->addMessage('You must log into a website account in order to access your Player page');
          return new RedirectResponse('/user/login?destination=summergame/player');
        }
      }
    }


  }

  private function auth_redemptions($pid, $type) {
    $user = \Drupal::currentUser();
    $gameDisplayName = \Drupal::config('summergame.settings')->get('game_display_name');
    if ($user->isAuthenticated()) {
      $pid = (int) $pid;
      if ($pid) {
        $player = summergame_player_load($pid);
        $pid = $player['pid'];
        if ($pid && summergame_player_access($pid)) {
          if ($type == 'gamecode') {
            return \Drupal::formBuilder()->getForm('Drupal\summergame\Form\SummerGamePlayerRedeemForm', $pid);
          }
          elseif ($type == 'consume') {
            if (\Drupal::config('summergame.settings')->get('summergame_points_enabled')) {
              return \Drupal::formBuilder()->getForm('Drupal\summergame\Form\SummerGamePlayerConsumeForm', $pid);
            }
            else {
              \Drupal::messenger()->addError("Summer Game is not currently active for logging rewards");
              return new RedirectResponse('/summergame/player');
            }
          }
          else {
            \Drupal::messenger()->addError("Invalid redeem form type");
            return new RedirectResponse('/summergame/player');
          }
        }
        else {
          \Drupal::messenger()->addError("Invalid ID or no access for player #$pid");
          return new RedirectResponse('/summergame/player');
        }
      }
      else {
        // pid = 0, try to load default player record and redirect
        if ($player = summergame_get_active_player()) {
          $redirect_uri = '/summergame/player/' . $player['pid'] . '/' . $type;
          if ($_GET['text']) {
            $redirect_uri .= '?text=' . $_GET['text'];
          }
          return new RedirectResponse($redirect_uri);
        }
        else {
          \Drupal::messenger()->addMessage("Add a player to your account to play the $gameDisplayName");
          return new RedirectResponse('/summergame/player/new');
        }

      }
    }
    else {
      \Drupal::messenger()->addMessage("You must be logged in to redeem a $gameDisplayName code.");
      return new RedirectResponse("/user/login?destination=" . $_SERVER['REQUEST_URI']);
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

  public function set_active($pid = 0) {
    if ($player = summergame_player_load($pid)) {
      if ($player['uid']) {
        $account = User::load($player['uid']);
        if (isset($account)) {
          // Use the user data service to store active Player ID
          \Drupal::service('user.data')->set('summergame', $account->id(), 'sg_active_pid', $pid);
          \Drupal::messenger()->addMessage(['#markup' => 'Player #' . $pid . ' (' . $player['nickname'] . ') is now the active player for the website account <em>' .
                             $account->get('name')->value . '</em>. Online activities that earn points (checkout history, reviews, etc.) will now be awarded ' .
                             'to this player.']);
        }
        else {
          \Drupal::messenger()->addWarning('Cannot load the website account associated with Player #' . $pid);
        }
      }
      else {
        \Drupal::messenger()->addWarning('No website user associated with Player #' . $pid);
      }
    }
    else {
      \Drupal::messenger()->addMessage('No player found with Player #' . $pid);
    }
    return new RedirectResponse('/summergame/player/' . $pid);
  }

  public function gcpc($pid = 0) {
    if ($player = summergame_player_load(['pid' => $pid])) {
      if (!$player['phone']) {
        $db = \Drupal::database();
        unset($player['bids']);
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
        $char = summergame_get_phone_character($player['pid']);
        \Drupal::messenger()->addMessage('TEXT ' . $char. $code . ' to 734-327-4200 to connect your phone');
      }
      return new RedirectResponse('/summergame/player/' . $player['pid']);
    }
    return new RedirectResponse('/summergame/player');
  }

  public function ledger($pid) {
    $summergame_settings = \Drupal::config('summergame.settings');
    $ledger = NULL;
    $player = summergame_player_load(['pid' => $pid]);

    if ($player) {
      $player_access = summergame_player_access($player['pid']);

      if (!$player['show_myscore'] && !$player_access) {
        \Drupal::messenger()->addError("Player #$pid's Score Card is private");
        return $this->redirect('<front>');
      }

      // Process GET parameters
      $filter_search = $_GET['filter_search'] ?? FALSE;
      $filter_type = $_GET['filter_type'] ?? FALSE;
      $game_term = $_GET['term'] ?? FALSE;

      $db = \Drupal::database();
      $query = $db->select('sg_ledger', 'l')
        ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
        ->fields('l')
        ->condition('pid', $pid);
      if ($game_term) {
        $query->condition('game_term', $game_term);
      }
      if ($filter_type) {
        $query->condition('type', $filter_type);
      }
      if ($filter_search) {
        $orGroup = $query->orConditionGroup()
          ->condition('description', '%' . $db->escapeLike($filter_search) . '%', 'LIKE')
          ->condition('metadata', '%' . $db->escapeLike($filter_search) . '%', 'LIKE');
        $query->condition($orGroup);
      }
      $query->orderBy('timestamp', 'DESC');
      $query->limit(100);
      $result = $query->execute();

      $score_table = [];
      while ($row = $result->fetchAssoc()) {
        $row['classes'] = [];

        // Check if row is access restricted
        if (strpos($row['metadata'], 'access:player') !== FALSE) {
          if ($player_access) {
            $row['classes'][] = 'access_player';
          }
          else {
            // skip it
            continue;
          }
        }

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
            $hint_row = $db->query("SELECT hint FROM sg_game_codes WHERE text = :text AND game_term = :game_term",
                                  [':text' => $matches[1], ':game_term' => $row['game_term']])->fetch();
            if ($hint_row->hint) {
              $row['description'] = $hint_row->hint;
            }
          }
        }
        // link to nodes
        if (preg_match('/nid:([\d]+)/', $row['metadata'], $matches)) {
          if ($row['type'] != 'Download of the Day' || $player_access) { // Don't link to DotD records
            $node = Node::load($matches[1]);
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
          'classes' => $row['classes'],
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
            $table_row['remove'] = '<a href="/summergame/player/' . $player['pid'] . '/ledger/' . $row['lid'] . '/deletescore">DELETE</a>';
          }
        }
        $score_table[] = $table_row;
      }

      if (count($score_table)) {
        $ledger = $score_table;
        // Get distinct ledger types
        $ledger_types = $db->select('sg_ledger', 'l')
          ->fields('l', ['type'])
          ->condition('pid', $pid)
          ->condition('game_term', $game_term)
          ->distinct()
          ->orderBy('type')
          ->execute()
          ->fetchCol();
        $ledger_types = array_combine($ledger_types, $ledger_types);
        $filter_form = \Drupal::formBuilder()->getForm('Drupal\summergame\Form\SummerGamePlayerLedgerFilterForm', $pid, $ledger_types);
      }
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
      '#theme' => 'summergame_player_ledger',
      '#filter_form' => $filter_form ?? '',
      '#ledger' => $ledger,
      '#pager' => [
        '#type' => 'pager',
        '#quantity' => 5,
        '#game_display_name' => $summergame_settings->get('game_display_name'),
      ]
    ];
  }

  public function leagues($pid = 0) {
    if (\Drupal::config('summergame.settings')->get('summergame_leagues_enabled')) {
      $pid = (int)$pid;
      if ($pid) {
        $player = summergame_player_load(['pid' => $pid]);
      }
      else {
        // Default to the active player if none specified
        if ($player = summergame_get_active_player()) {
          return new RedirectResponse('/summergame/player/' . $player['pid'] . '/leagues');
        }
      }
      if ($player) {
        // Check if user has access to this player
        if (!summergame_player_access($player['pid'])) {
          \Drupal::messenger()->addError("You do not have access to Player #{$player['pid']}'s leagues");
          return new RedirectResponse('summergame/leaderboard');
        }

        // League Code Display
        $league_code = \Drupal::formBuilder()->getForm('Drupal\summergame\Form\SummerGameLeagueCodeForm', $player['pid']);

        // Display Join Form
        $join_form = \Drupal::formBuilder()->getForm('Drupal\summergame\Form\SummerGameLeagueJoinForm', $player['pid']);

        // Get League list for Player
        $player_leagues = summergame_player_leagues($player['pid']);

        return [
          '#theme' => 'summergame_player_leagues_page',
          '#attached' => [
            'library' => [
              'summergame/summergame-lib'
            ]
          ],
          '#cache' => [
            'max-age' => 0, // Don't cache, always get fresh data
          ],
          '#player' => $player,
          '#league_code' => $league_code,
          '#player_leagues' => $player_leagues,
          '#join_form' => $join_form,
        ];
      }
      else {
        \Drupal::messenger()->addError("Invalid Player ID: $player_id");
        return new RedirectResponse('/summergame/player');
      }
    }
    else {
      \Drupal::messenger()->addError("Leagues are not currently enabled");
      return new RedirectResponse('/summergame/player');
    }
  }

  public function getRecentBadges() {
    $session = \Drupal::request()->getSession();
    $recently_viewed_badges = $session->get('recently_viewed_badges');
    $resp = json_decode("{}");

    foreach($recently_viewed_badges as $key=>$value){

      $node = \Drupal::entityTypeManager()->getStorage('node')->load($key);

      $badgeData = array();
      $badgeData['node'] = $node;
      $badgeData['logged_in'] = \Drupal::currentUser()->isAuthenticated();
      $badgeData = BadgeRenderer::abstractSGBadgeRender($badgeData);
      $token_service = \Drupal::token();
      $body_field_data = $node->get('body')->value;

      $token_data = array(
          'node' => $node,
      );
      $token_options = ['clear' => TRUE];
      $badgeData["node"]=$node;
      $badgeData["nid"]=$node->get('nid')->value;
      $badgeData["title"]=$node->get('title')->value;
      $badgeData["body"]= $token_service->replace($body_field_data, $token_data, $token_options);
      $badgeData["created_raw"]= $node->get('created')->value;

      if (isset($badgeData['node']->body)) {
        $badgeData['parsed_body'] = [
          '#type' => 'processed_text',
          '#text' => $token_service->replace($badgeData['node']->body->value),
          '#format' => $badgeData['node']->body->format,
        ];

        if (isset($badgeData['content']['body'][0]['#text'])) {
          $badgeData['content']['body'][0]['#text'] = $badgeData['parsed_body']['#text'];
        }
      }

      $renderArray = array(
        "badge"=>$badgeData,
        "node"=>$badgeData["node"],
        "badge_url"=>$badgeData["node"]->toUrl()->setAbsolute()->toString()
      );

      $module_path = \Drupal::service('module_handler')->getModule('summergame')->getpath();
      $html = $this->renderTwig($module_path."/templates/sg-badge-display-embed.html.twig",  $renderArray);
      $resp->html[$key] = $html;
    }
    $response = new JsonResponse($resp, 200);
    return $response;
  }

  private function renderTwig($template_file, array $variables){
    $renderArray = [
      '#type'     => 'inline_template',
      '#template' => \file_get_contents($template_file),
      '#context'  => $variables,
    ];
    return (string) \Drupal::service('renderer')->renderPlain($renderArray);
  }

}
