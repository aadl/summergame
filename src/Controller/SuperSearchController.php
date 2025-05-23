<?php

/**
 * @file
 * Contains \Drupal\summergame\Controller\SuperSearchController.
 */

namespace Drupal\summergame\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * External connection controller for the Summer Game module.
 */
class SuperSearchController extends ControllerBase
{
	public function guess(Request $request, $nid)
	{
		$ids = json_decode($request->getContent())->ids;
		$file_path = \Drupal::service('file_system')->realpath('private://super_search/' . $nid . '.json');
		$puzzle_data = json_decode(file_get_contents($file_path), true);
		$sets = [];
		$progress = $this->getProgress($nid);
		$sIds = array_column($progress, 'ids');
		foreach ($sIds as $s) {
			if (!array_diff($ids, $s) && count($ids) === count($s)) {
				return new JsonResponse(['correct' => false]);
			}
		}
		foreach ($puzzle_data['categories'] as $k => $a) {
			$sets = array_column($a['set'], 'ids');
			foreach ($sets as $n => $c) {
				if (!array_diff($ids, $c) && count($ids) === count($c)) {
					$progress[] = ['ids' => $ids, 'color' => $a['color']];
					$this->storeProgress($nid, $progress);
					if (count($progress) === 36) {
						$answer = '<div class="win-prompt"><p>Solved! The remaining letters reveal...<span class="ss-answer"> ' . $puzzle_data['answer'] . '</span> Redeem this game code for Summer Game points!</p></div>';
					}
					return new JsonResponse(['hint' => $puzzle_data['categories'][$k]['set'][$n]['hint'], 'color' => $a['color'], 'category' => $k, 'correct' => true, 'word' => $puzzle_data['categories'][$k]['set'][$n]['answer'], 'answer' => $answer ?? null]);
				}
			}
		}
		return new JsonResponse(['correct' => false]);
	}
	public function hint(Request $request, $nid, $i)
	{
		$revealed =  json_decode($request->getContent())->revealed ?? [];
		$file_path = \Drupal::service('file_system')->realpath('private://super_search/' . $nid . '.json');
		$puzzle_data = json_decode(file_get_contents($file_path), true);
		$remaining = array_values(array_diff(array_column($puzzle_data['categories'][$i]['set'], 'hint'), $revealed));
		return new JsonResponse(['hint' => $remaining[0]]);
	}
	public function get_puzzle($nid)
	{
		$progress = $this->getProgress($nid, true);
		$file_path = \Drupal::service('file_system')->realpath('private://super_search/' . $nid . '.json');
		if (!file_exists($file_path)) {
			return new JsonResponse([
				'message' => 'No puzzle',
			], 404);
		}
		$salt = \Drupal::config('summergame.settings')->get('summergame_supersearch_salt');
		$sk = substr(base64_encode($salt . date('Ymd')), 0, 16);
		$puzzle_data = json_decode(file_get_contents($file_path), true);
		$completedHints = [];
		$categories = [];
		$encodedAnswers = [];
		foreach ($puzzle_data['categories'] as $k => $c) {
			$categories[] = $c['name'];
			foreach ($c['set'] as $a) {
				$encodedAnswers[] = $this->xor_encode(implode('', $a['ids']), $sk);
			}
			foreach ($progress as $s) {
				$sets = array_column($c['set'], 'ids');
				foreach ($sets as $n => $g) {
					if (!array_diff($s['ids'], $g) && count($s['ids']) === count($g)) {
						$completedHints[] = ['category' => $k, 'hint' => $c['set'][$n]['hint'], 'word' => $c['set'][$n]['answer']];
					}
				}
			}
		}
		if (count($completedHints) === 36) {
			$answer = '<div class="win-prompt"><p>Solved! The remaining letters reveal...<span class="ss-answer"> ' . $puzzle_data['answer'] . '</span> Redeem this game code for Summer Game points!</p></div>';
		}
		return new JsonResponse(['categories' => $categories, 'letters' => $puzzle_data['letters'], 'progress' => $progress, 'answer' => $answer ?? null, 'completedHints' => $completedHints, 'ea' => $encodedAnswers, 'sk' => $sk]);
	}
	private function xor_encode(string $a, $key)
	{
		$result = "";
		for ($i = 0; $i < strlen($a); $i++) {
			$k = ord($key[$i % strlen($key)]);
			$c = ord($a[$i]) ^ $k;
			$result .= chr($c);
		}
		return base64_encode($result);
	}

	private function storeProgress($nid, $progress)
	{
		if (\Drupal::currentUser()->isAnonymous()) {
			$session = \Drupal::requestStack()->getCurrentRequest()->getSession();
			$session->set('ss-' . $nid, $progress);
		} else {
			$uid = \Drupal::currentUser()->id();
			\Drupal::service('user.data')->set('summergame', $uid, 'ss-' . $nid, $progress);
		}
	}
	private function getProgress($nid, $fresh = false): array
	{
		if (\Drupal::currentUser()->isAnonymous()) {
			$session = \Drupal::requestStack()->getCurrentRequest()->getSession();
			$progress = $session->get('ss-' . $nid, []);
		} else {
			if ($fresh) {
				$session = \Drupal::requestStack()->getCurrentRequest()->getSession();
				if ($session->has('ss-' . $nid)) {
					$progress = $session->get('ss-' . $nid, []);
					$this->storeProgress($nid, $progress);
					$session->remove('ss-' . $nid);
				}
			}
			$uid = \Drupal::currentUser()->id();
			$progress = \Drupal::service('user.data')->get('summergame', $uid, 'ss-' . $nid);
		}
		return $progress ?? [];
	}
}
