<?php


namespace Drupal\menu_burger;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Session\AccountInterface;
  
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
/**
 * Class PubliciteService
 * @package Drupal\menu_burger\Services
 */
class MenuBurgerService {

  const DOMAIN_PUBLIC = "cultureviande_dev_makoa_net";
  const SITE_METIER = "https://metiers-viande.com/accueil-metier";
  const ID_SOCIAL_RH = 5012;

    public function getMeetings() {
        $query = "SELECT
        created_id_civicrm_contact.start_date AS created_id_civicrm_contact_start_date,
        civicrm_contact.id AS id,
        created_id_civicrm_contact.title,
        created_id_civicrm_contact.id AS created_id_civicrm_contact_id
      FROM
        civicrm_contact
      INNER JOIN testcultureviand.civicrm_event AS created_id_civicrm_contact ON civicrm_contact.id = created_id_civicrm_contact.created_id
      WHERE
        (DATE_FORMAT((created_id_civicrm_contact.start_date + INTERVAL 7200 SECOND), '%Y-%m-%dT%H:%i:%s') >= DATE_FORMAT(('2023-07-16T22:00:00' + INTERVAL 7200 SECOND), '%Y-%m-%dT%H:%i:%s'))
        AND created_id_civicrm_contact.is_active = '1'
      ORDER BY
        created_id_civicrm_contact_start_date ASC;
      ";


      $allMeetings = \Drupal::database()->query($query)->fetchAll();
      $allDatas = [];
     /*  foreach ($allMeetings as $meeting) {
        $allDatas['date'] = $meeting->created_id_civicrm_contact_start_date;
        $date_format = 'Y-m-d';
        $allDatas['title'] = $meeting->title;
           
        
        $date = new \Drupal\Core\Datetime\DrupalDateTime($meeting->created_id_civicrm_contact_start_date);
        $dateFormat = DateFormat::load('custom', 'fr'); // Load the custom date format

        $day = $date->format('d'); // Day in numeric format
        $month = $date->format('F'); // Month in full textual format
        $year = $date->format('Y'); // Year in numeric format

        // Apply the custom date format
        $formattedDate = $date->format($dateFormat->getPattern());
      }
        return $allMeetings; */
        
    }
    

    
    private function getAllEventId () {
      $query = "SELECT
        Event.start_date AS event_start_date,
        civicrm_contact.id AS id,
        Event.id AS event_id, 
        Event.title as event_title
      FROM
        civicrm_contact
      INNER JOIN civicrm_event AS Event ON civicrm_contact.id = Event.created_id
      WHERE
      DATE_FORMAT(
              (Event.start_date  + INTERVAL 7200 SECOND),
              '%Y-%m-%dT%H:%i:%s'
          ) >= DATE_FORMAT(
              (NOW() + INTERVAL 7200 SECOND),
              '%Y-%m-%dT%H:%i:%s'
          )
        -- (DATE_FORMAT((Event.start_date + INTERVAL 7200 SECOND), '%Y-%m-%dT%H:%i:%s') >= DATE_FORMAT((NOW() + INTERVAL 7200 SECOND), '%Y-%m-%dT%H:%i:%s'))
        AND
         (Event.is_active = '1')
      ";

        $results =  \Drupal::database()->query($query)->fetchAll();
        return $results;
    } 
    
    /**
     * 
     */
    private function checkIfContactIsInsideAGroup ($cid) {

      $allEvent = $this->getAllEventId();
      $contactInsideAgroup = [];
      foreach($allEvent as $event) {
        $event_id = $event->event_id;
        if ($event_id) {

          $events = \Civi\Api4\Event::get(false)
          ->addSelect('rsvpevent_cg_linked_groups.rsvpevent_cf_linked_groups')
          ->addWhere('id', '=', $event_id)
          ->execute();
          if ($events) {
            
            $eventGroupId = $events->getIterator();
            $eventGroupId = iterator_to_array($eventGroupId);  
            $current_user = \Drupal::currentUser();
            $user_roles = $current_user->getRoles();
            foreach ($eventGroupId as $group_id) {
              $allContactId = \Civi\Api4\GroupContact::get(FALSE)
              ->addSelect('contact_id')
              ->addWhere('group_id', '=', $group_id['rsvpevent_cg_linked_groups.rsvpevent_cf_linked_groups'][0])
              ->execute()->getIterator();
              $allContactId = iterator_to_array($allContactId);  
              $allContactId = array_column($allContactId, 'contact_id');

              $authorizedToSeeAllmeet = in_array($cid, $allContactId);
              if (in_array('administrator', $user_roles) || in_array('super_utilisateur', $user_roles) || in_array('permanent', $user_roles)) {
                $authorizedToSeeAllmeet = true;
              }
              
              $contactInsideAgroup[$event_id] = $authorizedToSeeAllmeet;
            }
            
          }
        }
      }

      return $contactInsideAgroup;
    }


