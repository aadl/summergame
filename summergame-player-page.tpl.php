<?php
global $user;
drupal_add_css(drupal_get_path('module', 'summergame') . '/summergame.css');
drupal_add_css(drupal_get_path('module', 'summergame') . '/hint.min.css'); // http://kushagragour.in/lab/hint/
$playername = ($player['nickname'] ? $player['nickname'] : $player['name']);

// Prepare links to Other Players
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


// Prepare Scorecards
$player['points'] = summergame_get_player_points($player['pid']);

?>
<div id="summergame-player-page">
  <ul id="player-tabs">
    <li id="main-player">Player: <?php echo $playername; ?></li>

<?php if ($player_access && $other_players) { ?>
    <li><strong>Your Other Players: </strong></li>
    <?php echo implode('', $others_links); ?>
<?php } if ($player['uid'] == $user->uid) { ?>
    <li><?php echo l('Sign up an extra player', 'summergame/player/extra'); ?>
      <span class="hint--bottom" data-hint="Sign up an extra player in your household that doesn't need a separate website account.">[?]</span>
    </li>
<?php } ?>
  </ul>

<?php if ($player_access) { echo theme_summergame_player_info($player); } ?>

<!-- Player Score -->
<?php foreach ($player['points'] as $game_term => $player_game_points) {
        if (is_array($player_game_points)) { ?>
  <div class="scorecard">
  <h2 class="title"><a name="<?php echo $game_term; ?>"><?php echo $game_term; ?> Scorecard</a></h2>

  <div class="player-points">
    <h3>Points</h3>
<?php
      $type_rows = array();
      foreach ($player_game_points['types'] as $type => $type_points) {
        $type_rows[] = array($type, array('data' => $type_points, 'class'=> 'digits'));
      }
      $type_rows[] = array('<strong>TOTAL POINTS EARNED</strong>',
                           array('data' => '<strong>' . $player_game_points['total'] . '</strong>',
                                 'class' => 'digits'));
      if (strpos($game_term, 'SummerGame') === 0) {
        $type_rows[] = array('<strong>Current Points Balance</strong>',
                             array('data' => '<strong>' . $player_game_points['balance'] . '</strong>',
                                   'class' => 'digits'));
      }
      echo theme('table', array('Type', 'Points'), $type_rows);
?>

    <ul class="scorecard-buttons">
      <li class="button">
        <?php echo l('See Full Scorecard', 'summergame/player/ledger/' . $player['pid'], array('query' => array('term' => $game_term))); ?>
      </li>
    </ul>

    <p>Points date range: <br />
<?php
      // Date Ranges
      $latest = reset($player_game_points['ledger']);
      $earliest = end($player_game_points['ledger']);
      echo date('F j, Y, g:i a', $earliest['timestamp']) . ' - <br />' .
           date('F j, Y, g:i a', $latest['timestamp']);
?>
    </p>
  </div>

  <div class="player-badges">
    <h3>Badges</h3>
<?php
      $sg_image_path = base_path() . file_directory_path() . '/sg_images/';
      $badge_grid = '';
      if (count($player_game_points['badges'])) {
        foreach ($player_game_points['badges'] as $badge) {
          $badge_grid .= theme_summergame_badge($badge['bid']);
        }
        echo $badge_grid;
      }
      else {
        echo "<p>This player hasn't earned any $game_term badges yet.</p>";
      }

?>
  </div>
  <div style="clear:both"></div>
  </div><!-- .scorecard -->
<?php
    }
  }
?>
</div><!-- #summergame-player-page -->

<?php
/*


  }

  // Player Details
  if ($player_access) {
    // Extra Players if player has web account
    if ($player['uid']) {
      $all_players = summergame_player_load_all($player['uid']);
      // pop first player off the list to determine primary player
      $primary_player = array_shift($all_players);

      if (count($all_players)) {
        if ($player['pid'] == $primary_player['pid']) {
          // We're on the primary player page
          foreach ($all_players as $extra_player) {
            if ($extra_player['pid'] != $player['pid']) {
              // Show Extra Player Info
              $extra_player['points'] = summergame_get_player_points($extra_player['pid']);
              $extra_playername = $extra_player['nickname'] ? $extra_player['nickname'] : $extra_player['name'];
              $extra_playername = l($extra_playername, 'summergame/player/' . $extra_player['pid']);
              $content .= '<div id="summergame-extra-player">';
              $content .= "<h1>Extra Player: $extra_playername (" . $extra_player['points']['career'] . " career points)</h1>";
              $content .= '<p>[ ' . l('MAKE ACTIVE', 'summergame/player/' . $extra_player['pid'] . '/setactive') . ' ]</p>';
              //$content .= theme_summergame_player_info($extra_player);
              $content .= '</div>';
            }
          }
        }
        else {
          // We're on an extra player page
          $content .= '<p>[ ' . l('Return to main player page', 'summergame/player/' . $primary_player['pid']) . ' ]</p>';
        }
      }
    }
    $content .= theme_summergame_player_info($player);
  }

  // Player Score //////////////////////////////////////////////////////////
  foreach ($player['points'] as $game_term => $player_game_points) {
    if (is_array($player_game_points)) {
      $content .= '<div class="scorecard">';
      $content .= "<h2 class=\"title\"><a name=\"$game_term\">$game_term Scorecard</a></h2>";

      // Points
      $content .= '<div class="player-points">';
      $content .= '<h3>Points</h3>';
      $type_rows = array();
      foreach ($player_game_points['types'] as $type => $type_points) {
        $type_rows[] = array($type, array('data' => $type_points, 'class'=> 'digits'));
      }
      $type_rows[] = array('<strong>TOTAL POINTS EARNED</strong>',
                           array('data' => '<strong>' . $player_game_points['total'] . '</strong>',
                                 'class' => 'digits'));
      if (strpos($game_term, 'SummerGame') === 0) {
        $type_rows[] = array('<strong>Current Points Balance</strong>',
                             array('data' => '<strong>' . $player_game_points['balance'] . '</strong>',
                                   'class' => 'digits'));
      }

      $content .= theme('table', array('Type', 'Points'), $type_rows);

      $content .= '<ul class="scorecard-buttons">' .
                  '<li class="button">' .
                  l('See Full Scorecard', 'summergame/player/ledger/' . $player['pid'],
                    array('query' => array('term' => $game_term))) .
                  '</li>' .
                  '</ul>';

      // Date Ranges
      $latest = reset($player_game_points['ledger']);
      $earliest = end($player_game_points['ledger']);
      $content .= '<p>Points date range: <br />' .
                  date('F j, Y, g:i a', $earliest['timestamp']) . ' - <br />' .
                  date('F j, Y, g:i a', $latest['timestamp']) .
                  '</p>';

      $content .= '</div>';

      // Badges
      $content .= '<div class="player-badges">';
      $content .= '<h3>Badges</h3>';
      $sg_image_path = base_path() . file_directory_path() . '/sg_images/';
      $badge_grid = '';
      if (count($player_game_points['badges'])) {
        foreach ($player_game_points['badges'] as $badge) {
          $badge_grid .= theme_summergame_badge($badge['bid']);
        }
        $content .= $badge_grid;
      }
      else {
        $content .= "<p>This player hasn't earned any $game_term badges yet.</p>";
      }

      $content .= '<p>[ ' . l("See All $game_term Badges", 'summergame/badges/' . $game_term) . ' ]</p>';
      $content .= '</div>';

      $content .= '<div style="clear:both"></div>';
      $content .= '</div>'; // .scorecard
    }
  }
  $content .= '</div>'; // #summergame-player-page
*/
