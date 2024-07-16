<?php

namespace Drupal\migration_coegatech\EventSubscriber;

use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\block_content\Entity\BlockContent;


/**
 * Class PostMigrationSubscriber.
 *
 * Run our user flagging after the last node migration is run.
 *
 * @package Drupal\YOUR_MODULE
 */
class PostMigrationSubscriber implements EventSubscriberInterface {

  /**
   * Get subscribed events.
   *
   * @inheritdoc
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::POST_IMPORT][] = ['onMigratePostImport'];
    return $events;
  }

  /**
   * Check for our specified last node migration and run our flagging mechanisms.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The import event object.
   */
  public function onMigratePostImport(MigrateImportEvent $event) {
		// Clean up video fields to complete the transition to YouTube field.
    if ($event->getMigration()->getBaseId() == 'coegatech_paragraphs_video') {
      $this->cleanUpVideoFields();
    }
		elseif ($event->getMigration()->getBaseId() == 'coegatech_paragraphs_card') {
      $this->cleanUpRelativeUrls('gtcoe_card','field_gtcoe_card_link');
    }
		elseif ($event->getMigration()->getBaseId() == 'coegatech_paragraphs_button') {
      $this->cleanUpRelativeUrls('gtcoe_button','field_gtcoe_button_link');
    }
  }

  /**
   * Transforms relative URL paths to use the internal: parameter.
   */
  private function cleanUpRelativeUrls($block = 'gtcoe_card', $field = 'field_gtcoe_button_link') {
		// Query block type.
		$query = \Drupal::entityQuery('block_content');
		$query->condition('type', [$block], 'IN');
		$query->exists($field);
		$block_ids = $query->execute();
		//
		if (!empty($block_ids)) {
			\Drupal::logger('migrate_news')->notice("Received " . sizeof($block_ids) . " " . $field . " values to process.");
			foreach ($block_ids as $bid) {
				$block = BlockContent::load($bid);
				// Check the input.
				$block_field_uri = $block->$field->uri;
				## Checking if link field has / as its first URI, meaning an internal linky link.
				if(!empty($block->$field) && strpos( $block_field_uri, "/" ) === 0){
					\Drupal::logger('migrate_news')->notice("Configuring " . $block_field_uri);
					// https://drupal.stackexchange.com/questions/248198/uncaught-php-exception-invalidagrumentexception-the-uri-is-invalid
					$block->$field->uri = "internal:" . $block_field_uri;
					// Now, save.
					$block->save();
					\Drupal::logger('migrate_news')->notice("Saved field: " . $block->$field->uri);
				}
				// Replace if an absolute link to an internal resource... d'oh. works with https:// and http://
				elseif (!empty($block->$field) && strpos( $block_field_uri, "https://coe.gatech.edu" ) === 0 || strpos( $block_field_uri, "http://coe.gatech.edu" ) === 0 ) {
					\Drupal::logger('migrate_news')->notice("Configuring " . $block_field_uri);
					// Using parse_url to grab just the path from the full URL, and stripping out any outdate URL context.
					$block->$field->uri = "internal:" . parse_url($block_field_uri)['path'];
					// Now, save.
					$block->save();
					\Drupal::logger('migrate_news')->notice("Saved field: " . $block->$field->uri);
				}
			}
		}
	}

  /**
   * Sanity checks videos and adds Video ID to each.
   */
  private function cleanUpVideoFields() {
		$block_ids = \Drupal::entityQuery('block_content')
				->condition('type', ['coe_youtube_embed'], 'IN')
				->execute();
		if (!empty($block_ids)) {
			\Drupal::logger('migrate_news')->notice("Received " . sizeof($block_ids) . " videos to process.");
			foreach ($block_ids as $bid) {
				$block = BlockContent::load($bid);
				// If video ID is empty, proccess it!
				if(empty($block->field_coe_youtube->video_id)){
					// Check the input.
					$block_field = $block->field_coe_youtube->input;
					if(!empty($block_field)){
						\Drupal::logger('migrate_news')->notice("Loading video block " . $bid . ": " . $block_field);
						$block_field_id = youtube_get_video_id($block_field);
						// Checking to see if we did generate video_id
						if(!empty($block_field_id)){
							\Drupal::logger('migrate_news')->notice("Configuring " . $block_field_id);
							$youtube_values = array(
								'video_id' => $block_field_id,
								'input' => $block_field,
							);
							// If so, setting it and saving it.
							$block->set("field_coe_youtube", $youtube_values)->save();
							\Drupal::logger('migrate_news')->notice("Saved field video_id: " . $block->field_coe_youtube->video_id);
						}
						else {
						// if empty, then not a YouTube video, and we should delete field.
						\Drupal::logger('migrate_news')->notice("Emptied video_id: " . $bid);
						$block->set("field_coe_youtube", [])->save();
						}
					}
				}
			}
		}
  }
}