        /**
     * Recupère tous les reunions à venir
     * 
     */
  public function getAllMeetings ($cid) {
      /* $query = "SELECT
      Event.start_date AS event_start_date,
      civicrm_contact.id AS id,
      Event.id AS event_id, Event.title as event_title
    FROM
      civicrm_contact
    INNER JOIN civicrm_event AS Event ON civicrm_contact.id = Event.created_id
    WHERE
      (DATE_FORMAT((Event.start_date + INTERVAL 7200 SECOND), '%Y-%m-%dT%H:%i:%s') >= DATE_FORMAT(('2023-07-18T22:00:00' + INTERVAL 7200 SECOND), '%Y-%m-%dT%H:%i:%s'))
      AND (Event.is_active = '1') AND civicrm_contact.id = $cid limit 3
    ";
    $results =  \Drupal::database()->query($query)->fetchAll();

    return $results; */

    $isAllowedMeeting = $this->checkIfContactIsInsideAGroup($cid);
      
      // Use the ArrayFilter class to remove false values
      $isAllowedMeeting = $this->removeFalseValues($isAllowedMeeting);
      $isAllowedMeeting = array_keys($isAllowedMeeting);
      if ($isAllowedMeeting) {
        $isAllowedMeeting = implode(', ', $isAllowedMeeting);
        
        $query = "SELECT
      `created_id_civicrm_contact`.`start_date` AS `event_start_date`,
      `created_id_civicrm_contact`.`title`  as event_title,
      `civicrm_contact`.`id` AS `id`,
      `created_id_civicrm_contact`.`id` AS `created_id_civicrm_contact_id`
  FROM
      `civicrm_contact`
  INNER JOIN
      `civicrm_event` AS `created_id_civicrm_contact` ON `civicrm_contact`.`id` = `created_id_civicrm_contact`.`created_id`
  WHERE
      (
          DATE_FORMAT(
              (`created_id_civicrm_contact`.`start_date` + INTERVAL 7200 SECOND),
              '%Y-%m-%dT%H:%i:%s'
          ) >= DATE_FORMAT(
              (NOW() + INTERVAL 7200 SECOND),
              '%Y-%m-%dT%H:%i:%s'
          )
      )
      AND
      (`created_id_civicrm_contact`.`is_active` = '1')  AND `created_id_civicrm_contact`.`id` IN (" . $isAllowedMeeting . ")   ORDER BY
      `event_start_date` ASC limit 3;
  ";
      $results =  \Drupal::database()->query($query)->fetchAll();
      
    }
     
      return $results;
  }

  
  public function removeFalseValues($array) {
    return array_filter($array, function ($value) {
        return $value !== false;
    });
  }


  public function getContactIdByEmail ($email) {
    $db = \Drupal::database();
    if ($email) {
      return $db->query("select contact_id from civicrm_email where email = '" . $email . "'")->fetch()->contact_id;
    }
    return false;
  }

  /**
     * Permet de récupérer le jour/mois/année heure:minute
     * @return array()
     */
    public function formatDateWithMonthInLetterAndHours ($start_date) {
      // Create a DateTime object from the date string
      $dateTime = new \DateTime($start_date);
  
      // Get the day
      $day = $dateTime->format('d');

      // Get the month
      $month = $dateTime->format('m');
      // Obtient le mois en français
      setlocale(LC_TIME, 'fr_FR.utf8');
      $month = strftime('%B', $dateTime->getTimestamp());

      // Get the year
      $year = $dateTime->format('Y');
      
      // Get the hour
      $hour = $dateTime->format('H');

      // Get the minute value.
      $minute = $dateTime->format('i');

      return [
          'day' => $day, 
          'month' => $month, 
          'year' => $year,
          'hour' => $hour,
          'minute' => $minute
      ];
  }

  public function getLinkedGroupWithEvent ($eventId) {
    $events = \Civi\Api4\Event::get(FALSE)
        ->addSelect('rsvpevent_cg_linked_groups.rsvpevent_cf_linked_groups')
        ->addWhere('id', '=', $eventId)
        ->execute()->getIterator();
    $events = iterator_to_array($events);   
    $group_ids = array_column($events, 'rsvpevent_cg_linked_groups.rsvpevent_cf_linked_groups');
    $data_groups = [];
    foreach ($group_ids[0] as $group_id) {
        $group_name = \Civi\Api4\Group::get(FALSE)
        ->addSelect('title')
        ->addWhere('id', '=', $group_id)
        ->execute()->first();
        
        $data_groups[$eventId] .= $group_name['title'] . ' - ';
    }
    


    return $data_groups;
  }

