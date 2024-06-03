<?php

/**
 * @file
 * Contains \Drupal\summergame\Controller\ConnectionController.
 */

namespace Drupal\summergame\Controller;

use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * External connection controller for the Summer Game module.
 */
class ConnectionController extends PlayerController
{
  public function show()
  {
    $user = \Drupal::currentUser();
    if (!$user->isAuthenticated()) {
      return new RedirectResponse('/user/login?destination=/summergame/scatterlog/connect');
    }
    $uid = $user->id();
    $db = \Drupal::database();
    $result = $db->query('SELECT * FROM sg_players WHERE uid = ' . (int) $uid . ' ORDER BY pid ASC');
    $players = [];
    while ($player = $result->fetchAssoc()) {
      $players[] = $player;
    }
    return [
      '#cache' => [
        'max-age' => 0, // Don't cache, always get fresh data
      ],
      '#theme' => 'summergame_player_external_redeem',
      '#uid' => $uid,
      '#players' => $players,
    ];
  }
  public function connect($uid)
  {
    $scatterlogKey = \Drupal::config('summergame.settings')->get('summergame_scatterlog_key');
    return new TrustedRedirectResponse(\Drupal::config('summergame.settings')->get('summergame_scatterlog_url') . '/connect?uid=' . $uid . '&key=' . $scatterlogKey);
  }
}
