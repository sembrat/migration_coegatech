<?php

namespace Drupal\migration_coegatech\Plugin\migrate\process;

use Drupal\migration_coegatech\LayoutBase;
use Drupal\migration_coegatech\LayoutMigrationItem;
use Drupal\migration_coegatech\LayoutMigrationMissingBlockException;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Database\Database;

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
 *   id = "newsimage_layout"
 * )
 */
class NewsImageLayout extends LayoutBase {

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
   * @return \Drupal\layout_builder\Section
   *   A Layout Builder Section object populated with Section Components.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\migrate\MigrateException
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!isset($this->configuration['source_field'])) {
      throw new MigrateException('Missing source_field for body field layout process plugin.');
    }

    // Legacy news do not have attributes, so create a basic section.
    $section = $this->createSection();
    $region = 'content';

    $field_content_images = $row->getSourceProperty($this->configuration['source_field']);

    if(empty($field_content_images)){
      return null;
    }
    else {
      \Drupal::logger('migrate_news')->notice('## LEGACY NEWS IMAGE IMPORT: ' . $field_content_images . " ###");
    }

     // Convert single selection to an array for consistent processing.
    if (!is_array($field_content_images) && !empty($field_content_images)) {
      $field_content_images = [$field_content_images];
    }

    foreach($field_content_images as $delta => $field_image){
      $drupalDb = Database::getConnection('default', 'default');
      $result_fid = $drupalDb->select('migrate_map_coegatech_legacyfiles_csv', 'yt')
      ->fields('yt', ['destid1'])
      ->condition('yt.sourceid1', $field_image['fid'], '=')
      ->execute()
      ->fetchField();

      \Drupal::logger('migrate_news')->notice($delta . ' -- CREATED LEGACY NEWS IMAGE BLOCK CONTENT -- ' . $field_image['filename']);

      // now create new block content
      $block = BlockContent::create([
        'info' => 'Legacy Image Field',
        'type' => 'image',
        'langcode' => 'en',
        'reusable' => 0,
        'field_image' => [
          'target_id' => $result_fid,
          'alt' => $field_image['title'],
          'title' => $field_image['title'],
        ],
      ]);
      $block->save();

      \Drupal::logger('migrate_news')->notice('#### CREATED LEGACY NEWS IMAGE BLOCK CONTENT: ' . $block->id());

      $section_component = $this->createSectionComponent($block->getRevisionId(), 'image', $delta, $region);
      $section->appendComponent($section_component);
    }


    return $section;
  }

}