  /**
   * Recupère les termes enfant d'un terme donnée
   */
  public function getTaxonomyTermChildByParentName ($term_label) {
    // The name of the taxonomy vocabulary (change this to your specific vocabulary machine name).
    // Get the current request object.
    $request = \Drupal::request();

    // Get the base URL of the Drupal site including the protocol.
    $base_url = \Drupal::request()->getSchemeAndHttpHost();


    $taxonomy_vocabulary = 'rubrique';
   

    // The label of the term you want to load.

    $property_array = ['vid' => $taxonomy_vocabulary];
    if ($term_label) {
      $property_array['name'] =  $term_label;
    }
    // Load the term by its label and the vocabulary it belongs to.
    $parent_terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadByProperties($property_array);
    $child_term_ids = '';
    
    
    if (!empty($parent_terms)) {
      
      // Get the first parent term from the result.
      $parent_term = reset($parent_terms);

      // Get the parent term ID.
      $parent_term_id = $parent_term->id();

      // Load the child terms using the EntityQuery service.
      $query = \Drupal::entityQuery('taxonomy_term');
      $query->condition('vid', $taxonomy_vocabulary);
      $query->condition('parent', $parent_term_id);
      $child_term_ids = $query->execute();
      $terms = Term::loadMultiple($child_term_ids);
      $all_names = [];
      

      // Tableau temporaire pour stocker les poids et les indices correspondants
      $tempArray = [];

      foreach ($terms as $index => $term) {
        // if (isset($term->values['weight']['x-default'])) {
          // dump($this->isTermLinkedWithMenu($term->id()));
        if($this->isTermLinkedWithMenu($term->id())) {
          $weight = $this->getNodeFieldValue($term, 'weight');
          $tempArray[$index] = $weight;
        }
        // }
      }

      // Triez le tableau temporaire par valeurs de poids
      asort($tempArray);

      foreach ($terms as $term) {
        if($this->isTermLinkedWithMenu($term->id())) {
          $name = $this->getNodeFieldValue ($term, 'name');
          $string_url = $term->toUrl()->toString();
          $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

          $vocab_name = 'rubrique';
          if (strpos($base_url, 'metiers-viande.') !== false) {
            $vocab_name = 'metiers_viande_com';
          }
          $children = $term_storage->loadChildren($term->id(), $vocab_name);
          $isPublished = $term->status->getValue()[0]['value'];
          if ($isPublished > 0) {//On recupère seulement les terme publiés
            if ((!$this->hasRoleSocial() && !$this->hasRoleSUorPermanent()) && $this->isTermSocial($term->id())) {
              continue;
            }
            if (count($children) < 1) {
              $all_names[$string_url] = ['id' => $term->id(), 'name' => $name, 'weight' => $this->getNodeFieldValue($term, 'weight')]; 
              // $all_names[$string_url] = $name;
            }else {
              $all_names['no-link' . $name] = ['id' => $term->id(), 'name' => $name, 'weight' => $this->getNodeFieldValue($term, 'weight')]; 
              // $all_names['no-link' . $name] = $name;
            }
          }
        }
      }
      
      // Tri du tableau en utilisant la fonction de comparaison
      uasort($all_names, [$this, 'compareByWeight']);
      return $all_names;
    }
  }

  // Fonction de comparaison personnalisée basée sur le poids
  private static  function compareByWeightTerm($a, $b) {
    // dump($a->get('weight')->getValue());
    return $a->get('weight')->getValue()[0]['value'] - $b->get('weight')->getValue()[0]['value'];
  }

  // Fonction de comparaison personnalisée basée sur le poids
  private static  function compareByWeight($a, $b) {
    return $a["weight"] - $b["weight"];
  }
  private function isTermLinkedWithMenu ($term_id) {
    $term = \Drupal\taxonomy\Entity\Term::load($term_id);
    // Check if the term entity is valid
    if ($term) {
      // Check if there are menu links associated with the term
      // $menu_link_manager = \Drupal::service('plugin.manager.menu.link');
      // $menu_links = $menu_link_manager->loadLinksByRoute('entity.taxonomy_term.canonical', ['taxonomy_term' => $term_id]);

      $title = $this->getNodeFieldValue($term, 'name');
      $title = str_replace("'", "''", $title);
      $query = "select enabled from menu_link_content_data where title =  '$title'"; 
      if (\Drupal::database()->query($query)) {

        if (\Drupal::database()->query($query)) {
          $fetched  = \Drupal::database()->query($query)->fetchAll();
          $fetched = array_column($fetched, 'enabled');
          return in_array('1', $fetched);
        }
      } 

      return false;
    }
  }


  /**
   * Check if the current page is a taxonomy term page.
   */
  public function is_taxonomy_term_page() {
    $route_match = \Drupal::service('current_route_match');
    $route_name = $route_match->getRouteName();
    return ($route_name === 'entity.taxonomy_term.canonical');
  }

  

public function getNodeFieldValue ($node, $field) {
  $value = '';
  $getValue = $node->get($field)->getValue();
  if (!empty($getValue)) {
    if (isset($getValue[0]['target_id'])) { //For entity reference (img / taxonomy ...)
      $value = $getValue[0]['target_id'];
    }elseif (isset($getValue[0]['value']))  { //For simple text / date
      $value = $getValue[0]['value'];
    }else if(isset($getValue[0]['uri'])) {
      $value = $getValue[0]['uri'];
    }else { //other type of field

    }
  }
  return $value;
}

/**
 * il y a à un moment duplication du accueil dans le fil d'ariane
 */
public function disableDuplicateHome (&$vars) {
  // La valeur à rechercher
  $valueToFind = "/accueil-0";

  // Variable pour indiquer si la valeur est trouvée
  $found = false;

  // Parcourir le tableau pour vérifier si la valeur est présente
  foreach ($vars['breadcrumb'] as $item) {
      if ($item['url'] === $valueToFind) {
          $found = true;
          break;
      }
  }

  if ($found) {
    unset($vars['breadcrumb'][0]);
  }
}



