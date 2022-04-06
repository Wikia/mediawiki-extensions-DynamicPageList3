<?php
/**
 * DynamicPageList3
 * CreateTemplateUpdateMaintenance
 *
 * @license		GPL-2.0-or-later
 * @package		DynamicPageList3
 *
 */

namespace DPL\DB;

use CommentStoreComment;
use ContentHandler;
use LoggedUpdateMaintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\UserRigorOptions;
use Title;

/*
 * Creates the DPL template when updating.
 */
class CreateTemplateUpdateMaintenance extends LoggedUpdateMaintenance {
	/**
	 * Handle inserting DPL's necessary template for content inclusion.
	 *
	 * @protected
	 * @return	void
	 */
	protected function doDBUpdates() {
		// Make sure page "Template:Extension DPL" exists
		$title = Title::newFromText( 'Template:Extension DPL' );

		if ( !$title->exists() ) {
			$services = MediaWikiServices::getInstance();
			$pageFactory = $services->getWikiPageFactory();
			$fandomUser = $services->getUserFactory()->newFromName(
				'FANDOMbot',
				UserRigorOptions::RIGOR_NONE
			);
			$page = $pageFactory->newFromTitle( $title );
			$pageContent = ContentHandler::makeContent( "<noinclude>This page was automatically created.  It serves as an anchor page for all '''[[Special:WhatLinksHere/Template:Extension_DPL|invocations]]''' of [http://mediawiki.org/wiki/Extension:DynamicPageList Extension:DynamicPageList (DPL)].</noinclude>", $title );
			$pageUpdater = $page->newPageUpdater( $fandomUser );
			$pageUpdater->setContent( SlotRecord::MAIN, $pageContent );
			$comment = CommentStoreComment::newUnsavedComment(
				'DPL required pages'
			);
			$pageUpdater->saveRevision( $comment, EDIT_NEW | EDIT_FORCE_BOT );
		}
	}

	/**
	 * Get the unique update key for this logged update.
	 *
	 * @protected
	 * @return string Unique Key
	 */
	protected function getUpdateKey() {
		return 'dynamic-page-list-create-template';
	}
}
