<?php

namespace Drupal\migration_coegatech\Plugin\migrate\process;

use Drupal\migration_coegatech\LayoutBase;
use Drupal\migration_coegatech\LayoutMigrationItem;
use Drupal\migration_coegatech\LayoutMigrationMissingBlockException;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Paragraphs Layout process plugin.
 *
 * Available configuration keys:
 *   - source_field: The source field containing paragraphs.
 *
 * @code
 * layout_builder__layout:
 *   plugin: layout_builder_layout
 *   source_field: field_paragraphs
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "paragraphs_layout"
 * )
 */
class ParagraphsLayout extends LayoutBase {

  /**
   * Transform paragraph source values into a Layout Builder sections.
   *
   * @param mixed $value
   *   The value to be transformed.
   * @param \Drupal\migrate\MigrateExecutableInterface $migrate_executable
   *   The migration in which this process is being executed.
   * @param \Drupal\migrate\Row $row
   *   The row from the source to process. Normally, just transforming the value
   *   is adequate but very rarely you might need to change two columns at the
   *   same time or something like that.
   * @param string $destination_property
   *   The destination property currently worked on. This is only used together
   *   with the $row above.
   *
   * @return \Drupal\layout_builder\Section[]
   *   A Layout Builder Section object populated with Section Components.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\migrate\MigrateException
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!isset($this->configuration['source_field'])) {
      throw new MigrateException('Missing source_field for paragraph layout process plugin.');
    }

    // Initialize the return sections[] array.
    $sections = [];

    // to-do: move this
    $map = $row->getSource()['constants']['map'];

    // Pulling each container
    $pg_containers = $row->getSourceProperty($this->configuration['source_field']);

    if (is_array($pg_containers) && !empty($pg_containers)) {
      #\Drupal::logger('migrate_news')->notice('Processing news item with containers: ' . $this->configuration['source_field']);
       // Process each container value
      foreach ($pg_containers as $delta => $pg_container) {
        \Drupal::logger('migrate_news')->notice('+++++ STARTING CONTAINER: ' . $pg_container['value'] );
        $pg_entity_id = $this->getParagraphContent('field_data_field_gt_container', 'field_gt_container_value', $pg_container['value'], 'entity_id');
        \Drupal::logger('migrate_news')->notice('*** HOST ENTITY ' . $pg_entity_id[0] . " ***");

        // Pulling container values
        $lb_settings = $this->lbs_settings_bonanaza($pg_container['value']);

        // Checking the sidebar construction
        $pg_items_sidebar = $this->getParagraphContent('field_data_field_gt_sidebar', 'entity_id', $pg_container['value'], 'field_gt_sidebar_value');

        // Convert single selection to an array for consistent processing.
        if (!is_array($pg_items_sidebar) && !empty($pg_items_sidebar)) {
          $pg_items_sidebar = [$pg_items_sidebar];
        }

        \Drupal::logger('migrate_news')->notice($pg_container['value'] . ' sidebar length: ' . (empty($pg_items_sidebar) ? "0" : sizeof($pg_items_sidebar)) );

        // if empty, create a one-column section
        if(empty($pg_items_sidebar)){
          $section = $this->createSection([], 'layout_onecol', $lb_settings);
          $region = 'content';
        }

        // if not empty, create a two-column section
        else {

          $section = $this->createSection([], 'layout_twocol_section', array_merge(array('column_widths' => '67-33'), $lb_settings));
          $region = 'second';

          \Drupal::logger('migrate_news')->notice("## STARTING SIDEBAR BLOCKS FOR " . $pg_container['value'] );
          // Iterate through each sidebar paragraph and add into section
          foreach ($pg_items_sidebar as $delta => $pg_item) {
            \Drupal::logger('migrate_news')->notice($pg_container['value'] . ' sidebar has paragraph item: ' . $pg_item );
            $this->addBlocktoSection($pg_item, $map, $delta, $section, $region);
          }
          $region = 'first';
        }

        // Getting content areas.
        $pg_items = $this->getParagraphContent('field_data_field_gt_content', 'entity_id', $pg_container['value'], 'field_gt_content_value');
        \Drupal::logger('migrate_news')->notice($pg_container['value'] . ' content length: ' . (empty($pg_items) ? "0" : sizeof($pg_items)) );

        // Convert single selection to an array for consistent processing.
        if (!is_array($pg_items) && !empty($pg_items)) {
          $pg_items = [$pg_items];
        }

        if(!empty($pg_items)){
          \Drupal::logger('migrate_news')->notice("## STARTING CONTENT BLOCKS FOR " . $pg_container['value'] );
          foreach ($pg_items as $delta => $pg_item) {
            \Drupal::logger('migrate_news')->notice($pg_container['value'] . ' content has paragraph item: ' . $pg_item );
            $this->addBlocktoSection($pg_item, $map, $delta, $section, $region);
           }
        }

        // add array to array bonanza
        $sections[] = $section;
        \Drupal::logger('migrate_news')->notice('+++++ ENDING CONTAINER: ' . $pg_container['value'] . " ###");
      }
    }

    \Drupal::logger('migrate_news')->notice('~~~~ summary - created: ' . (empty($sections) ? "0" : sizeof($sections)) . " sections");
    return $sections;
  }

  /**
  * Migrates the found blocks, abstracted so the sidebar can use it.
  *
  * @param [type] $paragraph_id
  * @param [type] $section
  * @param [type] $map
  * @param [type] $delta
  * @return void
  */
  public function addBlocktoSection($paragraph_id, $map, $delta, &$section, $region){

    $type = $this->getParagraphType($paragraph_id);
    \Drupal::logger('migrate_news')->notice('paragraph ' . $paragraph_id . " type: " . $type );
    $migration_id = $map[$type];

    // if migration id exists, we can process since the blocks should exist.
    if (!empty($migration_id)){
      \Drupal::logger('migrate_news')->notice("## STARTING MIGRATION FOR BLOCK " . $migration_id);
      $item = new LayoutMigrationItem($type, $paragraph_id, $delta, $migration_id);
      (!empty($item) ? $section->appendComponent($this->createComponent($item, $region)) : \Drupal::logger('migrate_news')->notice("ERROR =============== Block could not be found " . $migration_id));
      \Drupal::logger('migrate_news')->notice("## ENDING MIGRATION FOR BLOCK " . $migration_id . " " . (empty($section->getComponents()) ? "0" : sizeof($section->getComponents())));
      return;
    }
    return;
  }

