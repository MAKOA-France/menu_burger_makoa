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


        /**
     * Recupère tous les reunions à venir
     * 
     */
  public function getAllMeetings ($cid) {
      $query = "SELECT
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

    return $results;
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
    $taxonomy_vocabulary = 'rubrique';

    // The label of the term you want to load.

    // Load the term by its label and the vocabulary it belongs to.
    $parent_terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadByProperties([
      'name' => $term_label,
      'vid' => $taxonomy_vocabulary,
    ]);
    
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
      foreach ($terms as $term) {
        $name = $this->getNodeFieldValue ($term, 'name');
        $string_url = $term->toUrl()->toString();
        $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

        $children = $term_storage->loadChildren($term->id(), 'rubrique');
        // dump([$this->getNodeFieldValue ($term, 'name'), $children], ' 555');
        if (count($children) < 1) {
          // dump($name);
          $all_names[$string_url] = $name;
        }else {
          $all_names['no-link' . $name] = $name;
        }
      }
      // usort($all_names);
      return $all_names;
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
  public function getAllMyGroup () {
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
        (civicrm_group_civicrm_group_contact.group_type LIKE '%3%')
        AND (civicrm_group_civicrm_group_contact.is_active = '1')
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
}
