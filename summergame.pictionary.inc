<?php

function summergame_pictionary_page() {
  drupal_add_js(drupal_get_path('module', 'summergame') . '/summergame.pictionary.js');
  $content .= drupal_get_js();
  $content .=
<<<JST
<script type="text/javascript">
if( Drupal.jsEnabled ) {
  $(document).ready(function() {
    setInterval("pictionaryupdate()", 3000);
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

function summergame_pictionary_update() {
  // Check the Guessed Status
  $html .= '<h1>Answer: ';
  if ($num_guessed = variable_get('summergame_pictionary_guessed', FALSE)) {
    $html .= variable_get('summergame_pictionary_answer', '');
    $html .= " ($num_guessed Correct Answer" . ($num_guessed == 1 ? ')' : 's)');
  }
  else {
    $html .= '?????';
  }
  $html .= '</h1>';

  $res = db_query("SELECT * FROM sg_pictionary ORDER BY time DESC LIMIT 25");
  $rows = array();
  while ($row = db_fetch_array($res)) {
    $rows[] = $row;
  }
  $html .= theme('table', array_keys($rows[0]), $rows);
  print drupal_json(array('html' => $html));

  // The exit() call is critical!
  exit();
}

function summergame_pictionary_settings() {
  $form['summergame_pictionary_active'] = array(
    '#type' => 'checkbox',
    '#title' => t('Activate Game'),
    '#default_value' => variable_get('summergame_pictionary_active', ''),
  );
  $form['summergame_pictionary_answer'] = array(
    '#type' => 'textfield',
    '#title' => t("Pictionary Answer"),
    '#default_value' => variable_get('summergame_pictionary_answer', ''),
    '#size' => 32,
    '#maxlength' => 32,
    '#description' => t("Current Answer to the Pictionary Game"),
  );

  return system_settings_form($form);
}

function summergame_pictionary_settings_validate($form, &$form_state) {
  if ($form_state['value']['summergame_pictionary_answer'] != variable_get('summergame_pictionary_answer', '')) {
    variable_set('summergame_pictionary_guessed', 0);
  }
}
