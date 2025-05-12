<?php /**
 * @file
 * Contains \Drupal\summergame\Controller\AAPSController.
 */

namespace Drupal\summergame\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Component\Serialization\Json;


/**
 * Player controller for the Summer Game module.
 */
class AAPSController extends ControllerBase {

  public function map() {
    
    $aaps_map_settings = \Drupal::config('summergame.aapssettings');
    $data_raw = $aaps_map_settings->get('data');

    //parse
    $data = [];
    $rawlines = preg_split('/\r\n|\r|\n/', $data_raw);
    $line_num = 0;
    foreach($rawlines as $rawline){
      $line = str_getcsv($rawline, ",", '"');

      $data[] = [
        'label'=>$line[0],
        'latitude'=>$line[1],
        'longitude'=>$line[2],
        'school_image'=>$line_num,
      ];

      $line_num++;

    }
    
    
    $renderArray = [];

    $module_path = \Drupal::service('module_handler')->getModule('summergame')->getpath();
    $html = $this->renderTwig($module_path."/templates/aaps-map.html.twig",  $renderArray);

    return [
      '#markup' => $html,
      '#attached'=>['library'=> array('summergame/summergame-aaps-map-lib'),
        'drupalSettings' => array(
           'data'=>$data
        )],
      '#cache' => ['max-age' => 0]
    ];

  }

  public function school($line_number) {



    $aaps_map_settings = \Drupal::config('summergame.aapssettings');
    $data_raw = $aaps_map_settings->get('data');
  
    //parse
    $data = [];
    $renderArray = [];
    $rawlines = preg_split('/\r\n|\r|\n/', $data_raw);
    $line_num = 0;
    foreach($rawlines as $rawline){
      $line = str_getcsv($rawline, ",", '"');
      
      if ((int)$line_number == $line_num) {

        $data = [
          'x'=>$line[6],
          'y'=>$line[7],
          'w'=>$line[8],
          'h'=>$line[9],
        ];
        $renderArray['label'] = $line[0];
        $renderArray['school_image'] = $line[3];
        $renderArray['code_url'] = md5($line[5]);

        break;
      }

      $line_num++;

    }
    
  

    $module_path = \Drupal::service('module_handler')->getModule('summergame')->getpath();
    $html = $this->renderTwig($module_path."/templates/aaps-school.html.twig",  $renderArray);

    return [
      '#markup' => $html,
      '#attached'=>['library'=> array('summergame/summergame-aaps-school-lib'),
       'drupalSettings' => array(
           'data'=>$data
        )],
      '#cache' => ['max-age' => 0]
    ];
    

    /*  $module_path = \Drupal::service('module_handler')->getModule('summergame')->getpath();
      $html = $this->renderTwig($module_path."/templates/sg-badge-display-embed.html.twig",  $renderArray);
      $resp->html[$key] = $html;
    
    $response = new JsonResponse($resp, 200);
    return $response;*/
  }

   public function code($line_number_hash) {


    $aaps_map_settings = \Drupal::config('summergame.aapssettings');
    $data_raw = $aaps_map_settings->get('data');
  
    //parse
    $data = [];
    $renderArray = [];
    $rawlines = preg_split('/\r\n|\r|\n/', $data_raw);
    $line_num = 0;
    foreach($rawlines as $rawline){
      $line = str_getcsv($rawline, ",", '"');
      
      if ($line_number_hash == md5($line[5])) {

        $renderArray['code_url'] = $line[5];
        $renderArray['code'] = trim($line[4]);

        break;
      }

      $line_num++;

    }
    
    $module_path = \Drupal::service('module_handler')->getModule('summergame')->getpath();
    $html = $this->renderTwig($module_path."/templates/aaps-code.html.twig",  $renderArray);

    return [
      '#markup' => $html,
      '#attached'=>['library'=> array('summergame/summergame-aaps-code-lib')],
      '#cache' => ['max-age' => 0]
    ];
    
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
