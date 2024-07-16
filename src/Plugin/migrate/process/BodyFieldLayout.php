<?php

namespace Drupal\migration_coegatech\Plugin\migrate\process;

use Drupal\migration_coegatech\LayoutBase;
use Drupal\migration_coegatech\LayoutMigrationItem;
use Drupal\migration_coegatech\LayoutMigrationMissingBlockException;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\block_content\Entity\BlockContent;

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
 *   id = "bodyfield_layout"
 * )
 */
class BodyFieldLayout extends LayoutBase {

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

    $legacy_body = $row->getSourceProperty($this->configuration['source_field']);

    if(empty($legacy_body)){
      return null;
    }
    else {
      \Drupal::logger('migrate_news')->notice('## LEGACY BODY IMPORT: ' . strlen($legacy_body[0]['value']) . " ###");
    }

    // Legacy body values have outdated img tags pointing to long-dead images, so remove these.
    $legacy_body_value = preg_replace("/<img[^>]+\>/i", "", $legacy_body[0]['value']);

    // now create new block content
    $block = BlockContent::create([
      'info' => 'Legacy Body Field',
      'type' => 'basic',
      'langcode' => 'en',
      'reusable' => 0,
      'body'     => ['value' => $legacy_body_value, 'format' => 'basic_html'],
    ]);
    //$block->set('body',$legacy_body);
    $block->save();
    \Drupal::logger('migrate_news')->notice('#### CREATED BLOCK CONTENT: ' . $block->id());
    $section_component = $this->createSectionComponent($block->getRevisionId(), 'basic', '0', $region);
    $section->appendComponent($section_component);
    return $section;
  }

}