  /**
   * Permet de recuperer tous mes groupes
   */
  public function getAllMyGroup ($cid) {
    $query = "SELECT
        civicrm_group_civicrm_group_contact.id AS civicrm_group_civicrm_group_contact_id,
        civicrm_group_civicrm_group_contact.title AS civicrm_group_civicrm_group_contact_title,
        civicrm_group_civicrm_group_contact.frontend_title AS civicrm_group_civicrm_group_contact_frontend_title,
        civicrm_group_civicrm_group_contact.parents AS civicrm_group_civicrm_group_contact_parents,
        civicrm_group_civicrm_group_contact.name AS civicrm_group_civicrm_group_contact_name,
        civicrm_group_civicrm_group_contact.group_type AS civicrm_group_civicrm_group_contact_group_type,
        MIN(civicrm_contact.id) AS id,
        MIN(users_field_data_civicrm_uf_match.uid) AS users_field_data_civicrm_uf_match_uid,
        MIN(civicrm_contact_civicrm_uf_match.id) AS civicrm_contact_civicrm_uf_match_id,
        MIN(civicrm_group_civicrm_group_contact.id) AS civicrm_group_civicrm_group_contact_id_1
    FROM
        civicrm_contact
        LEFT JOIN civicrm_uf_match civicrm_uf_match ON civicrm_contact.id = civicrm_uf_match.contact_id
        LEFT JOIN users_field_data users_field_data_civicrm_uf_match ON civicrm_uf_match.uf_id = users_field_data_civicrm_uf_match.uid
        LEFT JOIN civicrm_uf_match users_field_data_civicrm_uf_match__civicrm_uf_match ON users_field_data_civicrm_uf_match.uid = users_field_data_civicrm_uf_match__civicrm_uf_match.uf_id
        LEFT JOIN civicrm_contact civicrm_contact_civicrm_uf_match ON users_field_data_civicrm_uf_match__civicrm_uf_match.contact_id = civicrm_contact_civicrm_uf_match.id
        LEFT JOIN civicrm_group_contact civicrm_group_contact ON civicrm_contact.id = civicrm_group_contact.contact_id AND civicrm_group_contact.status = 'Added'
        LEFT JOIN civicrm_group civicrm_group_civicrm_group_contact ON civicrm_group_contact.group_id = civicrm_group_civicrm_group_contact.id
    WHERE
    (civicrm_group_civicrm_group_contact.group_type LIKE '%3%')  AND
         (civicrm_group_civicrm_group_contact.is_active = '1')
        AND civicrm_contact.id = $cid
    GROUP BY
        civicrm_group_civicrm_group_contact_id,
        civicrm_group_civicrm_group_contact_title,
        civicrm_group_civicrm_group_contact_frontend_title,
        civicrm_group_civicrm_group_contact_parents,
        civicrm_group_civicrm_group_contact_name,
        civicrm_group_civicrm_group_contact_group_type
    ORDER BY
        civicrm_group_civicrm_group_contact_parents ASC,
        civicrm_group_civicrm_group_contact_name ASC limit 3
    "; 

    $results =  \Drupal::database()->query($query)->fetchAll();

    return $results;
  }

  

  private   function calculateTermDepth(\Drupal\taxonomy\Entity\Term $term) {
    $depth = 0;
    // while ($term->getParent() instanceof \Drupal\taxonomy\Entity\Term) {
      // $term = $term->getParent();
      // $depth++;
    // }
    return $depth;
  }

