<?php

function summergame_trivia_page() {
  drupal_add_js(\Drupal::service('extension.list.module')->getPath('summergame') . '/summergame.trivia.js');
  $content .= drupal_get_js();
  $content .=
<<<JST
<script type="text/javascript">
if( Drupal.jsEnabled ) {
  $(document).ready(function() {
    setInterval("triviaupdate()", 3000);
  });
}
</script>
JST;

  $content .= '<div class="autorefresh_div">' .
              '<div style="text-align: center">' .
              '<img src="' . base_path() . \Drupal::service('extension.list.module')->getPath('summergame') . '/loading.gif"><br />' .
              'Loading Data...</div>' .
              '</div>';
  print $content;
}

function summergame_trivia_update() {
  // Check the Guessed Status
  $db = \Drupal::database();
  $html .= '<h1>Answer: ';

  $query = $db->select('sg_trivia_correct', 'sg_trivia_correct')
      ->fields('sg_trivia_correct', ['id']);
  $num_guessed = $query->countQuery()->execute()->fetchField();

  if ($num_guessed) {
    $html .= variable_get('summergame_trivia_answer', '');
    $html .= " ($num_guessed Correct Answer" . ($num_guessed == 1 ? ')' : 's)');
  }
  else {
    $html .= '?????';
  }
  $html .= '</h1>';

  $sg_trivia_guesses = $db->query("SELECT * FROM sg_trivia_guesses ORDER BY id DESC LIMIT 25")->fetchAll(PDO::FETCH_ASSOC);
  $rows = [];
  foreach ($sg_trivia_guesses as $row) {
    $rows[] = $row;
  }
  $html .= theme('table', array_keys($rows[0]), $rows);
  print drupal_json(array('html' => $html));

  // The exit() call is critical!
  exit();
}

function summergame_trivia_settings() {
  $form['summergame_trivia_active'] = array(
    '#type' => 'checkbox',
    '#title' => t('Activate Game'),
    '#default_value' => variable_get('summergame_trivia_active', ''),
  );
  $form['summergame_trivia_answer'] = array(
    '#type' => 'textfield',
    '#title' => t("Trivia Answer"),
    '#default_value' => variable_get('summergame_trivia_answer', ''),
    '#size' => 32,
    '#maxlength' => 32,
    '#description' => t("Current Answer to the Trivia Game"),
  );
  $form['summergame_trivia_game_term'] = array(
    '#type' => 'textfield',
    '#title' => t("Trivia Game Term"),
    '#default_value' => variable_get('summergame_trivia_game_term', ''),
    '#size' => 32,
    '#maxlength' => 32,
    '#description' => t("Active Game Term for trivia (e.g. SpaceProgramTrivia)"),
  );

  return system_settings_form($form);
}

function summergame_trivia_settings_validate($form, &$form_state) {
  if ($form_state['value']['summergame_trivia_answer'] != variable_get('summergame_trivia_answer', '')) {
    // Clear the trivia correct table
    $db = \Drupal::database();
    $db->query("TRUNCATE TABLE sg_trivia_correct");
  }
}
