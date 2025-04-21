<?php

namespace Drupal\summergame\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class SummerGameAdminAAPS extends ConfigFormBase
{

  const SETTINGS = 'sumergame.aapssettings';

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'summergame_admin_aaps';
  }

  /** 
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $this->config(static::SETTINGS)

      ->set('data', $form_state->getValue('data'))
      
      ->save();

    parent::submitForm($form, $form_state);
  }



  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $config = $this->config(static::SETTINGS);


     $form['coordinate_data_area'] = [
        '#type' => 'markup',
        '#markup' => '<h1>AAPS School Codes Game Configuration.</h1><p><b>How to use this page:<br></b> Add the school URL to the "Image URL" text input.  The image will load in and you can then click and drag to define the click box for to get that schools code.  The coordinate information will output in the "Box location data" text field which can be copied into the configuration csv below.</p> <p><b>Uploading images:<br></b>Go to <b>"/admin/content/media"</b> > <b>+ Add Media</b> > <b>Image</b>  to uplaod an image.<br><br><b>Get Image URL:</b><br> Go to "/admin/content/media" and click the media name.  on the resulting oage, right click and select "copy image link"</p><br><hr><div class="coordinate-data-area aadl-box-locator"></div>',
     ];

     $form['data'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Game Data'),
      '#default_value' => $config->get('data'),
      '#description'=> $this->t('Configuration data for AAPS game. <a href="/schoolcodes" target="_blank">View Map</a>.   CSV data field order:<br> label, latitude, longitude, school_image, code, code_image, x, y, w, h'),
    ];

    $form['#attached'] = array(
       'library' => array('summergame/summergame-aaps-admin-map-lib'),
       'drupalSettings' => array(
         'cal_settings'=>json_decode("{}")
       ),
    );


    return parent::buildForm($form, $form_state);
  }
}