  public function getAllTaxoWithHierarchySiteMetier ($taxonomy_vocabulary) {
    
    // The label of the term you want to load.

    $property_array = ['vid' => $taxonomy_vocabulary];
    if ($term_label) {
      $property_array['name'] =  $term_label;
    }
      
    $metier_vocab = 'metiers_viande_com';
    // Load the term by its label and the vocabulary it belongs to.
    $parent_terms =   \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'metiers_viande_com']);
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => $metier_vocab,  'status' => 1]);


      // Load the first-level terms (root terms) of the vocabulary.
      $first_level_terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadTree('metiers_viande_com', 0, 1, true);

      $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');


      $first_parent_id = $first_level_terms[0]->get('tid')->getValue()[0]['value'];
      $children = $term_storage->loadChildren($first_parent_id, $vocab_name);
      
      
      //TODO creer une fonction pour le menu SITE METIER et faire le refactoring

      $html = '<ul class="dropdown menu menu-site-metier">';

        // dump($first_level_terms, $children);
      foreach ($children as $term) {
        $term_name = $this->getNodeFieldValue($term, 'name');
        $term_id = $term->id();

        

        //checker si le terme à un enfant
        $children = $term_storage->loadChildren($term_id, $vocab_name);
        if ($children) {
          $html .= '<li class="  li-'. $toggleClasses . ' menu-item menu-item--expanded menu-item--active-trail is-dropdown-submenu-parent opens-right premier-niv"><a class="disabled-button-link"  href="javascript:void(0);">' . $term_name . '<span class="switch-collapsible"></span></a>
              <ul class=" metier-first-menu submenu is-dropdown-submenu first-sub vertical ' . $toggleClasses . ' ">';
              uasort($children, [$this, 'compareByWeightTerm']);
          foreach ($children as $id_term_child => $term_child) {
            $term_name = $this->getNodeFieldValue($term_child, 'name');
            
            //On n'affiche pas le menu si le term n'est pas publié
            if ($term_child->status->value <1) { 
              continue;
            }

            //checker si le terme à un enfant
            $children = $term_storage->loadChildren($id_term_child, $vocab_name);
            if ($children) {
              
              $html .= '<li class="menu-item menu-item--expanded menu-item--active-trail is-dropdown-submenu-parent opens-right second-niv"><a class="disabled-button-link"  href="javascript:void(0);">' . $term_name . '<span class="switch-collapsible"></span></a>
              <ul class=" metier-second-menu submenu is-dropdown-submenu first-sub vertical  " >';  
              foreach ($children as $id_term_child_1 => $term_child_1) {
                
                 //On n'affiche pas le menu si le term n'est pas publié
                if ($term_child_1->status->value <1) { 
                  continue;
                }
                $term_name = $this->getNodeFieldValue($term_child_1, 'name');
                $html .= '<li class="' . $toggleClasses . ' menu-item menu-item--collapsed troisieme-niv"><a href="/taxonomy/term/' . $id_term_child_1 . '">' . $term_name . '</a></li>';
              }
              $html .= '</ul></li>';
            }else {
              $html .= '<li class="menu-item menu-item--collapsed second-niv"><a href="/taxonomy/term/' . $id_term_child . '">' . $term_name . '</a></li>';
            }
          }
          $html .= '</ul></li>';
        }else {
          $html .= '<li class="menu-item menu-item--collapsed premier-niv"><a href="/taxonomy/term/' . $term_id . '">' .  $term_name . '</a></li>';
        }

      }

      $html .= '</ul>';

    return $html;
  }


  public function sitePublicTerms () {
    $checked_site_public_term_parent = [];
    $entity_type_manager = \Drupal::service('entity_type.manager');

    // Get the entity type manager service.
    $entity_type_manager = \Drupal::service('entity_type.manager');

    // Specify the vocabulary machine name.
    $vocabulary_machine_name = 'rubrique';

    // Load the vocabulary.
    $vocabulary = $entity_type_manager->getStorage('taxonomy_vocabulary')->load($vocabulary_machine_name);

    if ($vocabulary) {
      // Get the ID of the vocabulary.
      $vid = $vocabulary->id();

      // Get all level 1 terms of the specified vocabulary.
      $storage = $entity_type_manager->getStorage('taxonomy_term');
      $level_1_terms = $storage->loadTree($vid, 4966, 1, TRUE);
      // Loop through the level 1 terms.
      foreach ($level_1_terms as $term) {
        // vérifier si le domain "site public " est coché
        $isSitePublic = $term->get('field_domain_acces')->getValue();
        if ($isSitePublic) {
          $isSitePublic = array_column($isSitePublic, 'target_id');
          if (in_array(self::DOMAIN_PUBLIC, $isSitePublic)) {//todo mise en prod
            $checked_site_public_term_parent[] = $term->id();
          }
        }
      }
    }
    return $checked_site_public_term_parent;
  }

  public function getAllTaxoWithHierarchyPublicSite () {
    $burger_service = \Drupal::service('menu_burger.view_services');
    $all_parents_term = $this->getTaxonomyTermChildByParentName(null);

    
    /** n'extraire des termes que Communication & interprofession et Culture viande pour le menu site public */

    $whiteListtermMenu = $this->sitePublicTerms();

    foreach ($all_parents_term as $k => $v) {
      if (!in_array($v['id'], $whiteListtermMenu)) {
        unset($all_parents_term[$k]);
      }
    }


    foreach ($all_parents_term as $key => $value) {
      $first_child_term = $burger_service->getTaxonomyTermChildByParentName($value['name']);

      //Si le role d'user courant est social et que le term est de type social aussi
      if ((!$this->hasRoleSocial() && !$this->hasRoleSUorPermanent()) && $this->isTermSocial($value['id'])) {
        continue;
      }
      $all_parents_term[$key] = [$value['name'] => $first_child_term, 'id' => $value['id']];
    }

    foreach ($all_parents_term as $first_key_level => $first_level_value) {
      foreach (reset($first_level_value) as $second_key_level => $second_level_value)  {
        $second_child_term = $burger_service->getTaxonomyTermChildByParentName($second_level_value['name']);
        if ((!$this->hasRoleSocial() && !$this->hasRoleSUorPermanent()) && $this->isTermSocial($second_level_value['id'])) {
          continue;
        }

        if ($second_level_value['id'] == '6391') {
          continue;
        }


        if (isset($first_level_value[array_keys( $first_level_value)[0]][$second_key_level])) {
          $formatted_arr = [];
          foreach($second_child_term as $key => $value) {
            if ((!$this->hasRoleSocial() && !$this->hasRoleSUorPermanent()) && $this->isTermSocial($value['id'])) {
              continue;
            }

            //Seul les termes qui sont cochés "CV PUBLIC" qu'on autorise ici 
            $termObj = $this->loadTermById($value['id']);
            if (in_array(self::DOMAIN_PUBLIC, array_column($termObj->get('field_domain_acces')->getValue(), 'target_id'))) {
              $formatted_arr[$key] = $value['name'];
            }
          }

          $all_parents_term[$first_key_level][$second_key_level] = $formatted_arr;
          $all_parents_term[$first_key_level][array_keys( $first_level_value)[0]][$second_key_level] = [$second_level_value['name'] => $formatted_arr, 'id' => $second_level_value['id']];
        }
      }
    } 
    //$all_parents_term;
    

    $html = '<ul class="dropdown menu cv-pub-site">';


    //Ne pas afficher le menu commission parmis les termes, on l'affiche differament
    // unset($all_parents_term['/accueil/commissions']);
    // unset($all_parents_term['no-linkCommissions']);

    foreach($all_parents_term as $item => $menu) {
      if (strpos($item, 'no-link') ===  false) {// qui n'ont pas de sous-menus
        if (array_keys($menu)[0] != 'id') {
          //ne pas afficher "export"
        
          $html .= '<li class="menu-item menu-item--collapsed premier-niv"><a href="' . $item . '">' .  array_keys($menu)[0] . '</a></li>';
        }
      }else { //qui ont des sous-menus

        if (array_keys($menu)[0] == 'Contacts') {
          $html .= '<li class="menu-item menu-item--collapsed premier-niv"><a href="' . self::SITE_METIER . '">Site métiers</a></li>';
        }
        if (array_keys($menu)[0] != 'id') {//ça veut dire que le terme est de type social donc on n'affiche pas pour les autres roles
          $toggleClasses = in_array('yes', $this->toggleClassMenu($menu[array_keys($menu)[0]])) ? ' menu-to-be-showed ' : ' menu-to-be-hide ';
          
          //Ajout de la filiere juste apres
          // dump(array_keys($menu)[0]);
          if (array_keys($menu)[0] == 'Expertises')  {
            $html .= $this->createMenuFiliere();
          }

          
          if ($menu['id'] == self::ID_SOCIAL_RH) {//On n'affiche pas "social/Rh" pour le site pub
            continue;
          }

          $html .= '<li class="menu-item menu-item--expanded menu-item--active-trail is-dropdown-submenu-parent opens-right premier-niv"><a class="disabled-button-link"  href="javascript:void(0);">' . array_keys($menu)[0] . '<span class="switch-collapsible"></span></a>
          <ul class="submenu is-dropdown-submenu first-sub vertical ' . $toggleClasses . ' ">';
          foreach ($menu[array_keys($menu)[0]] as $key => $submenu)  {
            
            if (array_keys($submenu)[0] != 'id') {//ça veut dire que le terme est de type social donc on n'affiche pas pour les autres roles
              if (strpos($key, 'no-link') ===  false || (empty(reset($submenu)))) { //Si le submenu n'a pas d'enfant

                if (empty(reset($submenu))) {
                  $key = '/taxonomy/term/' . $submenu['id']; 
                }
                $html .= '<li class="menu-item menu-item--collapsed second-niv"><a href="' . $key . '">' . array_keys($submenu)[0] . '</a></li>';
              }else {
                $toggleClasses = in_array('yes', $this->toggleClassMenu($submenu[array_keys($submenu)[0]])) ? ' menu-to-be-showed ' : ' menu-to-be-hide ';
                $html .= '<li class="menu-item menu-item--expanded menu-item--active-trail is-dropdown-submenu-parent opens-right second-niv"><a class="disabled-button-link"  href="javascript:void(0);">' .array_keys($submenu)[0]. '<span class="switch-collapsible"></span></a>
                <ul class="submenu is-dropdown-submenu first-sub vertical ' . $toggleClasses . '  " >';  

                foreach($submenu[array_keys($submenu)[0]] as $k => $v) {
                  if (strpos($k, 'no-link') ===  false) {
                  }
                  $html .= '<li class="menu-item menu-item--collapsed troisieme-niv"><a href="' . $k . '">' . $v . '</a></li>';
                }
                
                $html .= '</ul></li>';
              
              }
            }
          }
          $html .= '</ul></li>';
        }
      } 
    }
    
       
    
  $allGroupAfficherSurExtranet = '<li class="menu-item menu-item--expanded menu-item--active-trail is-dropdown-submenu-parent opens-right premier-niv"><a class="disabled-button-link" href="void(0);">Commissions<span class="switch-collapsible"></span></a>
  <ul class="submenu is-dropdown-submenu first-sub vertical" style="display: block;">';
  
  foreach ($this->getGoupeAfficherSurExtranet() as $group => $groupValue) {
    $allGroupAfficherSurExtranet .= ' <li class="menu-item menu-item--collapsed second-niv">
    <a href="/civicrm-group/' . $groupValue['id']  . '">' . $groupValue['title'] . '</a>
  </li>';
  }

  $allGroupAfficherSurExtranet .= '</ul></li>';
  // $html .= $allGroupAfficherSurExtranet . '</ul>' ;   //Commenté car le client ne veux plus qu'on l'affiche dans le menu (en bas de la liste)

  return $html;

  
}

