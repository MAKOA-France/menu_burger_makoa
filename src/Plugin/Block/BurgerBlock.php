<?php

namespace Drupal\menu_burger\Plugin\Block;

use Drupal\node\Entity\Node;
use \Drupal\node\NodeInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\media\Entity\Media;
/**
 * Provides a 'Menu burger ' block.
 *
 * @Block(
 *  id = "menu_burger_block",
 *  admin_label = @Translation("Menu burger block"),
 *  category = @Translation("Menu burger block"),
 * )
 */
class BurgerBlock  extends BlockBase  {



  /**
   * {@inheritdoc}
   */
  public function build() {

    $burger_service = \Drupal::service('menu_burger.view_services');
    $allMeetings = $burger_service->getMeetings();
    foreach ($allMeetings as $meeting) {
      $dateMeeting = $meeting->created_id_civicrm_contact_start_date;
      // dump($dateMeeting);
    }



    return [
      '#theme' => 'menu_burger_block',
      '#cache' => ['max-age' => 0],
      '#content' => [
      ],
    ];
  }

}