  /**
   * Gets the type of paragraph given a paragraph id.
   *
   * Uses basic static caching since this may be called multiple times for the
   * same paragraphs.
   *
   * @param string $id
   *   The paragraph id.
   *
   * @return string
   *   The paragraph bundle.
   */
  public function getParagraphType($id) {
    $types = &drupal_static(__FUNCTION__);
    if (!isset($types[$id])) {
      $query = $this->migrateDb->select('paragraphs_item', 'p');
      $query->fields('p', ['bundle']);
      $query->condition('p.item_id', $id, '=');
      $types[$id] = $query->execute()->fetchField();
    }
    return $types[$id];
  }

  /**
   * Gets the type of paragraph given a paragraph id.
   *
   * Uses basic static caching since this may be called multiple times for the
   * same paragraphs.
   *
   * @param string $id
   *   The paragraph id.
   *
   * @return array $rid
   *    Content container rid.
   *
   */
  public function getParagraphContent($table = 'field_data_field_gt_container', $check = 'field_gt_container_value', $id = '4845', $field = 'bundle') {
    $rid = [];
    $query = $this->migrateDb->select($table, 'p');
    $query->fields('p', [$field]);
    $query->condition('p.' . $check, $id, '=');
    $query->orderBy('delta','ASC');
    $rid = $query->execute()->fetchCol();
    return $rid;
    // this returns an array, so be mindful on iterating through it or grabbing [0] from it.
  }