private function loadTermById ($id) {
  return Term::load($id);
}

public function getFilieres () {
  $filiere = [];
  $optionValues = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('label', 'name', 'description')
      ->addWhere('option_group_id', '=', 163)
      ->execute();

  //ajouter de l'icone avec les filieres    

  foreach ($optionValues as $optionValue) {
    switch($optionValue['name']) {
        case 'Veau':
            $filiere[$optionValue['name']] = ['label' => $optionValue['label'], 'icon' => $optionValue['description'], 'id_term_linked' => 6378]; 
            break;
        case 'Porc':
            $filiere[$optionValue['name']] = ['label' => $optionValue['label'], 'icon' => $optionValue['description'], 'id_term_linked' => 6377]; 
            break;
        case 'Ovin':
            $filiere[$optionValue['name']] = ['label' => $optionValue['label'], 'icon' => $optionValue['description'], 'id_term_linked' => 6376]; 
            break;
        case 'Bovin':
            $filiere[$optionValue['name']] = ['label' => $optionValue['label'], 'icon' => $optionValue['description'], 'id_term_linked' => 6375]; 
            break;
        case 'Caprine':
            $filiere[$optionValue['name']] = ['label' => $optionValue['label'], 'icon' => $optionValue['description'], 'id_term_linked' => 6380]; 
            break;
        case 'Equine':
            $filiere[$optionValue['name']] = ['label' => $optionValue['label'], 'icon' => $optionValue['description'], 'id_term_linked' => 6379]; 
            break;
        case 'Produits_tripiers':
            $filiere[$optionValue['name']] = ['label' => $optionValue['label'], 'icon' => $optionValue['description'], 'id_term_linked' => 6381]; 
            break;

    }

}
  return $filiere;
}

