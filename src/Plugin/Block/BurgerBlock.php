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

    \Drupal::service('civicrm')->initialize();
    $burger_service = \Drupal::service('menu_burger.view_services');
    $all_meetings = $burger_service->getAllMeetings();
    foreach ($all_meetings as $meet) {
      $formated_date = $burger_service->formatDateWithMonthInLetterAndHours ($meet->event_start_date);
      $meet->formated_start_date = $formated_date;
      $linked_group = $burger_service->getLinkedGroupWithEvent ($meet->event_id); 
      $meet->linked_group = $linked_group;
    }

    $all_groups = $burger_service->getAllMyGroup();

    return [
      '#theme' => 'menu_burger_block',
      '#cache' => ['max-age' => 0],
      '#content' => [
        'meeting' => $all_meetings, 
        'groups' => $all_groups
      ],
    ];
  }

}
