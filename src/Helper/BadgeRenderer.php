<?php

namespace Drupal\summergame\Helper;

/**
 * Class BadgeRenderer
 * @package Drupal\summergame\Helper
 */
class BadgeRenderer {

  function __construct(){}

   /**
   * @return array
   */

  static function abstractSGBadgeRender($variables){



  $variables['test_data'] = "Some data";

  $badge_level = $variables['node']->field_badge_level->value;
  switch ($badge_level) {
    case 2:
      $variables['badge_level'] = "⭐️⭐️ Tricky";
      break;
    case 3:
      $variables['badge_level'] = "⭐️⭐️⭐️ Super Tricky";
      break;
    case 4:
        $variables['badge_level'] = "⭐️⭐️⭐️⭐️ Extremely Tricky";
        break;
    default:
      $variables['badge_level'] = "⭐️ Standard";
  }

  // set up badge progress display
  if (\Drupal::moduleHandler()->moduleExists('summergame')) {
    $sg_enabled = \Drupal::config('summergame.settings')->get('summergame_points_enabled');
    $user = \Drupal::currentUser();
    $bid = $variables['node']->id();

    $db = \Drupal::database();
    $awarded = $db->query("SELECT COUNT(pid) AS pcount FROM sg_players_badges WHERE bid=:bid", [':bid' => $bid])->fetch();
    $variables['badge_awards'] = "This badge has been awarded to $awarded->pcount players";

    // Prepare taxonomy terms
    $variables['badge_series'] = [];
    $play_test_term_id = \Drupal::config('summergame.settings')->get('summergame_play_test_term_id');
    if (isset($variables['node']->field_sg_badge_series_multiple)) {
      foreach ($variables['node']->field_sg_badge_series_multiple->referencedEntities() as $ref) {
        $tid = $ref->get('tid')->value;
        if ($tid != $play_test_term_id) {
          $variables['badge_series'][$tid] = $ref->get('name')->value;
        }
      }
    }
    $variables['badge_tags'] = [];
    if (isset($variables['node']->field_badge_tags)) {
      foreach ($variables['node']->field_badge_tags->referencedEntities() as $ref) {
        $tid = $ref->get('tid')->value;
        $variables['badge_tags'][$tid] = $ref->get('name')->value;
      }
    }

    // Get home path for game term
    $variables['game_home_path'] = 'play'; // default to play
    $game_term_homes = summergame_get_game_term_homes();
    foreach ($game_term_homes as $game_term_pattern => $game_term_path) {
      if (strpos($game_term_pattern, $variables['node']->field_badge_game_term->value) !== FALSE) {
        $variables['game_home_path'] = $game_term_path;
        break;
      }
    }

    $badge_progress = '';
    $badge_list = '';
    if ($user->isAuthenticated()) {
      if (summergame_get_active_player() || $user->hasPermission('administer summergame')) {
        if ($user->hasPermission('administer summergame') && isset($_GET['pid'])) {
          $pid = $_GET['pid'];
          $player = summergame_player_load(['pid' => $pid]);
        } else {
          $player = summergame_get_active_player();
          $pid = $player['pid'];
        }

        $badge = new \StdClass();
        $badge->game_term = $variables['node']->field_badge_game_term->value;
        $badge->level = $variables['node']->field_badge_level->value;
        $badge->formula = $variables['node']->field_badge_formula->value;
        $badge->points = $variables['node']->field_badge_points->value;
        $badge->reveal = $variables['node']->field_badge_reveal->value;

        // Look up if the badge was earned and when
        $earned_ts = (int) $db->query("SELECT timestamp FROM sg_players_badges WHERE pid=:pid AND bid=:bid",
                                      [':pid' => $pid, ':bid' => $bid])->fetchField();

        if (strpos($badge->formula, 'SELFAWARD:') === 0) {
          $variables['self_award_form'] = \Drupal::formBuilder()->getForm('Drupal\summergame\Form\SummerGameSelfAwardForm', $pid, $bid);
        }
        else {
          $variables['redeem_form'] = \Drupal::formBuilder()->getForm('Drupal\summergame\Form\SummerGamePlayerRedeemForm', $pid);
        }

        // Handle hidden badges
        $variables['hide_badge'] = FALSE;
        if ($badge->reveal) {
          if ($pid) {
            $required_parts = explode(',', $badge->reveal);
            foreach ($required_parts as $required_part) {
              if (strpos($required_part, 'gamecode:') === 0) {
                // Required Game Code, search player ledger
                $ledger = $db->query("SELECT * FROM sg_ledger WHERE pid = :pid AND metadata LIKE :metadata AND game_term = :term LIMIT 1",
                                                   [':pid' => $pid, ':metadata' => $required_part, ':term' => $badge->game_term])->fetch();
                if (!$ledger->lid) {
                  $variables['hide_badge'] = TRUE;
                  break;
                }
              }
              else {
                // Required Badge
                if (!$player['bids'][$required_part]) {
                  $variables['hide_badge'] = TRUE;
                  break;
                }
              }
            }
            $variables['elements']['#title'] = 'Hidden Badge';
          }
          else {
            // no player
            $variables['hide_badge'] = TRUE;
          }
        }

        if ($badge->points) {
          $term = ($badge->game_term_override ?? $badge->game_term);
          $points = ($badge->points_override ?? $badge->points);
        }

        if (!$variables['hide_badge']) {
          $player_count = 0;
          if (strpos($badge->formula, 'SELFAWARD:') === 0) {
            // Self Awarding Badge
            $tasks = explode('|', substr($badge->formula, strlen('SELFAWARD:')));
            $total_count = count($tasks);
            $player_count = $db->query("SELECT COUNT(lid) AS player_count FROM `sg_ledger` WHERE `pid` = $pid AND metadata LIKE '%badgetask:$bid%'")->fetchField();
            //$formula_type = 'self awarded task' . ($total_count > 1 ? 's' : '');
            $formula_type = 'Bits Complete';
            
          }
          elseif (strpos($badge->formula, '**') !== FALSE) {
            // Multi Game Term ("Hall of Fame") Badge
            list($hof_type, $game_term_pattern, $total_count) = explode('**', $badge->formula);
            if ($hof_type == 'game_terms') {
              $formula_type = '"' . $game_term_pattern . '" games played';
              // Count distict Game Terms that match the pattern
              $gt_count = $db->query("SELECT COUNT(DISTINCT `game_term`) AS gt_count FROM `sg_ledger` WHERE `pid` = $pid AND `game_term` LIKE :game_term_pattern",
                                     [':game_term_pattern' => "%$game_term_pattern%"])->fetchObject();
              $player_count = $gt_count->gt_count;
            }
            elseif ($hof_type == 'total_points') {
              $formula_type = '"' . $game_term_pattern . '" total points';
              $point_total = $db->query("SELECT SUM(`points`) AS point_total FROM `sg_ledger` WHERE `pid` = $pid AND `game_term` LIKE :game_term_pattern " .
                                        "AND `points` > '0' AND `metadata` NOT LIKE '%leaderboard:no%'",
                                        [':game_term_pattern' => "%$game_term_pattern%"])->fetchObject();
              $player_count = $point_total->point_total;
            }
          }
          elseif (preg_match('/^{([\d,]+)}$/', $badge->formula, $matches)) {
            // Badge collection badge
            $formula_type = ' badges earned';
            $bids = explode(',', $matches[1]);
            $total_count = count($bids);
            $badge_list = '';
            foreach ($bids as $bid) {
              $node = Node::load($bid);
              $faded_class = (isset($player['bids'][$bid]) ? '' : ' sg-badge-faded');
              $badge_list .= '<a href="/node/' . $bid . '" target="_blank">' .
                             '<img class="sg-admin-badge ' . $faded_class . '" src="/files/badge-derivs/100/' . $node->field_badge_image->entity->getFilename() . '">' .
                             '</a>';
            }
            $badge_count = $db->query("SELECT COUNT(bid) AS bid_count FROM sg_players_badges WHERE pid=:pid AND bid IN (" . implode(',', $bids) . ")", [':pid' => $pid])->fetch();
            $player_count = $badge_count->bid_count;
          }
          else if (strpos($badge->formula, '^^') !== FALSE) {
            // Multiple Day Badge (Streak)
            list($total_count, $text_pattern) = explode('^^', $badge->formula);
            $lid_count = $db->query("SELECT COUNT(DISTINCT FROM_UNIXTIME(`timestamp`, '%j')) AS lid_count FROM sg_ledger WHERE pid=:pid AND (type LIKE :type OR metadata LIKE :metadata) AND game_term = :term",
                                    [':pid' => $pid, ':type' => $text_pattern, ':metadata' => 'gamecode:'.$text_pattern, ':term' => $badge->game_term])->fetch();

            $player_count = $lid_count->lid_count;
            $formula_type = " days with a " . $text_pattern . " score in your ledger";
          }
          else if (strpos($badge->formula, '::') !== FALSE) {
            // Multiple Badge
            $formula_parts = explode('::', $badge->formula);
            if (count($formula_parts) == 2) {
              list($total_count, $text_pattern) = explode('::', $badge->formula);
              $lid_count = $db->query("SELECT COUNT(lid) AS lid_count FROM sg_ledger WHERE pid=:pid AND (type LIKE :type OR metadata LIKE :metadata) AND game_term = :term",
                                                      [':pid' => $pid, ':type' => $text_pattern, ':metadata' => 'gamecode:'.$text_pattern, ':term' => $badge->game_term])->fetch();
            }
            else if (count($formula_parts) == 3) {
              // New multiple of a ledger pattern (count::field::pattern)
              list($total_count, $ledger_field, $text_pattern) = $formula_parts;
              $lid_count = $db->query("SELECT COUNT(lid) AS lid_count FROM sg_ledger WHERE pid = $pid " .
                                      'AND game_term = :game_term ' .
                                      "AND $ledger_field LIKE :text_pattern",
                                      [':game_term' => $badge->game_term, ':text_pattern' => $text_pattern])->fetchObject();
            }
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
                $gc_rows[] = array('game_code' => '<strong>One of the following:</strong>',
                                   'description' => '',
                                   'earned_on' => '');
                $any_mode = TRUE;
              }
              else {
                $any_mode = FALSE;
              }

              foreach ($text_patterns as $pattern) {
                $gc_row = array('game_code' => '',
                                'description' => '',
                                'earned_on' => '');

                // is it a game code?
                $gc = $db->query("SELECT * FROM sg_game_codes WHERE text = :text AND game_term = :game_term", [':text' => $pattern, ':game_term' => $badge->game_term])->fetch();
                if (isset($gc->code_id)) {
                  $formula_type = ' game codes found';
                  $gc_row['game_code'] = '???????';
                  $gc_row['clue'] = $gc->clue;
                  $clue_trigger = $gc->clue_trigger;
                  if (empty($clue_trigger)) {
                    $gc_row['clue_unlocked'] = true;
                  } else {
                    $clue_unlocked = $db->query("SELECT * FROM sg_ledger WHERE pid = :pid AND metadata = :metadata AND game_term = :term LIMIT 1", [':pid' => $pid, ':metadata' => 'gamecode:'.$clue_trigger, ':term' => $badge->game_term])->fetch();
                    $gc_row['clue_unlocked'] = (isset($clue_unlocked->lid) ? true : false);
                  }
                  if ($hint = $gc->hint) {
                    $hint = str_replace('\'', '\x27', str_replace('"', '\x22', $hint));
                    $replace_lines = ["\n", "\r\n", "\r"];
                    $hint = str_replace($replace_lines, '', $hint);
                    if ($gc_row['clue_unlocked']) {
                      $gc_row['description'] = "<span style=\"color:#06c;\" onclick=\"this.innerHTML = 'HINT: $hint'; this.style.color='black';\">(click for hint)</span>";
                    } else {
                      $gc_row['description'] = 'You have not unlocked this hint';
                    }
                  }
                }
                else {
                  // not a game code
                  $gc_row['game_code'] = $pattern;
                }

                $ledger = $db->query("SELECT * FROM sg_ledger WHERE pid = :pid AND (type LIKE :type OR metadata LIKE :metadata) AND game_term = :term LIMIT 1",
                                                   [':pid' => $pid, ':type' => $pattern, ':metadata' => 'gamecode:'.$pattern, ':term' => $badge->game_term])->fetch();
                if (isset($ledger->lid)) {
                  if (!$player_matches[$code_id]) {
                    $player_count++;
                    $player_matches[$code_id] = TRUE;
                  }
                  if ($gc->code_id) {
                    $gc_row['game_code'] = $gc->text;
                    $gc_row['description'] = $gc->description;
                  }
                  $gc_row['earned_on'] = date('F j, Y, g:i a', $ledger->timestamp);
                }

                if ($any_mode) {
                  $gc_row['Game Code'] = '-- ' . $gc_row['Game Code'];
                }
                $gc_rows[] = $gc_row;
              }
            }
          }

          $badge_progress = '';
          if ($pid) {
            // Show progress bar
            if ($player_count) {
              $percentage = min(round(($player_count / $total_count) * 100), 100);
            }
            else {
              $percentage = 0;
            }
            $badge_progress .= '<h3>Player ' . ($player['nickname'] ? $player['nickname'] : $player['name']);
            $badge_progress .= " Progress: $percentage% ($player_count / $total_count $formula_type)</h3>";
            $badge_progress .= "<progress class=\"sg-badge-progress-bar\" value=\"$percentage\" max=\"100\"></progress><br>";

            // Show badge status under progress bar
            $badge_progress .= '<p class="player-badge-status">';
            if ($earned_ts) {
              $badge_progress .= 'You received this badge on ' . date('F j, Y g:i A', $earned_ts);
            }
            else {
              $badge_progress .= 'You have not yet earned this badge';
            }
            $badge_progress .= '</p>';
          }

          if (!empty($gc_rows)) {
            $variables['gc_rows'] = $gc_rows;
          }

          if ($badge_list) {
            $badge_progress .= '<div class="badge-collection-list">';
            $badge_progress .= '<h3>Required Badges:</h3>';
            $badge_progress .= '<div id="summergame-badges-page">';
            $badge_progress .= $badge_list;
            $badge_progress .= '</div>';
            $badge_progress .= '</div>';
          }
        } // end of hide badge check

        $variables['badge_progress'] = $badge_progress;
      } else {
        $variables['badge_progress'] = '<h3>You don\'t have a player on this account. <a href="/summergame/player">Create one now</a>!</h3>';
      }
    }
  }
  $variables['#cache']['max-age'] = 0;
  $variables['game_display_name'] = \Drupal::config('summergame.settings')->get('game_display_name');

  return $variables;

  }
}
