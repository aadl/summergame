<?php

function summergame_trivia_page() {
  drupal_add_js(drupal_get_path('module', 'summergame') . '/summergame.trivia.js');
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
              '<img src="' . base_path() . drupal_get_path('module', 'summergame') . '/loading.gif"><br />' .
              'Loading Data...</div>' .
              '</div>';
  print $content;
}

function summergame_trivia_update() {
  // Check the Guessed Status
  $html .= '<h1>Answer: ';
  $count = db_fetch_object(db_query("SELECT COUNT(id) AS count FROM sg_trivia_correct"));
  $num_guessed = $count->count;
  if ($num_guessed) {
    $html .= variable_get('summergame_trivia_answer', '');
    $html .= " ($num_guessed Correct Answer" . ($num_guessed == 1 ? ')' : 's)');
  }
  else {
    $html .= '?????';
  }
  $html .= '</h1>';

  $res = db_query("SELECT * FROM sg_trivia_guesses ORDER BY id DESC LIMIT 25");
  $rows = array();
  while ($row = db_fetch_array($res)) {
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

  return system_settings_form($form);
}

function summergame_trivia_settings_validate($form, &$form_state) {
  if ($form_state['value']['summergame_trivia_answer'] != variable_get('summergame_trivia_answer', '')) {
    // Clear the trivia correct table
    db_query("TRUNCATE TABLE sg_trivia_correct");
  }
}