public function createMenuFiliere () {
  $html = '<li class="menu-item menu-item--expanded menu-item--active-trail is-dropdown-submenu-parent opens-right premier-niv"><a class="disabled-button-link" href="void(0);">Filières<span class="switch-collapsible"></span></a>
  <ul class="submenu is-dropdown-submenu first-sub vertical  menu-to-be-hide  " style="display: none;">';
  $allFilieres = $this->getFilieres();
  foreach ($allFilieres as $filiere) {//TODO MENU LIEN VERS CHAQUE FILIERE
    // dump($filiere);
    $html .= '<li class="menu-item menu-item--collapsed second-niv"><a href="/taxonomy/term/' . $filiere['id_term_linked'] . '">' . $filiere['label'] . '</a></li>';
  }
  $html .= '</ul></li>';
  return $html;
}

/**
 * Determine si dans le terme "site public" est coché
 */
public function isTermForSitePub ($termId) {
  $term = Term::load($termId);
  $isSitePub = false;
  $domainType = $this->getNodeFieldValue($term , 'field_domain_acces');
  if ($domainType == self::DOMAIN_PUBLIC) {
    $isSitePub = true;
  }

  return $isSitePub;
}

    public function getAllTaxoWithHierarchy () {
      $burger_service = \Drupal::service('menu_burger.view_services');
      $all_parents_term = $this->getTaxonomyTermChildByParentName(null);
      // asort($all_parents_term);

      foreach ($all_parents_term as $key => $value) {
        $first_child_term = $burger_service->getTaxonomyTermChildByParentName($value['name']);
        
        
        //Ceci permet de ne pas afficher le terme coché pour "site public" de ne pas l'affiché sur le menu extranet
        if ($this->isTermForSitePub($value['id'])) {
          continue;
        }
        //end


        //Si le role d'user courant est social et que le term est de type social aussi
        if ((!$this->hasRoleSocial() && !$this->hasRoleSUorPermanent()) && $this->isTermSocial($value['id']) && ($this->isTermForSitePub($value['id']))) {
          continue;
        }
        $all_parents_term[$key] = [$value['name'] => $first_child_term];
      }

      foreach ($all_parents_term as $first_key_level => $first_level_value) {
        foreach (reset($first_level_value) as $second_key_level => $second_level_value)  {
          $second_child_term = $burger_service->getTaxonomyTermChildByParentName($second_level_value['name']);
          
          if ((!$this->hasRoleSocial() && !$this->hasRoleSUorPermanent()) && $this->isTermSocial($second_level_value['id'])) {
            continue;
          }
          if (isset($first_level_value[array_keys( $first_level_value)[0]][$second_key_level])) {
            $formatted_arr = [];
            foreach($second_child_term as $key => $value) {
              if ((!$this->hasRoleSocial() && !$this->hasRoleSUorPermanent()) && $this->isTermSocial($value['id'])) {
                continue;
              }
              $formatted_arr[$key] = $value['name'];
            }

            $all_parents_term[$first_key_level][$second_key_level] = $formatted_arr;
            $all_parents_term[$first_key_level][array_keys( $first_level_value)[0]][$second_key_level] = [$second_level_value['name'] => $formatted_arr];
          }
        }
      } 
      //$all_parents_term;

      $html = '<ul class="dropdown menu site-extranet-cv">';


      //Ne pas afficher le menu commission parmis les termes, on l'affiche differament
      // unset($all_parents_term['/accueil/commissions']);
      // unset($all_parents_term['no-linkCommissions']);

      foreach($all_parents_term as $item => $menu) {
        if (strpos($item, 'no-link') ===  false) {
          if (array_keys($menu)[0] != 'id') {
            $html .= '<li class="menu-item menu-item--collapsed premier-niv"><a href="' . $item . '">' .  array_keys($menu)[0] . '</a></li>';
          }
        }else {
          if (array_keys($menu)[0] != 'id') {//ça veut dire que le terme est de type social donc on n'affiche pas pour les autres roles
            $toggleClasses = in_array('yes', $this->toggleClassMenu($menu[array_keys($menu)[0]])) ? ' menu-to-be-showed ' : ' menu-to-be-hide ';
            $html .= '<li class="menu-item menu-item--expanded menu-item--active-trail is-dropdown-submenu-parent opens-right premier-niv"><a class="disabled-button-link"  href="javascript:void(0);">' . array_keys($menu)[0] . '<span class="switch-collapsible"></span></a>
            <ul class="submenu is-dropdown-submenu first-sub vertical ' . $toggleClasses . ' ">';
            foreach ($menu[array_keys($menu)[0]] as $key => $submenu)  {
              
              if (array_keys($submenu)[0] != 'id') {//ça veut dire que le terme est de type social donc on n'affiche pas pour les autres roles

                if (strpos($key, 'no-link') ===  false) {
                  $html .= '<li class="menu-item menu-item--collapsed second-niv"><a href="' . $key . '">' . array_keys($submenu)[0] . '</a></li>';
                }else {
                  $toggleClasses = in_array('yes', $this->toggleClassMenu($submenu[array_keys($submenu)[0]])) ? ' menu-to-be-showed ' : ' menu-to-be-hide ';
                  $html .= '<li class="menu-item menu-item--expanded menu-item--active-trail is-dropdown-submenu-parent opens-right second-niv"><a class="disabled-button-link"  href="javascript:void(0);">' .array_keys($submenu)[0]. '<span class="switch-collapsible"></span></a>
                  <ul class="submenu is-dropdown-submenu first-sub vertical ' . $toggleClasses . '  " >';  

                  foreach($submenu[array_keys($submenu)[0]] as $k => $v) {
                    if (strpos($k, 'no-link') ===  false) {
                    }
                    $html .= '<li class="menu-item menu-item--collapsed troisieme-niv"><a href="' . $k . '">' . $v . '</a></li>';
                  }
                  $html .= '</ul></li>';
                
                }
              }
            }
          $html .= '</ul></li>';
        }
        } 
    }

    $allGroupAfficherSurExtranet = '<li class="menu-item menu-item--expanded menu-item--active-trail is-dropdown-submenu-parent opens-right premier-niv"><a class="disabled-button-link" href="void(0);">Commissions<span class="switch-collapsible"></span></a>
    <ul class="submenu is-dropdown-submenu first-sub vertical" style="display: block;">';
    
    foreach ($this->getGoupeAfficherSurExtranet() as $group => $groupValue) {
      $allGroupAfficherSurExtranet .= ' <li class="menu-item menu-item--collapsed second-niv">
      <a href="/civicrm-group/' . $groupValue['id']  . '">' . $groupValue['title'] . '</a>
    </li>';
    }

    $allGroupAfficherSurExtranet .= '</ul></li>';
    // $html .= $allGroupAfficherSurExtranet . '</ul>' ;   //Commenté car le client ne veux plus qu'on l'affiche dans le menu (en bas de la liste)

    return $html;

    
  }

  private function toggleClassMenu ($submenu, $first_element = false) {
    $state = [];
    foreach($submenu as $k => $v) {
      $path_alias_manager = \Drupal::service('path_alias.manager');
      $current_path = \Drupal::service('path.current')->getPath();
      $alias = $path_alias_manager->getAliasByPath($current_path);
      if ($first_element) {
        if(array_key_exists($alias, reset($v))) {
          $state[] = 'yes';
        }
      }

      if (strpos($alias, $k) !== false) {
        $state[] = 'yes';
        break;
      }
    }

    return $state;
  }

  private function getGoupeAfficherSurExtranet () {
    $groups = \Civi\Api4\Group::get(FALSE)
      ->addSelect('id', 'title')
      ->addWhere('group_type', 'CONTAINS', 3)
      ->addWhere('is_active', '=', TRUE)
      ->execute()->getIterator();
    $groups = iterator_to_array($groups);   
    return $groups;
  }

  private function isTermSocial ($termId) {
    $term = Term::load($termId);
    return $this->getNodeFieldValue($term, 'field_social');
  }

  private function hasRoleSocial() {
    // Get the current user object.
    $current_user = \Drupal::currentUser();

    $user = \Drupal\user\Entity\User::load($current_user->id());

    // Get an array of role IDs for the current user.
    $user_roles = $current_user->getRoles();
    $is_admin = $user->hasRole('administrator');
  
    return in_array('social', $user_roles) || $is_admin;
  }

  private function hasRoleSUorPermanent() {
    // Get the current user object.
    $current_user = \Drupal::currentUser();

    $user = \Drupal\user\Entity\User::load($current_user->id());

    // Get an array of role IDs for the current user.
    $user_roles = $current_user->getRoles();
    $is_admin = $user->hasRole('administrator');
    if ($is_admin) return true;
  
    return in_array('permanent', $user_roles) || in_array('super_utilisateur', $user_roles) ;
  }

  
  public function getUserRoles () {
    $current_user = \Drupal::currentUser();

    $user = \Drupal\user\Entity\User::load($current_user->id());

    // Get an array of role IDs for the current user.
    return $current_user->getRoles();
  }
}
