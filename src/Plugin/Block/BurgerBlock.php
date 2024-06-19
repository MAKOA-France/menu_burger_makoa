<?php

namespace Drupal\menu_burger\Plugin\Block;

use Drupal\node\Entity\Node;
use \Drupal\node\NodeInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\media\Entity\Media;
use Drupal\taxonomy\Entity\Term;


use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;


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

    // Get the current user.
    $current_user = \Drupal::currentUser();
    $user_id = $current_user->id();
    // Get the base URL of the Drupal site including the protocol.
      
    $base_url = \Drupal::request()->getSchemeAndHttpHost();
    \Drupal::cache()->invalidateAll();
    // Load CiviCRM API
    \Drupal::service('civicrm')->initialize();
    $burger_service = \Drupal::service('menu_burger.view_services');
    if ($user_id) {

      $userRole = $burger_service->getUserRoles();
      /** Si l'utilisateur n'a aucun rôle ou adherent sans communication on n'affiche pas le menu */
      if (!in_array( 'administrator', $userRole) && !in_array( 'admin_client', $userRole) && !in_array( 'super_utilisateur', $userRole) && !in_array( 'permanent', $userRole)
      && !in_array( 'permanent_lecture', $userRole)&& !in_array( 'adherent', $userRole)&& !in_array( 'social', $userRole)
      ) {
        return;
      }


      // Load the user account entity to access the email field.
      $account = \Drupal\user\Entity\User::load($user_id);
      // Get the user's email address.
      $email = $account->getEmail();
      $getId = \Drupal::request()->query->get('cid2') ? \Drupal::request()->query->get('cid2') : \Drupal::request()->query->get('cid');
      $cid = $user_id ? $burger_service->getContactIdByEmail($email) : $getId;
      $all_meetings = $burger_service->getAllMeetings($cid);
      foreach ($all_meetings as $meet) {
        $formated_date = $burger_service->formatDateWithMonthInLetterAndHours ($meet->event_start_date);
        $meet->formated_start_date = $formated_date;
        $linked_group = $burger_service->getLinkedGroupWithEvent ($meet->event_id); 
        $meet->linked_group = $linked_group;
      }

      $idHash = \Civi\Api4\Contact::get(FALSE)
      ->selectRowCount()
      ->addSelect('hash')
      ->addWhere('id', '=', $cid)
      ->execute()->first()['hash'];



      $link_ask_question = (strpos($base_url, 'metiers-viande.') === false) ? '/form/poser-une-question?cid2=' . $cid . '&token=' . $idHash : false;

      // Change 'menu-principal' to the machine name of the menu you want to display.
      $menu_name = 'menu-principal';
      $depth = 3;

      $menuLinkTree = \Drupal::service('menu.link_tree');
      $parameters = new MenuTreeParameters();
      $parameters->setMaxDepth($depth);
      $tree = $menuLinkTree->load($menu_name, $parameters);
      $menu_tree_service = \Drupal::service('menu.link_tree');

      // Optionally, you can transform the tree into a renderable array.
      $manipulators = [
        ['callable' => 'menu.default_tree_manipulators:checkAccess'],
        ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
      ];
      $tree = $menu_tree_service->transform($tree, $manipulators);
      
      $all_menus = $this->getAllMenuHierarchy ($tree, $manipulators);

      $html = '<ul class="dropdown menu letit">';
      foreach ($all_menus as $key => $men)  {
        if (count($men)< 3) {
          if (!is_int($key)) {
            $html .= '<li class="menu-item menu-item--collapsed"><a href="' . $men['link_menus'][0] . '">' . $key . '</a></li>';
            }
        }else {
            $html .= '<li class="menu-item menu-item--expanded menu-item--active-trail is-dropdown-submenu-parent opens-right"><a href="#">' . $key . '<span class="switch-collapsible"></span></a>
            <ul class="submenu is-dropdown-submenu first-sub vertical">';
            foreach ($men as $first_key => $firs_elem) {
              if (count($firs_elem)< 3) {
                if (!is_int($first_key) && $first_key != 'link_menus') {
                  $html .= '<li class="menu-item menu-item--collapsed"><a href="' . $firs_elem['link_menus'][0] . '">' . $first_key . '</a></li>';
                }
              }else {
                $html .= '<li class="menu-item menu-item--expanded menu-item--active-trail is-dropdown-submenu-parent opens-right"><a href="#">' . $first_key . '<span class="switch-collapsible"></span></a><ul class="submenu is-dropdown-submenu second-sub vertical">';
                foreach ($firs_elem as $second_key => $second_elem) {
                    if (!is_int($second_key) && $second_key != 'link_menus') {
                      $html .= '<li class="menu-item menu-item--collapsed"><a href="' . $second_elem['link_menus'][0] . '">' . $second_key . '</a>';
                    }
                }
                $html .= '</ul></li>';
              }
            }
            $html .= '</ul></li>';
        }
      }
      $html .= '</ul>';

    }
    
    
      $markup = ['#markup' => $burger_service->getAllTaxoWithHierarchy()];
    
      /** TODO MEP POUR LE SITE PUBLIC  */
      $isSitePublic = (strpos($base_url, 'cultureviande.dev.makoa.net') !== false)  || (strpos($base_url, 'cultureviande.makoa4.makoa.net') !== false) ? true : false;
      if (strpos($base_url, 'cultureviande.dev.makoa.net') !== false OR strpos($base_url, 'cultureviande.makoa4.makoa.net') !== false) {
        $markup = ['#markup' => $burger_service->getAllTaxoWithHierarchyPublicSite()];
      }
      
      /** END SITE PUBLIC */

      // $markup = ['#markup' => $html];
      if (strpos($base_url, 'metiers-viande.') !== false) {
        $taxonomy_vocabulary = 'metiers_viande_com';
        $markup = ['#markup' => $burger_service->getAllTaxoWithHierarchySiteMetier($taxonomy_vocabulary)];
      }
      $renderable_menu = \Drupal::service('renderer')->render($markup);
      if ($cid && (strpos($base_url, 'metiers-viande.') === false)) {
        $all_groups = $burger_service->getAllMyGroup($cid);
      }

      $isSiteMetier = (strpos($base_url, 'metiers-viande.') !== false) ? true : false;
      $class_nav_site_metier = $isSiteMetier ? ' nav_custom_class_metier ' : '';
      $class_sub_menu_burger = $isSiteMetier ? ' site-metier-sub-menu-burger ' : '';
      


      return [
        '#theme' => 'menu_burger_block',
        '#cache' => ['max-age' => 0],
        '#content' => [
          'meeting' => (strpos($base_url, 'metiers-viande.') === false)  && (strpos($base_url, 'cultureviande.dev.makoa.net') === false)   && (strpos($base_url, 'cultureviande.makoa4.makoa.net') === false) ? $all_meetings : false, 
          'groups' => $all_groups,
          'main_menus' => $all_menus,
          'html_menu' => $renderable_menu,
          'link_ask_question' => $link_ask_question, 
          'isSiteMetier' => $isSiteMetier,
          'isSitePublic' => $isSitePublic,
          'nav_metier' => $class_nav_site_metier, 
          'class_sub_burger' => $class_sub_menu_burger
        ],
      ];
    
  }


  public function getUrlC ($mel) {
    $menu_link_manager = \Drupal::service('plugin.manager.menu.link');
  
    // Load the menu link by its UUID.
    $menu_link = $menu_link_manager->createInstance($mel);
    
    if ($menu_link) {
      // Get the URL object for the menu item.
      $url_object = $menu_link->getUrlObject();

      // Convert the URL object to a string.
      $link_url = $url_object->toString();

      return $link_url;
    }
  }

    /**
   * Helper function to process the menu tree and get the submenus.
   *
   * @param array $tree
   *   The menu tree.
   *
   * @return array
   *   An array containing the menu items and submenus.
   */
  protected function processMenuTree($tree) {
    $submenus = [];

    foreach ($tree as $item) {
      // Add the item to the submenus array.
      $submenus[] = [
        'title' => $item->link->getTitle(),
        'url' => $item->link->getUrlObject()->toString(),
      ];

      // Check if the item has children (submenus).
      if ($item->hasChildren) {
        // Recursively process the child items.
        $submenus = array_merge($submenus, $this->processMenuTree($item->subtree));
      }
    }

    return $submenus;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(\Drupal\Core\Session\AccountInterface $account) {
    // You can add your custom access logic here.
    // For example, allow access only for authenticated users.
    //Ne pas afficher si le nom de domaine est l'extranet
    //On affiche seulement si le nom de domaine est metier
    if ($account->isAnonymous()) {
      $base_url = \Drupal::request()->getSchemeAndHttpHost();
      if ((strpos($base_url, 'metiers-viande.') === false) && (strpos($base_url, 'cultureviande.') === false)) {
        return  \Drupal\Core\Access\AccessResult::forbidden();
      }
    }
    return  \Drupal\Core\Access\AccessResult::allowed();
  }


  /**
   * Function to load the menu and its submenus by name.
   */
  public function load_menu_and_submenus($menu_name) {
    $menu_tree = \Drupal::service('menu.link_tree');
    $parameters = new \Drupal\Core\Menu\MenuTreeParameters();
    $tree = $menu_tree->load($menu_name, $parameters);

    // Process the menu tree to get the submenus.
    $submenus = $this->process_menu_tree($tree);

    return $submenus;
  }

  /**
   * Helper function to process the menu tree and get the submenus.
   */
  public function process_menu_tree($tree) {
    $submenus = [];

    foreach ($tree as $item) {
      // Add the item to the submenus array.
      $submenus[] = [
        'title' => $item->link->getTitle(),
        'url' => $item->link->getUrlObject()->toString(),
      ];

      // Check if the item has children (submenus).
      if ($item->hasChildren) {
        // Recursively process the child items.
        $submenus = array_merge($submenus, process_menu_tree($item->subtree));
      }
    }

    return $submenus;
  }

  /**
   * Retourne un array contenant l'architecture du menu burger
   */
  private function getAllMenuHierarchy ($tree, $manipulators) {
    $menu_tree_service = \Drupal::service('menu.link_tree');
    $tree = $menu_tree_service->transform($tree, $manipulators);
    
    $all_menus = [];

    foreach ($tree as $first_level ) {
      $isEnabled_first_level = $first_level->link->getPluginDefinition()['enabled'];//si le menu est actif
      if ( $isEnabled_first_level) {
        // $lien = $this->getUrlC($first_level->link->getPluginId()
        // $term_id = $first_level->link->getPluginDefinition()['route_parameters']['taxonomy_term'];
        // $is_social = $this->checkIfThereIsDocumentLinkedWithThisTerm($term_id, $first_level->link->getTitle());


        $all_menus[$first_level->link->getTitle()]['link_menus'][] = $this->getUrlC($first_level->link->getPluginId());
        if ($first_level->subtree) {
          foreach ($first_level->subtree as $second_level) {
            // dump($second_level->link, $second_level);
            $isEnabled_second_level = $second_level->link->getPluginDefinition()['enabled'];
            if ($isEnabled_second_level) {


              $term_id = $second_level->link->getPluginDefinition()['route_parameters']['taxonomy_term'];
              $is_social = $this->checkIfThereIsDocumentLinkedWithThisTerm($term_id, $second_level->link->getTitle());
              if (!$is_social) {//TODO CONDITION ROLE SOCIAL AUSSI
                  $all_menus[$first_level->link->getTitle()][$second_level->link->getTitle()][] = $second_level->link->getTitle();
                  $all_menus[$first_level->link->getTitle()][$second_level->link->getTitle()]['link_menus'][] = $this->getUrlC($second_level->link->getPluginId());
                  if ($second_level->subtree) {
                    foreach ($second_level->subtree as $third_level) {
                      $isEnabled_third_level = $third_level->link->getPluginDefinition()['enabled'];
                      if ($isEnabled_third_level) {
                        $all_menus[$first_level->link->getTitle()][$second_level->link->getTitle()][$third_level->link->getTitle()]['link_menus'][] = $this->getUrlC($third_level->link->getPluginId());
                        $all_menus[$first_level->link->getTitle()][$second_level->link->getTitle()][$third_level->link->getTitle()][] = $third_level->link->getTitle();
                      }
                    }
                  }
              }
            } 
          }
        }
      } 
    }

    return $all_menus;
  }

  /**
   * 
   */
  private function checkIfThereIsDocumentLinkedWithThisTerm($termId, $name) {
    // Load the taxonomy term by ID.
    $burger_service = \Drupal::service('menu_burger.view_services');
    if ($termId) {
      
      $term = Term::load($termId);
      $string_query = 'select entity_id from media__field_tag where field_tag_target_id = ' . $termId;
      $all_linked_doc = \Drupal::database()->query($string_query)->fetchAll();
      $hasSocialDocument = false;
      if ($all_linked_doc) {
        $all_linked_doc = array_column($all_linked_doc, 'entity_id');
        $mediaDocuments = Media::loadmultiple($all_linked_doc);
        if ($mediaDocuments) {
          foreach ($mediaDocuments as $idDoc => $document) {
            $isSocial = $burger_service->getNodeFieldValue($document, 'field_social');
            if ($isSocial > 0) {
              $hasSocialDocument = true;
              break;
            }
          }
        }
      }
      return $hasSocialDocument;
    }
  }





}