  public function lbs_settings_bonanaza($paragraph_id){
    // LBS presents itself as a 2dimensional array on Sections, so append onto it.
    $settings['layout_builder_styles_style'] = array();
    \Drupal::logger('migrate_news')->notice("## STARTING LAYOUT BUILDER STIYLES FOR " . $paragraph_id );

    // field_gt_padding
    $terms = $this->getParagraphContent('field_data_field_gt_padding', 'entity_id', $paragraph_id, 'field_gt_padding_target_id');
    #\Drupal::logger('migrate_news')->notice($paragraph_id . " has padding of term ID: " . $terms );
    switch ($terms[0]){
      case '32':
        // 0rem
        // gatech_coe_padding_0
        $settings = $this->lbs_settings_bonanaza_addon($settings, 'gatech_coe_padding_0');
        break;
      case '93':
        // 1rem
        $settings = $this->lbs_settings_bonanaza_addon($settings, 'gatech_coe_padding_1');
        break;
      case '31':
        // 3rem
        $settings = $this->lbs_settings_bonanaza_addon($settings, 'gatech_coe_padding_3');
        break;
      case '33':
        // 5rem
        $settings = $this->lbs_settings_bonanaza_addon($settings, 'gatech_coe_padding_5');
        break;
      case '92':
        // 10rem
        $settings = $this->lbs_settings_bonanaza_addon($settings, 'gatech_coe_padding_10');
        break;
    }

    // field_gt_width
    $terms = $this->getParagraphContent('field_data_field_gt_width', 'entity_id', $paragraph_id, 'field_gt_width_target_id');
    #\Drupal::logger('migrate_news')->notice($paragraph_id . " has width of term ID: " . $terms );
    switch ($terms[0]){
      case '25':
        // 100% wide
        $settings = $this->lbs_settings_bonanaza_addon($settings, 'gatech_coe_padding_100');
        break;
      case '26':
        // 1200px
        // No LBS setting is needed here, so break.
        break;
    }

    // field_gt_background_color
    $terms = $this->getParagraphContent('field_data_field_gt_background_color', 'entity_id', $paragraph_id, 'field_gt_background_color_tid');
    #\Drupal::logger('migrate_news')->notice($paragraph_id . " has background-color of term ID: " . $terms );
    switch ($terms[0]){
      case '89':
        // Light Gold
        break;
      case '86':
        // Tech Gold
        $settings = $this->lbs_settings_bonanaza_addon($settings, 'gatech_coe_bg_techgold');
        break;
      case '88':
        // Tech Gold Medium
        $settings = $this->lbs_settings_bonanaza_addon($settings, 'gatech_coe_bg_techgold');
        break;
      case '87':
        // Buzz Gold
        $settings = $this->lbs_settings_bonanaza_addon($settings, 'gatech_coe_bg_buzzgold');
        break;
      case '8':
        // Buzz Gold 80
        $settings = $this->lbs_settings_bonanaza_addon($settings, 'gatech_coe_bg_buzzgold60');
        break;
      case '6':
        // GT Blue
        $settings = $this->lbs_settings_bonanaza_addon($settings, 'gatech_coe_bg_blue');
        break;
      case '5':
        // GT Blue 80
        $settings = $this->lbs_settings_bonanaza_addon($settings, 'gatech_coe_bg_blue80');
        break;
      case '4':
        // White
        //$settings = Drupal::entityTypeManager()->getStorage('layout_builder_style')->load('gatech_coe_padding_100');
        break;
      case '7':
        // Light Gray
        $settings = $this->lbs_settings_bonanaza_addon($settings, 'gatech_coe_bg_lightgray');
        break;
      case '3':
        // Dark Gray
        $settings = $this->lbs_settings_bonanaza_addon($settings, 'gatech_coe_bg_darkgray');
        break;
      case '1':
        // Black 85
        $settings = $this->lbs_settings_bonanaza_addon($settings, 'gatech_coe_bg_dark');
        break;


    }

    \Drupal::logger('migrate_news')->notice($paragraph_id . " has a settings array with " . implode("|",$settings['layout_builder_styles_style'])  );
    \Drupal::logger('migrate_news')->notice("## ENDING LAYOUT BUILDER STIYLES FOR " . $paragraph_id );
    // (text colour in d8 through LBS is inherited from bg color, so do this only)
    return $settings;
  }


  public function lbs_settings_bonanaza_addon($settings = [], $value = ''){
    $new_value = (\Drupal::entityTypeManager()->getStorage('layout_builder_style')->load($value))->id();
    \Drupal::logger('migrate_news')->notice("Loading " . $new_value . " into settings array.");
    array_push($settings['layout_builder_styles_style'],$new_value);
    #\Drupal::logger('migrate_news')->notice("Array now has length " . count($settings['layout_builder_styles_style']));
    return $settings;
  }
}