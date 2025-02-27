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
		foreach ($puzzle_data['categories'] as $k => $a) {
			$sets = array_column($a['set'], 'ids');
			foreach ($sets as $n => $c) {
				if (!array_diff($ids, $c) && count($ids) === count($c)) {
					return new JsonResponse(['hint' => $puzzle_data['categories'][$k]['set'][$n]['hint'], 'color' => $a['color'], 'category' => $k, 'correct' => true]);
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
		$file_path = \Drupal::service('file_system')->realpath('private://super_search/' . $nid . '.json');
		$puzzle_data = json_decode(file_get_contents($file_path), true);
		$categories = [];
		foreach ($puzzle_data['categories'] as $c) {
			$categories[] = $c['name'];
		}
		return new JsonResponse(['categories' => $categories, 'letters' => $puzzle_data['letters']]);
	}
}
